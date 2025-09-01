<?php

date_default_timezone_set('UTC');
error_reporting(E_ALL);

$host = $argv[1] ?? 'db'; // Get host name from test.sh
$user = 'example';
$pass = 'example';
$expected_double = '-2.2250738585072014e-308';
$ret = 0;

print "PHP version is ". phpversion() . PHP_EOL;
print "PDO check: double field" . PHP_EOL . PHP_EOL;

$pdoOptions = [
    \PDO::ATTR_PERSISTENT => true,
    \PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    \PDO::ATTR_STRINGIFY_FETCHES => true,
];

// Backward/forward compatibility for buffered query attribute across PHP 8.3–8.5
if (class_exists('Pdo\\Mysql') && defined('Pdo\\Mysql::ATTR_USE_BUFFERED_QUERY')) {
    $pdoOptions[constant('Pdo\\Mysql::ATTR_USE_BUFFERED_QUERY')] = false;
} elseif (defined('PDO::MYSQL_ATTR_USE_BUFFERED_QUERY')) {
    $pdoOptions[constant('PDO::MYSQL_ATTR_USE_BUFFERED_QUERY')] = false;
}

$db = new \PDO("mysql:host=$host;dbname=test001", $user, $pass, $pdoOptions);

$q = $db->query('SELECT * FROM test000');
$q->setFetchMode(\PDO::FETCH_ASSOC);

foreach ($q as $result) {
    if ($result['col15'] === $expected_double) {
        echo "Success: Double value is the expected!" . PHP_EOL;
        $ret = 0;
    } else {
        echo "Fail: double value is not expected..." . PHP_EOL;
        echo "Expected: " . $expected_double . PHP_EOL;
        echo "Actual:   " . $result['col15'] . PHP_EOL;
        $ret = 1;
    }
}

echo PHP_EOL;

exit($ret);
