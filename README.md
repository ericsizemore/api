Esi\Api - A simple wrapper/builder using Guzzle for base API clients.
=====================================================================

[![Build Status](https://scrutinizer-ci.com/g/ericsizemore/api/badges/build.png?b=main)](https://scrutinizer-ci.com/g/ericsizemore/api/build-status/main)
[![Code Coverage](https://scrutinizer-ci.com/g/ericsizemore/api/badges/coverage.png?b=main)](https://scrutinizer-ci.com/g/ericsizemore/api/?branch=main)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/ericsizemore/api/badges/quality-score.png?b=main)](https://scrutinizer-ci.com/g/ericsizemore/api/?branch=main)
[![Continuous Integration](https://github.com/ericsizemore/api/actions/workflows/continuous-integration.yml/badge.svg?branch=main)](https://github.com/ericsizemore/api/actions/workflows/continuous-integration.yml)
[![Latest Stable Version](https://img.shields.io/packagist/v/esi/api.svg)](https://packagist.org/packages/esi/api)
[![Downloads per Month](https://img.shields.io/packagist/dm/esi/api.svg)](https://packagist.org/packages/esi/api)
[![License](https://img.shields.io/packagist/l/esi/api.svg)](https://packagist.org/packages/esi/api)

# Note

* This is the development branch (`2.x-dev`) for the next major release (`2.0.0`). The class api's/method signatures/etc. can, and will likely, change.
* It is not recommended to use this development branch in production.

## About

Documentation will be a bit lackluster, and the unit tests need a lot of work. With that being said, I created this library more for use in my own projects that center around an API service; to decouple a lot of the logic that would be repeated in each API service library, to its own library.

It has a long way to go, but it should be relatively stable.

## Features

* Builds around [`guzzle/guzzle`](https://github.com/guzzle/guzzle) as the HTTP Client.
* Cache's requests via [`Kevinrob/guzzle-cache-middleware`](https://github.com/Kevinrob/guzzle-cache-middleware).
* Can retry requets on a connection or server error via the Guzzle Retry Middleware.
  * `Client::enableRetryAttempts()` to instruct the client to attempt retries.
  * `Client::disableRetryAttempts()` to disable attempt retries.
  * `Client::setMaxRetryAttempts()` to set the maximum number of retries.
* Can pass along headers in `Client::build()` to be 'persistent' headers, i.e. headers sent with every request.
* Various methods to send a request, depending on HTTP method needed: `delete()`, `get()`, `post()`, and `put()`.

It currently does not support:
  * async requests and pooling; just your regular, good 'ol, standard requests.
  * `HEAD`, `OPTIONS`, `PATCH`, etc.


## Example
```php
use Esi\Api\Client;

/**
 * $options, array of:
 *
 * apiUrl           - URL to the API.
 * apiKey           - Your API Key.
 * cachePath        - The path to your cache on the filesystem.
 * apiRequiresQuery - True if the API requires the api key sent via query/query string, false otherwise.
 * apiParamName     - If apiRequiresQuery = true, then the param name for the api key. E.g.: api_key
 *
 */
$client = new Client([
    'apiUrl'           => 'https://myapiurl.com/api',
    'apiKey'           => 'myApiKey',
    'cachePath'        => '/var/tmp',
    'apiParamName'     => 'api_key',
    'apiRequiresQuery' => true,
]);
        
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

$response = $client->get('/', ['query' => ['foo' => 'bar']]);

// Decode the json and return as array
$data = $client->toArray($response);

// or... as an object
$data = $client->toObject($response);

// or... for the raw json response, to do with as you will
$data = $client->raw(); // or $response->getBody()->getContents()

```

### Requirements

- PHP 8.2.0 or above.

### Submitting bugs and feature requests

Bugs and feature requests are tracked on [GitHub](https://github.com/ericsizemore/api/issues)

Issues are the quickest way to report a bug. If you find a bug or documentation error, please check the following first:

* That there is not an Issue already open concerning the bug
* That the issue has not already been addressed (within closed Issues, for example)

### Contributing

See [CONTRIBUTING](CONTRIBUTING.md).

### Author

Eric Sizemore - <admin@secondversion.com> - <https://www.secondversion.com>

### License

Esi\Api is licensed under the MIT License - see the `LICENSE.md` file for details
