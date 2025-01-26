# Changelog

## [7.0.1] - 2025-01-26
### Changed
- Improved some type annotations and simplified internal test configuration.
- Switched from Psalm to PHPStan for static analysis.

### Fixed
- `floatSelectedAsString` option is now `false` for PostgreSQL on PHP 8.4.


## [7.0.0] - 2024-10-29
### Added
- Official support for PostgreSQL.
- It is now possible to bind and set binary column values in a prepared statement which is executed multiple times.

### Changed
- Rewrote library using PDO instead of driver-specific connection objects.
Rather than instantiating a `Mysql` or `SqlServer` subclass, simply construct
`PeachySql` with the PDO object for your connection.
- Moved to `DevTheorem` namespace.

> [!IMPORTANT]
> If using SQL Server, make sure your PDO connection has the `PDO::SQLSRV_ATTR_FETCHES_NUMERIC_TYPE`
> option set to `true`, so column values are returned with the same native types as before.

- When using MySQL, the `Statement` object no longer has a `getInsertId()` method.
This does not affect shorthand insert methods, however, which provide the insert IDs just like before.
- The `getAffected()` method on a select query result now returns the number of selected rows instead of `-1` for MySQL.
- PHP 8.1+ is now required.

### Removed
- All previously deprecated methods.
- Unnecessary `$length` parameter from `makeBinaryParam()`.
- `SqlException` no longer has properties for the failed query and params.


## [6.3.1] - 2024-10-13
### Changed
- Improved `makeBinaryParam()` implementation for SQL Server.

### Fixed
- Maximum number of MySQL bound parameters.

### Deprecated
- Unnecessary `Options` getters and setters.


## [6.3.0] - 2024-10-05
### Changed
- Use `mysqli_stmt::get_result()` instead of `bind_result()` internally
to slightly improve performance and reduce memory usage.

### Deprecated
- Unnecessary getter methods (in `InsertResult`, `BulkInsertResult`, `SqlParams`, and `SqlException`).


## [6.2.0] - 2022-03-14
### Added
- Support for empty bulk insert queries (previously an error was thrown).


## [6.1.0] - 2021-12-15
### Added
- Shorthand `select()` method which takes a `SqlParams` object to allow
bound params in the base select query.
- Support for MySQL exception error handling mode (default in PHP 8.1).


## [6.0.3] - 2021-08-06
### Changed
- Improved type declarations for static analysis.


## [6.0.2] - 2021-02-11
### Changed
- Specified additional types and enabled Psalm static analysis.
- PHP 7.4+ is now required.


## [6.0.1] - 2019-08-05
### Changed
- Implemented missing return type declarations.
- Excluded additional test files from production bundle.


## [6.0.0] Deprecation Elimination - 2019-01-16
### Added
- Scaler and return type declarations.

### Removed
- Support for HHVM as well as PHP versions prior to 7.1.
- Unnecessary `$maximum` parameter from `Selector::offset()` method.
- All previously-deprecated methods and options.


## [5.5.1] Differentiated Bit - 2017-11-09
### Added
- Support for using `makeBinaryParam()` with nullable columns
(issue [#5](https://github.com/devtheorem/peachy-sql/issues/5)).


## [5.5.0] Null Appreciation - 2017-10-19
### Added
- New `nu` and `nn` shorthand operators to filter where a column is or is not null.

### Deprecated
- Ability to use null values with `eq` and `ne` operators.


## [5.4.0] Boolean Affectation - 2017-03-08
### Added
- `makeBinaryParam()` method.

### Fixed
- `Statement::getAffected()` method now consistently returns -1 when no affected count is available.
- "Incorrect integer value" MySQL error when binding a false value.


## [5.3.1] Deprecation Proclamation - 2017-01-31
### Changed
- Updated readme to document `offset()` method instead of deprecated `paginate()` method.

### Deprecated
- Unnecessary option getter/setter methods (`setTable()`, `getTable()`,
`setAutoIncrementValue()`, `getAutoIncrementValue()`, `setIdColumn()`, `getIdColumn()`).


## [5.3.0] Descending Increase - 2016-11-04
### Added
- `Selector::offset()` method to enable setting an offset that isn't a multiple of the page size.

### Deprecated
- `Selector::paginate()` method.


## [5.2.3] Protracted Refinement - 2016-08-28
### Added
- Support for generating filters with IS NOT NULL and multiple LIKE operators.


## [5.2.2] Chainable Reparation - 2016-08-25
### Changed
- Updated dependencies and removed unused code.

### Fixed
- Return type of `Selector` functions to support subclass autocompletion.


## [5.2.1] Simple Safety - 2016-07-21
### Changed
- An exception is now thrown when attempting to use pagination without sorting rows.
- Qualified column identifiers are now automatically escaped. As a
consequence, column names containing periods are no longer supported.


## [5.2.0] Intermediate Injection - 2016-07-07
### Added
- `selectFrom()`, `insertRow()`, `insertRows()`, `updateRows()`, and `deleteFrom()`
methods which can be passed a table name rather than depending on options
passed to the PeachySQL constructor. This simplifies dependency injection
and also removes the need for implementation-specific options. The new
`selectFrom()` method also supports pagination and more complex sorting/filtering.

### Deprecated
- Old shorthand methods (`select()`, `insertBulk()`, `insertOne()`, `update()`, and `delete()`)


## [5.1.0] Futuristic Resourcefulness - 2016-04-15
### Changed
- `InsertResult::getId()` now throws an exception if no ID is available.
- Minor code cleanup.

### Fixed
- Compatibility issue with PHP 7 SQL Server driver (see
[Microsoft/msphpsql#84](https://github.com/Microsoft/msphpsql/issues/84)).

### Removed
- HHVM generator compatibility hack (no longer necessary as of HHVM v3.11).


## [5.0.0] Escaping Execution - 2015-09-16
### Added
- `prepare()` method which binds parameters and returns a `Statement`
object. This object has an `execute()` method which makes it possible
to run the prepared query multiple times with different values.

### Changed
- Column names are now automatically escaped, so shorthand methods can
be used without having to specify a list of valid columns.
- Rather than passing options to the PeachySQL constructor as an associative array,
an `Options` subclass should be passed instead. This object has setters and getters
for each setting, which improves discoverability and refactoring.

### Removed
- `setConnection()` and `setOptions()` methods. Options can still be
dynamically changed by calling `getOptions()->setterMethod()`.

### Fixed
- Bug where MySQL insert IDs weren't calculated correctly if the
increment value was altered.


## [4.0.2] Preparatory Fixture - 2015-05-11
### Fixed
- Missing error info for MySQL prepared statement failures
(issue [#4](https://github.com/devtheorem/peachy-sql/issues/4)).

### Removed
- Unnecessary `SqlResult::getQuery()` method.


## [4.0.1] Stalwart Sparkle - 2015-02-08
### Changed
- Unused code cleanup
- Documentation improvements


## [4.0.0] Economical Alternator - 2015-02-06
### Added
- `SqlResult::getIterator()` method which returns a `Generator`, making it possible to iterate over
very large result sets without running into memory limitations.
- Optional third parameter on `select()` method which accepts an array of
column names to sort by in ascending order.
- `SqlException::getSqlState()` method which returns the standard SQLSTATE code for the failure.

### Changed
- `SqlException::getMessage()` now includes the SQL error message for the failed query.
- `SqlException::getCode()` now returns the MySQL or SQL Server error code.

### Removed
- PHP 5.4 support (5.5+ is now required - recent versions of HHVM should also work if using MySQL).
- Deprecated `insert()` and `insertAssoc()` methods.
- Deprecated `TSQL` class.
- Ability to call `SqlResult::getFirst()` and `SqlResult::getAll()` multiple
times for a given result (since rows are no longer cached in the object).


## [3.0.1] Uniform Optimization - 2014-12-06
### Changed
- Improved documentation consistency.
- Minor code cleanup and performance tweaks.


## [3.0.0] Hyperactive Lightyear - 2014-12-02
### Added
- `insertOne()` and `insertBulk()` methods, which accept an associative array of
columns/values and return `InsertResult` and `BulkInsertResult` objects, respectively.
- It is now possible to bulk-insert an arbitrarily large set of rows.
PeachySQL will automatically batch large inserts to remove limitations
on the maximum number of bound parameters and rows per query.

### Changed

The following classes have been renamed to improve API consistency:
- `PeachySQL` is now `PeachySql`
- `MySQL` is now `Mysql`
- `SQLException` is now `SqlException`
- `SQLResult` is now `SqlResult`
- `MySQLResult` is now `MysqlResult`

Since class and function names in PHP are case-insensitive (as are file names on some platforms),
these renames do not necessarily break backwards compatibility. However, existing references should
still be updated to avoid confusion.

### Deprecated
- `insertAssoc()` method - use `insertOne()` instead.
- `insert()` method - use `insertBulk()` instead.
- `TSQL` class - use `SqlServer` instead.

### Removed
- Previously deprecated `SqlResult::getRows()` method.
- Optional callback parameters from `query()` and shorthand methods.


## [2.1.0] Progressive Substitution - 2014-08-03
### Added
- `SQLResult::getFirst()` method for retrieving the first selected row.
- `SQLResult::getAll()` method for retrieving all selected rows.

### Deprecated
- `SQLResult::getRows()` is now a deprecated alias of `SQLResult::getAll()`.


## [2.0.0] Peachy Sequel - 2014-07-29
### Added
- `begin()`, `commit()`, and `rollback()` methods to support transactions.
- `insertAssoc()` method to easily insert a single row from an associative array.
- `setConnection()` method to change the database connection after instantiation.
- Option to override the default auto increment value for MySQL.
- Custom `PeachySQL\SQLException` thrown for query errors, with methods
to retrieve the error array, SQL query string, and bound parameters.
- Contributing instructions.

### Changed
- The library is now namespaced under `PeachySQL`.
- Callbacks for shorthand methods are now optional. If no callback is specified, the methods will
return sensible defaults (e.g. `select()` returns selected rows, `insert()` returns insert IDs,
and `update()` and `delete()` return the number of affected rows).
- A list of valid columns must now be passed to the options array to
generate queries which reference a column. This allows queries to be
generated from user data without the potential for SQL injection attacks.
- Callbacks are now passed a `SQLResult` object, rather than separate
arguments for selected and affected rows.
- Table name and identity column options are now passed to the constructor as an associative array.
- If a flat array of values is passed to `insert()`, the insert ID will
now be returned as an integer instead of an array.
- Updated code to follow the [PSR-2 coding style guide](http://www.php-fig.org/psr/psr-2/).

### Removed
- `$dbType` argument from constructor (use `new PeachySQL\MySQL($conn)`
or `new PeachySQL\TSQL($conn)` instead).
- `getTableName()` and `setTableName()` methods (replaced with `getOptions()` and `setOptions()`).
- `$idCol` parameter from `insert()` method (specify via options array instead if using SQL Server). 
- `splitRows()` method (not core to PeachySQL's goal). The same functionality is
available in the [ArrayUtils library](https://github.com/theodorejb/array-utils).

## Fixed
- Potential error when inserting into a MySQL table without an auto-incremented column.
- Errors are now thrown if required table/column names aren't specified.


## [1.1.1] - 2014-04-20
### Fixed
- Bug where additional (non-existent) insert IDs were returned when
inserting a single row into a MySQL table with a flat array.

### Removed
- Unnecessary usage of variable variables.


## [1.1.0] - 2014-04-11
### Changed
- The `query()`, `select()`, `insert()`, `update()`, and `delete()` methods now
return the value of their callback function, making it easier to use data outside the callback.
- A flat array of values can now be passed to the `insert()` method to insert a single row.


## [1.0.1] - 2014-03-28
### Changed
- Simplified `splitRows()` example in readme.
- Short array syntax is now used consistently.
- Minor unit test improvements.


## [1.0.0] - 2014-02-20
- Initial release


[7.0.1]: https://github.com/devtheorem/peachy-sql/compare/v7.0.0...v7.0.1
[7.0.0]: https://github.com/devtheorem/peachy-sql/compare/v6.3.1...v7.0.0
[6.3.1]: https://github.com/devtheorem/peachy-sql/compare/v6.3.0...v6.3.1
[6.3.0]: https://github.com/devtheorem/peachy-sql/compare/v6.2.0...v6.3.0
[6.2.0]: https://github.com/devtheorem/peachy-sql/compare/v6.1.0...v6.2.0
[6.1.0]: https://github.com/devtheorem/peachy-sql/compare/v6.0.3...v6.1.0
[6.0.3]: https://github.com/devtheorem/peachy-sql/compare/v6.0.2...v6.0.3
[6.0.2]: https://github.com/devtheorem/peachy-sql/compare/v6.0.1...v6.0.2
[6.0.1]: https://github.com/devtheorem/peachy-sql/compare/v6.0.0...v6.0.1
[6.0.0]: https://github.com/devtheorem/peachy-sql/compare/v5.5.1...v6.0.0
[5.5.1]: https://github.com/devtheorem/peachy-sql/compare/v5.5.0...v5.5.1
[5.5.0]: https://github.com/devtheorem/peachy-sql/compare/v5.4.0...v5.5.0
[5.4.0]: https://github.com/devtheorem/peachy-sql/compare/v5.3.1...v5.4.0
[5.3.1]: https://github.com/devtheorem/peachy-sql/compare/v5.3.0...v5.3.1
[5.3.0]: https://github.com/devtheorem/peachy-sql/compare/v5.2.3...v5.3.0
[5.2.3]: https://github.com/devtheorem/peachy-sql/compare/v5.2.2...v5.2.3
[5.2.2]: https://github.com/devtheorem/peachy-sql/compare/v5.2.1...v5.2.2
[5.2.1]: https://github.com/devtheorem/peachy-sql/compare/v5.2.0...v5.2.1
[5.2.0]: https://github.com/devtheorem/peachy-sql/compare/v5.1.0...v5.2.0
[5.1.0]: https://github.com/devtheorem/peachy-sql/compare/v5.0.0...v5.1.0
[5.0.0]: https://github.com/devtheorem/peachy-sql/compare/v4.0.2...v5.0.0
[4.0.2]: https://github.com/devtheorem/peachy-sql/compare/v4.0.1...v4.0.2
[4.0.1]: https://github.com/devtheorem/peachy-sql/compare/v4.0.0...v4.0.1
[4.0.0]: https://github.com/devtheorem/peachy-sql/compare/v3.0.1...v4.0.0
[3.0.1]: https://github.com/devtheorem/peachy-sql/compare/v3.0.0...v3.0.1
[3.0.0]: https://github.com/devtheorem/peachy-sql/compare/v2.1.0...v3.0.0
[2.1.0]: https://github.com/devtheorem/peachy-sql/compare/v2.0.0...v2.1.0
[2.0.0]: https://github.com/devtheorem/peachy-sql/compare/v1.1.1...v2.0.0
[1.1.1]: https://github.com/devtheorem/peachy-sql/compare/v1.1.0...v1.1.1
[1.1.0]: https://github.com/devtheorem/peachy-sql/compare/v1.0.1...v1.1.0
[1.0.1]: https://github.com/devtheorem/peachy-sql/compare/v1.0.0...v1.0.1
[1.0.0]: https://github.com/devtheorem/peachy-sql/tree/v1.0.0
