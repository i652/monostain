<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/bootstrap.php';

$pdo = \Stain\Database::pdo();
$schema = file_get_contents(dirname(__DIR__) . '/schema.sql');
if ($schema === false) {
    throw new RuntimeException('Cannot read schema.sql');
}

$pdo->exec($schema);
echo "Schema is up to date.\n";
