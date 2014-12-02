# PeachySQL

[![Packagist Version](https://img.shields.io/packagist/v/theodorejb/peachy-sql.svg)](https://packagist.org/packages/theodorejb/peachy-sql) [![Total Downloads](https://img.shields.io/packagist/dt/theodorejb/peachy-sql.svg)](https://packagist.org/packages/theodorejb/peachy-sql) [![License](https://img.shields.io/packagist/l/theodorejb/peachy-sql.svg)](https://packagist.org/packages/theodorejb/peachy-sql) [![Build Status](https://travis-ci.org/theodorejb/peachy-sql.svg?branch=master)](https://travis-ci.org/theodorejb/peachy-sql)

PeachySQL is a speedy database abstraction layer with a goal of simplifying the
experience of performing common SQL queries and building JSON APIs in PHP.
It supports both MySQL (via MySQLi) and SQL Server (via Microsoft's
[SQLSRV extension](http://www.php.net/manual/en/book.sqlsrv.php)) and runs on PHP 5.4+.

## Installation

To install via [Composer](https://getcomposer.org/), add the following to the
composer.json file in your project root:

```json
{
    "require": {
        "theodorejb/peachy-sql": "3.x"
    }
}
```

Then run `composer install` and require `vendor/autoload.php` in your
application's bootstrap file.

## Usage

Start by instantiating the `Mysql` or `SqlServer` class with a database connection,
which should be an existing [mysqli object](http://www.php.net/manual/en/mysqli.construct.php)
or [SQLSRV connection resource](http://www.php.net/manual/en/function.sqlsrv-connect.php):

```php
$peachySql = new PeachySQL\Mysql($mysqlConn);
```
or
```php
$peachySql = new PeachySQL\SqlServer($sqlSrvConn);
```

After instantiation, arbitrary queries can be executed by passing a
SQL string and array of bound parameters to the `query()` method:

```php
$sql = 'SELECT * FROM Users WHERE fname LIKE ? AND lname LIKE ?';
$result = $peachySql->query($sql, ['theo%', 'b%']);
echo json_encode($result->getAll());
```

Because PeachySQL always returns selected rows as an associative array,
it is easy to make changes to the data structure and output it as JSON.

In addition to `getAll()`, the `SQLResult` object returned by `query()` has
`getFirst()`, `getAffected()`, and `getQuery()` methods (to return the first
selected row, the number of affected rows, and the executed query string,
respectively). If using MySQL, `query()` will return an extended `MySQLResult`
object which adds a `getInsertId()` method.

### Shorthand methods

When creating a new instance of PeachySQL, an array of options can be passed
to the constructor specifying a table name and list of valid columns:

```php
$options = [
    'table'   => 'Users',
	'columns' => ['user_id', 'fname', 'lname']
];

$userTable = new PeachySQL\MySQL($mysqlConn, $options);
```

If using SQL Server, an additional option can be passed to specify the table's identity column.
This is necessary so that PeachySQL can generate an output clause to retrieve insert IDs.

```php
$userTable = new PeachySQL\SqlServer($sqlSrvConn, [
    'table'   => 'Users',
    'columns' => ['user_id', 'fname', 'lname'],
    'idCol' => 'user_id'
]);
```

You can then make use of PeachySQL's five shorthand methods: `select()`,
`insertOne()`, `insertBulk()`, `update()`, and `delete()`. To prevent SQL
injection, the queries PeachySQL generates for these methods always use bound
parameters for values, and column names are checked against the list of valid
columns in the options array.

```php
// select first and last name columns where user_id is equal to 5
$rows = $userTable->select(['fname', 'lname'], ['user_id' => 5]);

// insert a single row from an associative array
$userData = ['fname' => 'Donald', 'lname' => 'Chamberlin'];
$id = $userTable->insertOne($userData)->getId();

// bulk-insert 3 rows into the Users table
$userData = [
    [
        'fname' => 'Theodore',
        'lname' => 'Brown'
    ],
    [
        'fname' => 'Grace',
        'lname' => 'Hopper'
    ],
    [
        'fname' => 'Douglas',
        'lname' => 'Engelbart'
    ]
];

// $ids will contain an array of the values inserted
// into the auto-incremented user_id column.
$ids = $userTable->insertBulk($userData)->getIds();

// update the user with user_id 4
$newData = ['fname' => 'Raymond', 'lname' => 'Boyce'];
$userTable->update($newData, ['user_id' => 4]);

// delete users with IDs 1, 2, and 3
$userTable->delete(['user_id' => [1, 2, 3]]);
```

### Transactions

Call the `begin()` method to start a transaction.
You can then call `query()` and any of the shorthand methods as needed,
before committing or rolling back the transaction with `commit()` or `rollback()`.

### Arbitrarily large bulk inserts

SQL Server allows a maximum of 1,000 rows to be inserted at a time,
and limits individual queries to 2,099 or fewer bound parameters.
MySQL supports a maximum of 65,536 bound parameters per query.
These limits can be easily reached when attempting to bulk-insert hundreds
or thousands of rows at a time. To avoid these limits, PeachySQL automatically
splits large bulk insert queries into batches to efficiently handle any number
of rows. The default limits (listed above) can be customized via the
"maxBoundParams" and "maxInsertRows" options.

### Other methods and options

The database connection can be swapped out at any time with `setConnection()`,
and `setOptions()` and `getOptions()` methods allow PeachySQL options to be
changed and retrieved at will.

In addition to the previously mentioned options, there is a MySQL-specific
"autoIncrementIncrement" option which can be used to set the interval between
successive auto-incremented values in the table (defaults to 1). This option is
used to determine the array of insert IDs for bulk-inserts, since MySQL only
provides the first insert ID.

## Author

Theodore Brown  
<http://theodorejb.me>

## License

MIT
