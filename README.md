Esi\Api - A simple wrapper/builder using Guzzle for base API clients.
=====================================================================

[![Build Status](https://scrutinizer-ci.com/g/ericsizemore/api/badges/build.png?b=main)](https://scrutinizer-ci.com/g/ericsizemore/api/build-status/main)
[![Code Coverage](https://scrutinizer-ci.com/g/ericsizemore/api/badges/coverage.png?b=main)](https://scrutinizer-ci.com/g/ericsizemore/api/?branch=main)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/ericsizemore/api/badges/quality-score.png?b=main)](https://scrutinizer-ci.com/g/ericsizemore/api/?branch=main)
[![Tests](https://github.com/ericsizemore/api/actions/workflows/tests.yml/badge.svg)](https://github.com/ericsizemore/api/actions/workflows/tests.yml)
[![PHPStan](https://github.com/ericsizemore/api/actions/workflows/main.yml/badge.svg)](https://github.com/ericsizemore/api/actions/workflows/main.yml)

[![Latest Stable Version](https://img.shields.io/packagist/v/esi/api.svg)](https://packagist.org/packages/esi/api)
[![Downloads per Month](https://img.shields.io/packagist/dm/esi/api.svg)](https://packagist.org/packages/esi/api)
[![License](https://img.shields.io/packagist/l/esi/api.svg)](https://packagist.org/packages/esi/api)


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
* One function that handles sending a request: `Client::send()`

It currently does not support async requests and pooling. Just your regular, good 'ol, standard requests.

## Example
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

### Submitting bugs and feature requests

Bugs and feature requests are tracked on [GitHub](https://github.com/ericsizemore/api/issues)

Issues are the quickest way to report a bug. If you find a bug or documentation error, please check the following first:

* That there is not an Issue already open concerning the bug
* That the issue has not already been addressed (within closed Issues, for example)

### Contributing

Esi\Api accepts contributions of code and documentation from the community. 
These contributions can be made in the form of Issues or [Pull Requests](http://help.github.com/send-pull-requests/) on the [Esi\Api repository](https://github.com/ericsizemore/api).

Esi\Api is licensed under the MIT license. When submitting new features or patches to Esi\Api, you are giving permission to license those features or patches under the MIT license.

Esi\Api tries to adhere to PHPStan level 9 with strict rules and bleeding edge. Please ensure any contributions do as well.

#### Guidelines

Before we look into how, here are the guidelines. If your Pull Requests fail to pass these guidelines it will be declined, and you will need to re-submit when you’ve made the changes. This might sound a bit tough, but it is required for me to maintain quality of the code-base.

#### PHP Style

Please ensure all new contributions match the [PSR-12](https://www.php-fig.org/psr/psr-12/) coding style guide. The project is not fully PSR-12 compatible, yet; however, to ensure the easiest transition to the coding guidelines, I would like to go ahead and request that any contributions follow them.

#### Documentation

If you change anything that requires a change to documentation then you will need to add it. New methods, parameters, changing default values, adding constants, etc. are all things that will require a change to documentation. The change-log must also be updated for every change. Also, PHPDoc blocks must be maintained.

##### Documenting functions/variables (PHPDoc)

Please ensure all new contributions adhere to:
  * [PSR-5 PHPDoc](https://github.com/php-fig/fig-standards/blob/master/proposed/phpdoc.md)
  * [PSR-19 PHPDoc Tags](https://github.com/php-fig/fig-standards/blob/master/proposed/phpdoc-tags.md)

when documenting new functions, or changing existing documentation.

#### Branching

One thing at a time: A pull request should only contain one change. That does not mean only one commit, but one change - however many commits it took. The reason for this is that if you change X and Y but send a pull request for both at the same time, we might really want X but disagree with Y, meaning we cannot merge the request. Using the Git-Flow branching model you can create new branches for both of these features and send two requests.

#### Unit Tests

Unit tests are handled with PHPUnit. The tests make use of a mock server to run against, which requires [mocko/cli](https://mocko.dev/docs/getting-started/standalone/). To run the tests:

1. Install [Node.js](https://nodejs.org/en/learn/getting-started/how-to-install-nodejs).
2. Install `mocko/cli`
```bash
$ npm i -g @mocko/cli
```
3. Install `esi/api` with [Composer](https://getcomposer.org/doc/00-intro.md), with dev dependencies
```bash
$ composer install esi/api
```
4. Start the `mocko` server
```bash
$ mocko --watch ./mock-server
```
5. Run the unit tests
```bash
$ composer run-script test
```

### Author

Eric Sizemore - <admin@secondversion.com> - <https://www.secondversion.com>

### License

Esi\Api is licensed under the MIT License - see the `LICENSE.md` file for details
