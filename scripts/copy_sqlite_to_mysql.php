<?php
/**
 * One-off tool: copy all data from a local SQLite database into a MySQL
 * database that already has the matching schema (i.e. after `migrate:fresh`).
 *
 * Usage:
 *   php scripts/copy_sqlite_to_mysql.php \
 *     --sqlite=database/database.sqlite \
 *     --host=127.0.0.1 --port=3306 --db=DBNAME --user=DBUSER --pass=DBPASS
 */

$args = [];
foreach ($argv as $arg) {
    if (str_starts_with($arg, '--') && str_contains($arg, '=')) {
        [$k, $v] = explode('=', substr($arg, 2), 2);
        $args[$k] = $v;
    }
}

foreach (['sqlite', 'host', 'port', 'db', 'user', 'pass'] as $required) {
    if (!isset($args[$required])) {
        fwrite(STDERR, "Missing --{$required}\n");
        exit(1);
    }
}

$sqlitePath = $args['sqlite'];
if (!is_file($sqlitePath)) {
    fwrite(STDERR, "SQLite file not found: {$sqlitePath}\n");
    exit(1);
}

$sqlite = new PDO('sqlite:' . $sqlitePath);
$sqlite->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$mysql = new PDO(
    "mysql:host={$args['host']};port={$args['port']};dbname={$args['db']};charset=utf8mb4",
    $args['user'],
    $args['pass'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

// Tables Laravel manages that we do NOT copy verbatim:
// - migrations: already populated correctly by `migrate:fresh` on the target.
$skip = ['migrations'];

$tables = $sqlite->query(
    "SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'"
)->fetchAll(PDO::FETCH_COLUMN);

$mysql->exec('SET FOREIGN_KEY_CHECKS=0');

foreach ($tables as $table) {
    if (in_array($table, $skip, true)) {
        echo "Skipping {$table}\n";
        continue;
    }

    $rows = $sqlite->query("SELECT * FROM \"{$table}\"")->fetchAll(PDO::FETCH_ASSOC);

    $mysql->exec("TRUNCATE TABLE `{$table}`");

    if (empty($rows)) {
        echo "{$table}: 0 rows\n";
        continue;
    }

    $columns = array_keys($rows[0]);
    $columnList = implode(', ', array_map(fn ($c) => "`{$c}`", $columns));
    $placeholders = implode(', ', array_fill(0, count($columns), '?'));
    $stmt = $mysql->prepare("INSERT INTO `{$table}` ({$columnList}) VALUES ({$placeholders})");

    $mysql->beginTransaction();
    foreach ($rows as $row) {
        $stmt->execute(array_values($row));
    }
    $mysql->commit();

    echo "{$table}: " . count($rows) . " rows copied\n";
}

$mysql->exec('SET FOREIGN_KEY_CHECKS=1');

echo "Done.\n";
