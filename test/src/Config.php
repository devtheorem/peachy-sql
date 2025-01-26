<?php

namespace DevTheorem\PeachySQL\Test\src;

class Config
{
    public string $mssqlServer = '(local)\SQLEXPRESS';
    public string $mssqlUsername = '';
    public string $mssqlPassword = '';

    public string $mysqlDsn = 'mysql:host=127.0.0.1;port=3306;dbname=PeachySQL';
    public string $mysqlUser = 'root';
    public string $mysqlPassword = '';

    public string $pgsqlUser = 'postgres';
    public string $pgsqlPassword = 'postgres';

    public function getPgsqlDsn(string $database): string
    {
        return "pgsql:host=localhost;dbname=$database";
    }
}
