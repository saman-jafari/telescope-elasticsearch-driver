<?php

namespace SamanJafari\TelescopeElasticsearchDriver;

use SamanJafari\TelescopeElasticsearchDriver\ElasticsearchEntriesRepository;
use Illuminate\Support\ServiceProvider;
use Laravel\Telescope\Contracts\ClearableRepository;
use Laravel\Telescope\Contracts\EntriesRepository;
use Laravel\Telescope\Contracts\PrunableRepository;

class TelescopeElasticsearchDriverServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     */
    public function boot()
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/config.php' => config_path('telescope-elasticsearch-driver.php'),
            ], 'config');
        }
    }

    /**
     * Register the application services.
     */
    public function register()
    {
        // Automatically apply the package configuration
        $this->mergeConfigFrom(__DIR__.'/../config/config.php', 'telescope-elasticsearch-driver');
    }


    /**
     * Determine if we should register the bindings.
     *
     * @return bool
     */
    protected function usingElasticsearchDriver(): bool
    {
        return config('telescope.driver') === 'elasticsearch';
    }

    /**
     * Register elasticsearch storage driver.
     *
     * @return void
     */
    protected function registerStorageDriver(): void
    {
        $this->app->singleton(EntriesRepository::class, ElasticsearchEntriesRepository::class);
        $this->app->singleton(ClearableRepository::class, ElasticsearchEntriesRepository::class);
        $this->app->singleton(PrunableRepository::class, ElasticsearchEntriesRepository::class);
    }
}
