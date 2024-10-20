# Contributing

## Dev environment setup

1. Install and enable PDO driver for one or more of MySQL, PostgreSQL, or SQL Server.
2. Install dependencies: `composer install`

## Tests

From a console in the working directory, execute `composer test` to run all unit tests.

> [!NOTE]
> By default, database tests will attempt to run on a database named `PeachySQL`.
> To override connection settings, create a `LocalConfig.php` class in the `test/src`
> directory which extends `Config` and overrides the desired methods.

## Formatting and static analysis

* Run `composer cs-fix` to format code following PER Coding Style.
* Run `composer analyze` to detect type-related errors before runtime.
