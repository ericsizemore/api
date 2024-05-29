# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

  * Added two new runtime dependencies:
    * symfony/options-resolver
    * symfony/validator
  * Added new property `$clientOptions`
  * Added new methods `configureClientOptions` and `createBuildOptionsResolver`.
  * Added new method `normalizeRequestOptions`.
  * Added new methods `delete`, `get`, `post`, and `put`.

### Changed

  * With the addition of `symfony/options-resolver`:
    * The constructor now only takes one parameter, `$options`, an array with possible keys of:
      * apiUrl           - URL to the API. **required**
      * apiKey           - Your API Key. **required**
      * cachePath        - The path to your cache on the filesystem.
      * apiRequiresQuery - True if the API requires the api key sent via query/query string, false otherwise.
      * apiParamName     - If apiRequiresQuery = true, then the param name for the api key. E.g.: api_key
  * Exceptions of the `Symfony\Component\OptionsResolver\Exception\ExceptionInterface` interface will now be thrown, instead of `InvalidArgumentException`, in cases where an invalid option is passed.
  * The `send` method is no longer public, it is now protected.
    * Its purpose now is to check that the `client` property is an instance of `GuzzleHttp\Client`, and
    * To send the request with given method, endpoint, and options based on which `method` method (get, post, put, delete) is called.
    * `normalizeEndpoint` method moved from `Utils` back to `Client` with the removal of the `Utils` class.

### Removed

  * Removed the `buildAndSend` function.
  * Removed class properties:
    * $apiKey
    * $apiParamName
    * $apiUrl
    * $cachePath
  * Removed `Utils` class.


## [1.1.0] - 2024-05-22

### Added

  * Added issue and pull request templates.
  * Added `backward-compatibility.md` - a backward compatibility promise.
  * Added a Contributor Code of Conduct, CODE_OF_CONDUCT.md.
  * Added a CONTRIBUTING file with information for contributing to the project.
  * Added SECURITY.md

### Changed

  * Updated header docblock for each file to be more compact.
  * Updated PHP-CS-Fixer configuration with new coding style rules; updated source files with these changes.
  * Updated `phpunit.xml` with the new 11.1 schema/options.
  * Replace `BeeCeptor` and `HTTPBinGo` with `Mocko`
    * Requires installing Mocko [standalone](https://mocko.dev/docs/getting-started/standalone/). Also see [the Unit Tests section of the README](README.md#unit-tests).
  * Replaces the composer section of the workflows with `ramsey/composer-install`.
  * Updated the `retryDecider` and `retryDelay` functions.
    * Added new tests/modified existing for these changes.


## [1.0.0] - 2024-02-06

  * Initial release.

[unreleased]: https://github.com/ericsizemore/api/tree/main
[1.1.0]: https://github.com/ericsizemore/api/releases/tag/v1.1.0
[1.0.0]: https://github.com/ericsizemore/api/releases/tag/v1.0.0