## CHANGELOG
A not so exhaustive list of changes for each release.

For a more detailed listing of changes between each version, 
you can use the following url: https://github.com/ericsizemore/api/compare/v1.0.0...v1.0.1. 

Simply replace the version numbers depending on which set of changes you wish to see.


### Unreleased

  * Updated header docblock for each file to be more compact.
  * Updated PHP-CS-Fixer configuration with new coding style rules; updated source files with these changes.
  * Updated `phpunit.xml` with the new 11.1 schema/options.
  * Replace `BeeCeptor` and `HTTPBinGo` with `Mocko`
    * Requires installing Mocko [standalone](https://mocko.dev/docs/getting-started/standalone/). Also see [the Unit Tests section of the README](README.md#unit-tests).
  * Replaces the composer section of the workflows with `ramsey/composer-install`.


### 1.0.1 (2024-02-06)

  * Added SECURITY.md
  * Updated the `retryDecider` and `retryDelay` functions.
    * Added new tests/modified existing for these changes.
    * Using [`BeeCeptor`](https://beeceptor.com) instead of [`HttpBin`](https://httpbin.org) for some tests.

### 1.0.0 (2024-02-06)

  * Initial release.