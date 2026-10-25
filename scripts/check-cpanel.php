#!/usr/bin/env php
<?php

declare(strict_types=1);

// Run with the same PHP binary as the cPanel domain.
$required = ['ctype', 'curl', 'dom', 'fileinfo', 'filter', 'hash', 'mbstring', 'openssl', 'pcre', 'pdo', 'session', 'tokenizer', 'xml'];
$optional = ['pdo_mysql', 'pdo_pgsql', 'intl', 'gd', 'zip'];
$missing = array_values(array_filter($required, static fn (string $extension): bool => !extension_loaded($extension)));
$dbDrivers = array_values(array_filter(['pdo_mysql', 'pdo_pgsql'], 'extension_loaded'));

echo 'PHP version: '.PHP_VERSION.PHP_EOL;
echo 'PHP >= 8.3: '.(PHP_VERSION_ID >= 80300 ? 'OK' : 'FAIL').PHP_EOL;
echo 'Required extensions: '.(empty($missing) ? 'OK' : 'MISSING '.implode(', ', $missing)).PHP_EOL;
echo 'PDO driver: '.(count($dbDrivers) > 0 ? implode(', ', $dbDrivers) : 'MISSING (pdo_mysql or pdo_pgsql)').PHP_EOL;
echo 'Other available extensions: '.implode(', ', array_filter($optional, 'extension_loaded')).PHP_EOL;

$project = dirname(__DIR__);
foreach (['storage', 'bootstrap/cache'] as $dir) {
    $path = $project.DIRECTORY_SEPARATOR.$dir;
    echo sprintf('%s writable: %s%s', $dir, is_writable($path) ? 'YES' : 'NO', PHP_EOL);
}

echo PHP_EOL.'Document root MUST point to /public and .env MUST NOT be web-accessible.'.PHP_EOL;
echo 'This checks CLI PHP only. Verify the web PHP version and web server separately.'.PHP_EOL;
exit(PHP_VERSION_ID < 80300 || !empty($missing) || count($dbDrivers) === 0 ? 1 : 0);
