# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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
[1.0.0]: https://github.com/ericsizemore/api/releases/tag/v1.1.0
[1.0.0]: https://github.com/ericsizemore/api/releases/tag/v1.0.0