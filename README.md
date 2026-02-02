Esi\Api - A simple wrapper/builder using Guzzle for base API clients.
=====================================================================

[![Build Status](https://scrutinizer-ci.com/g/ericsizemore/api/badges/build.png?b=main)](https://scrutinizer-ci.com/g/ericsizemore/api/build-status/main)
[![Code Coverage](https://scrutinizer-ci.com/g/ericsizemore/api/badges/coverage.png?b=main)](https://scrutinizer-ci.com/g/ericsizemore/api/?branch=main)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/ericsizemore/api/badges/quality-score.png?b=main)](https://scrutinizer-ci.com/g/ericsizemore/api/?branch=main)
[![Continuous Integration](https://github.com/ericsizemore/api/actions/workflows/continuous-integration.yml/badge.svg?branch=main)](https://github.com/ericsizemore/api/actions/workflows/continuous-integration.yml)
[![Latest Stable Version](https://img.shields.io/packagist/v/esi/api.svg)](https://packagist.org/packages/esi/api)
[![Downloads per Month](https://img.shields.io/packagist/dm/esi/api.svg)](https://packagist.org/packages/esi/api)
[![License](https://img.shields.io/packagist/l/esi/api.svg)](https://packagist.org/packages/esi/api)

`esi/api` is a simple wrapper/builder using Guzzle for base API clients.


> [!NOTE]
> Documentation will be a bit lackluster, and the unit tests need a lot of work. With that being said, I created this 
> library more for use in my own projects that center around an API service; to decouple a lot of the logic that would 
> be repeated in each API service library, to its own library.
> It has a long way to go, but it should be relatively stable.

## Installation

You can install the package via composer:

``` bash
$ composer require ericsizemore/api
```

## Features

* Builds around [`guzzle/guzzle`](https://github.com/guzzle/guzzle) as the HTTP Client.
* Cache's requests via [`Kevinrob/guzzle-cache-middleware`](https://github.com/Kevinrob/guzzle-cache-middleware).
* Can retry requets on a connection or server error via the Guzzle Retry Middleware.
  * `Client::enableRetryAttempts()` to instruct the client to attempt retries.
  * `Client::disableRetryAttempts()` to disable attempt retries.
  * `Client::setMaxRetryAttempts()` to set the maximum number of retries.
* Can pass along headers in `Client::build()` to be 'persistent' headers, i.e. headers sent with every request.
* One function that handles sending a request: `Client::send()`

It currently does not support async requests and pooling. Just your regular, good 'ol, standard requests.

## Usage
```php
use Esi\Api\Client;

// api url, api key, cache path, does the api require key sent as a query arg, the name of the query arg
$client = new Client('https://myapiurl.com/api', 'myApiKey', '/var/tmp', true, 'api_key');

// Must first 'build' the client with (optional) $options array which can include any valid Guzzle option.
$client->build([
    'persistentHeaders' => [
        'Accept' => 'application/json',
    ],
    'allow_redirects' => true,
    // ... etc.
]);
$client->enableRetryAttempts();
$client->setMaxRetryAttempts(5);

$response = $client->send('GET', '/', ['query' => ['foo' => 'bar']]);

// Decode the json and return as array
$data = $client->toArray($response);

// or... as an object
$data = $client->toObject($response);

// or... for the raw json response, to do with as you will
$data = $client->raw(); // or $response->getBody()->getContents()

```

### Requirements

- PHP 8.2.0 or above.

### Credits

- [Eric Sizemore](https://github.com/ericsizemore)
- [All Contributors](https://github.com/ericsizemore/api/contributors)

### Contributing

See [CONTRIBUTING](CONTRIBUTING.md).

Bugs and feature requests are tracked on [GitHub](https://github.com/ericsizemore/api/issues).

### Contributor Covenant Code of Conduct

See [CODE_OF_CONDUCT.md](./CODE_OF_CONDUCT.md)

### Backward Compatibility Promise

See [backward-compatibility.md](./backward-compatibility.md) for more information on Backwards Compatibility.

### Changelog

See the [CHANGELOG](./CHANGELOG.md) for more information on what has changed recently.

### License

See the [LICENSE](./LICENSE.md) for more information on the license that applies to this project.

### Security

See [SECURITY](./SECURITY.md) for more information on the security disclosure process.
