# Enables you to use Elasticsearch for telescope so it does NOT use relational database

[![Latest Version on Packagist](https://img.shields.io/packagist/v/saman-jafari/telescope-elasticsearch-driver.svg?style=flat-square)](https://packagist.org/packages/saman-jafari/telescope-elasticsearch-driver)
[![Total Downloads](https://img.shields.io/packagist/dt/saman-jafari/telescope-elasticsearch-driver.svg?style=flat-square)](https://packagist.org/packages/saman-jafari/telescope-elasticsearch-driver)

it will allow you to switch from sql database to elasticsearch as driver for your data storage and it will eliminate the deadlock so it makes telescope a ready for production logging system.
## Prerequisite

- elasticsearch
- telescope 5.0 or higher

## Installation

You can install the package via composer:

```bash
composer require saman-jafari/telescope-elasticsearch-driver
```

## Usage

first set driver of telescope to elasticsearch
```dotenv
TELESCOPE_DRIVER=elasticsearch
``` 
then you need to set elasticsearch related config in your .env file

example:
```dotenv
TELESCOPE_ELASTICSEARCH_HOST=https://elasticsearch:9200/
TELESCOPE_ELASTICSEARCH_USERNAME=elastic
TELESCOPE_ELASTICSEARCH_PASSWORD=changeme
TELESCOPE_ELASTICSEARCH_INDEX=telescope_index
``` 

if you want you can publish the config file too

```php
php artisan vendor:publish --tag=telescope-elasticsearch-driver-config
```

### Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

### Security

If you discover any security related issues, please email isaimon@me.com instead of using the issue tracker.

## Credits

-   [Saman Jafari](https://github.com/saman-jafari)
-   [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

## Laravel Package Boilerplate

This package was generated using the [Laravel Package Boilerplate](https://laravelpackageboilerplate.com).
