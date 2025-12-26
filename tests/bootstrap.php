<?php

use DevTheorem\PeachySQL\Test\src\{App, Config};

require 'vendor/autoload.php';

$configFile = __DIR__ . '/config.php';

if (file_exists($configFile)) {
    $config = require $configFile;

    if (!$config instanceof Config) {
        throw new Exception('Expected config file to return Config instance');
    }

    App::$config = $config;
} else {
    App::$config = new Config();
}
