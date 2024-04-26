<?php

namespace SamanJafari\TelescopeElasticsearchDriver;

use Carbon\Carbon;
use DateTimeInterface;
use Elastic\Elasticsearch\Exception\ClientResponseException;
use Elastic\Elasticsearch\Exception\MissingParameterException;
use Elastic\Elasticsearch\Exception\ServerResponseException;
use Exception;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Laravel\Telescope\Contracts\ClearableRepository;
use Laravel\Telescope\Contracts\EntriesRepository;
use Laravel\Telescope\Contracts\PrunableRepository;
use Laravel\Telescope\Contracts\TerminableRepository;
use Laravel\Telescope\EntryResult;
use Laravel\Telescope\EntryType;
use Laravel\Telescope\EntryUpdate;
use Laravel\Telescope\IncomingEntry;
use Laravel\Telescope\Storage\EntryQueryOptions;
use Throwable;

/**
 * Class ElasticsearchEntriesRepository
 * @package App\Services\TelescopeElasticsearch
 */
class ElasticsearchEntriesRepository implements EntriesRepository, ClearableRepository, PrunableRepository, TerminableRepository
{
    /**
     * The tags currently being monitored.
     *
     * @var array|null
     */
    protected              $monitoredTags;
    private TelescopeIndex $telescopeIndex;

    public function __construct()
    {
        $this->telescopeIndex = new TelescopeIndex();
    }

    /**
     * Return an entry with the given ID.
     *
     * @param mixed $id
     *
     * @return EntryResult
     * @throws Exception
     */
    public function find($id): EntryResult
    {
        $entry = $this->telescopeIndex->client->get([
            'index' => $this->telescopeIndex->index,
            'id'    => $id,
        ]);

        if (!$entry) {
            throw new Exception('Entry not found');
        }
        $entryCollect = collect($entry->asArray());

        return $this->toEntryResult($entryCollect->all());
    }

    /**
     * Return all the entries of a given type.
     *
     * @param string|null       $type
     * @param EntryQueryOptions $options
     *
     * @return Collection[\Laravel\Telescope\EntryResult]
     * @throws ClientResponseException
     * @throws ServerResponseException
     */
    public function get($type, EntryQueryOptions $options)
    {
        if ($options->limit < 0) {
            $options->limit = 1000;
        }
        $options->beforeSequence = request()->before;
        $query                   = [
            'from'  => (int)$options->beforeSequence,
            'size'  => $options->limit,
            'sort'  => [
                [
                    'created_at' => [
                        'order' => 'desc',
                    ],
                ],
            ],
            'query' => [
                'bool' => [
                    'must' => [],
                ],
            ],
        ];

        if ($type) {
            $query['query']['bool']['must'][] = [
                'term' => [
                    'type' => $type,
                ],
            ];
        }

        if ($options->batchId) {
            $query['query']['bool']['must'][] = [
                'term' => [
                    'batch_id' => $options->batchId,
                ],
            ];
        }

        if ($options->familyHash) {
            $query['query']['bool']['must'][] = [
                'term' => [
                    'family_hash' => $options->familyHash,
                ],
            ];
        }

        if ($options->tag) {
            $query['query']['bool']['must'][] = [
                'nested' => [
                    'path'  => 'tags',
                    'query' => [
                        'match_phrase' => [
                            'tags.raw' => $options->tag,
                        ],
                    ],
                ],
            ];
        }
        $params = [
            'index' => $this->telescopeIndex->index,
            'type'  => $type,
            'body'  => [
                ...$query,
            ],
        ];
        $data   = $this->telescopeIndex->client->search($params);

        return $this->toEntryResults(collect($data->asArray()))->reject(function ($entry) {
            return !is_array($entry->content);
        });
    }

    /**
     * Map Elasticsearch result to EntryResult collection.
     *
     * @param Collection $results
     *
     * @return Collection
     */
    public function toEntryResults(Collection $results): Collection
    {
        $collect = collect($results->all()['hits']['hits']);

        return $collect->map(function ($entry) {
            return $this->toEntryResult($entry);
        });
    }

    /**
     * Map Elasticsearch document to EntryResult object.
     *
     * @param array $document
     *
     * @return EntryResult
     */
    public function toEntryResult(array $document): EntryResult
    {
        $entry           = $document['_source'] ?? [];
        $requestSequence = 50;
        if (request()->before >= 50) {
            $requestSequence = request()->before + $requestSequence;
        }

        return new EntryResult(
            $entry['uuid'],
            $requestSequence,
            $entry['batch_id'],
            $entry['type'],
            $entry['family_hash'] ?? null,
            $entry['content'],
            Carbon::parse($entry['created_at']),
            Arr::pluck($entry['tags'], 'raw')
        );
    }

    /**
     * Store the given entries.
     *
     * @param Collection<IncomingEntry> $entries
     *
     * @return void
     * @throws ClientResponseException
     * @throws ServerResponseException
     */
    public function store(Collection $entries): void
    {
        if ($entries->isEmpty()) {
            return;
        }

        [$exceptions, $entries] = $entries->partition->isException();

        $this->storeExceptions($exceptions);

        $this->bulkSend($entries);
    }

    /**
     * Store the given array of exception entries.
     *
     * @param Collection $exceptions
     *
     * @return void
     * @throws ClientResponseException
     * @throws ServerResponseException
     */
    protected function storeExceptions(Collection $exceptions): void
    {
        $entries = collect([]);

        $exceptions->map(function ($exception) use ($entries) {
            $params           = [
                'index' => $this->telescopeIndex->index, // Replace with your actual index name
                'body'  => [
                    'query' => [
                        'bool' => [
                            'must' => [
                                ['term' => ['family_hash' => $exception->familyHash()]],
                                ['term' => ['type' => EntryType::EXCEPTION]],
                            ],
                        ],
                    ],
                    'size'  => 1000,
                ],
            ];
            $documents        = $this->telescopeIndex->client->search($params);
            $content          = array_merge(
                $exception->content,
                ['occurrences' => $documents->offsetGet('hits')['total']['value'] + 1]
            );
            $collectDocuments = collect($documents->asArray()['hits']['hits']);
            $entries->merge(
                $collectDocuments->map(function ($document) {
                    $document = collect($document)->toArray();

                    return tap($this->toIncomingEntry($document), function ($entry) {
                        $entry->displayOnIndex = false;
                    });
                })
            );

            $exception->content    = $content;
            $exception->familyHash = $exception->familyHash();
            $exception->tags([
                get_class($exception->exception),
            ]);

            $entries->push($exception);

            $occurrences = $collectDocuments->map(function ($document) {
                $document = collect($document)->toArray();

                return tap($this->toIncomingEntry($document), function ($entry) {
                    $entry->displayOnIndex = false;
                });
            });

            $entries->merge($occurrences);
        });
        $this->bulkSend($entries);
    }

    /**
     * Map Elasticsearch result to IncomingEntry object.
     *
     * @param array $document
     *
     * @return IncomingEntry
     */
    public function toIncomingEntry(array $document): IncomingEntry
    {
        $data = $document['_source'] ?? [];

        return tap(IncomingEntry::make($data['content']), function ($entry) use ($data) {
            $entry->uuid        = $data['uuid'];
            $entry->batchId     = $data['batch_id'];
            $entry->type        = $data['type'];
            $entry->family_hash = $data['family_hash'] ?? null;
            $entry->recordedAt  = Carbon::parse($data['created_at']);
            $entry->tags        = Arr::pluck($data['tags'], 'raw');

            if (!empty($data['content']['user'])) {
                $entry->user = $data['content']['user'];
            }
        });
    }

    /**
     * Use Elasticsearch bulk API to send list of documents.
     *
     * @param Collection<IncomingEntry> $entries
     *
     * @return void
     * @throws ClientResponseException
     * @throws ServerResponseException
     */
    protected function bulkSend(Collection $entries): void
    {
        if ($entries->isEmpty()) {
            return;
        }

        $this->initIndex($index = $this->telescopeIndex);

        $params['body'] = [];
        foreach ($entries as $entry) {
            $params['body'][]                = [
                'index' => [
                    '_id'    => $entry->uuid,
                    '_index' => $index->index,
                ],
            ];
            $data                            = $entry->toArray();
            $data['family_hash']             = $entry->familyHash ?? null;
            $data['tags']                    = $this->formatTags($entry->tags);
            $data['should_display_on_index'] = property_exists($entry, 'displayOnIndex')
                ? $entry->displayOnIndex
                : true;
            $data['@timestamp']              = gmdate('c');

            $params['body'][] = $data;
        }

        $index->client->bulk($params);
    }

    /**
     * Create new index if not exists.
     *
     * @param TelescopeIndex $index
     *
     * @return void
     */
    protected function initIndex(TelescopeIndex $index): void
    {
        if (!$index->exists()) {
            $index->create();
        }
    }

    /**
     * Format tags to elasticsearch input.
     *
     * @param array $tags
     *
     * @return array
     */
    protected function formatTags(array $tags): array
    {
        $formatted = [];

        foreach ($tags as $tag) {
            if (Str::contains($tag, ':')) {
                [$name, $value] = explode(':', $tag);
            } else {
                $name  = $tag;
                $value = null;
            }

            $formatted[] = [
                'raw'   => $tag,
                'name'  => $name,
                'value' => $value,
            ];
        }

        return $formatted;
    }

    /**
     * Store the given entry updates.
     *
     * @param Collection $updates [\Laravel\Telescope\EntryUpdate]
     *
     * @return void
     * @throws ClientResponseException
     * @throws ServerResponseException
     */
    public function update(Collection $updates): void
    {
        $entries = [];
        foreach ($updates as $update) {
            $params = [
                'index' => $this->telescopeIndex->index, // Replace with your actual index name
                'body'  => [
                    'query' => [
                        'bool' => [
                            'must' => [
                                ['term' => ['uuid' => $update->uuid]],
                                ['term' => ['type' => $update->type]],
                            ],
                        ],
                    ],
                    'size'  => 1,
                ],
            ];
            $entry  = $this->telescopeIndex->client->search($params)->asArray();

            if ( !isset($entry['hits']['hits'][0])
                || (gettype($entry['hits']['hits'][0]) !== 'array')
            ) {
                continue;
            }

            $collectEntries = collect($entry['hits']['hits'][0])->toArray();

            $collectEntries['_source']['content'] = array_merge(
                $collectEntries['_source']['content'] ?? [],
                $update->changes
            );

            $entries[] = tap($this->toIncomingEntry($collectEntries), function ($e) use ($update) {
                $e->tags($this->updateTags($update, $e->tags));
            });
        }

        $this->bulkSend(collect($entries));
    }

    /**
     * Update tags of the given entry.
     *
     * @param EntryUpdate $update
     * @param array       $tags
     *
     * @return array
     */
    protected function updateTags(EntryUpdate $update, array $tags)
    {
        if (!empty($update->tagsChanges['added'])) {
            $tags = array_unique(
                array_merge($tags, $update->tagsChanges['added'])
            );
        }

        if (!empty($update->tagsChanges['removed'])) {
            Arr::forget($tags, $update->tagsChanges['removed']);
        }

        return $tags;
    }

    /**
     * Determine if any of the given tags are currently being monitored.
     *
     * @param array $tags
     *
     * @return bool
     */
    public function isMonitoring(array $tags)
    {
        if (is_null($this->monitoredTags)) {
            $this->loadMonitoredTags();
        }

        return count(array_intersect($tags, $this->monitoredTags)) > 0;
    }

    /**
     * Load the monitored tags from storage.
     *
     * @return void
     */
    public function loadMonitoredTags()
    {
        try {
            $this->monitoredTags = $this->monitoring();
        } catch (Throwable $e) {
            $this->monitoredTags = [];
        }
    }

    /**
     * Get the list of tags currently being monitored.
     *
     * @return array
     */
    public function monitoring()
    {
        return [];
    }

    /**
     * Begin monitoring the given list of tags.
     *
     * @param array $tags
     *
     * @return void
     */
    public function monitor(array $tags)
    {
        $tags = array_diff($tags, $this->monitoring());

        if (empty($tags)) {
            return;
        }

        //
    }

    /**
     * Stop monitoring the given list of tags.
     *
     * @param array $tags
     *
     * @return void
     */
    public function stopMonitoring(array $tags)
    {
        //
    }

    /**
     * Prune all of the entries older than the given date.
     *
     * @param DateTimeInterface $before
     * @param bool              $keepExceptions
     *
     * @return int
     * @throws ClientResponseException
     * @throws MissingParameterException
     * @throws ServerResponseException
     */
    public function prune(DateTimeInterface $before, $keepExceptions): int
    {
        $params = [
            'index' => $this->telescopeIndex->index, // Replace with your actual index name
            'body'  => [
                'query' => [
                    'bool' => [
                        'must' => [
                            [
                                'range' => [
                                    'created_at' => [
                                        'lt' => (string)$before,
                                    ],
                                ],
                            ],
                            [
                                'match_all' => (object)[],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $response = $this->telescopeIndex->client->deleteByQuery($params);

        return $response['total'] ?? 0;
    }

    /**
     * Clear all the entries.
     *
     * @return void
     * @throws ClientResponseException
     * @throws MissingParameterException
     * @throws ServerResponseException
     */
    public function clear(): void
    {
        $this->telescopeIndex->client->indices()->delete(['index' => $this->telescopeIndex->index]);
    }

    /**
     * Perform any clean-up tasks needed after storing Telescope entries.
     *
     * @return void
     */
    public function terminate()
    {
        $this->monitoredTags = null;
    }
}