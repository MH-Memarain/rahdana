<?php

declare(strict_types=1);

/** Run only via CLI, against a dedicated Rahdana database. */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require __DIR__ . '/../vendor/autoload.php';
$config = require __DIR__ . '/../config/database.php';
if (($config['driver'] ?? 'mysql') !== 'mysql' || empty($config['database'])) {
    fwrite(STDERR, "Configure a MySQL database using DB_* environment variables.\n");
    exit(1);
}
$db = new \Paya\Core\Database($config);
$db->execute('CREATE TABLE IF NOT EXISTS schema_migrations (version VARCHAR(120) PRIMARY KEY, applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP)');
$files = glob(__DIR__ . '/../database/migrations/*.sql') ?: [];
sort($files);
foreach ($files as $path) {
    $version = basename($path);
    if ($db->fetchOne('SELECT version FROM schema_migrations WHERE version = ?', [$version])) {
        echo "Already applied: {$version}\n";
        continue;
    }
    // DDL is not transactional in MySQL. Keep statements idempotent and do not edit applied migrations.
    $sql = (string) file_get_contents($path);
    foreach (explode(';', $sql) as $statement) {
        $statement = trim($statement);
        if ($statement !== '') {
            $db->execute($statement);
        }
    }
    $db->execute('INSERT INTO schema_migrations (version) VALUES (?)', [$version]);
    echo "Applied: {$version}\n";
}
