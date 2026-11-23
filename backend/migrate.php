<?php
/**
 * Database Migration Runner
 * Run this script to apply database migrations
 */

require_once __DIR__ . '/../bootstrap/app.php';

echo "Genmashy Distributors - Database Migration Runner\n";
echo "================================================\n\n";

$migrationsDir = __DIR__ . '/../migrations';

if (!is_dir($migrationsDir)) {
    echo "Error: Migrations directory not found at {$migrationsDir}\n";
    exit(1);
}

$files = glob($migrationsDir . '/*.sql');
sort($files);

if (empty($files)) {
    echo "No migration files found.\n";
    exit(0);
}

$pdo = Database::getConnection();

// Create migrations table if not exists
$pdo->exec("
    CREATE TABLE IF NOT EXISTS `migrations` (
        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `migration` VARCHAR(255) NOT NULL,
        `batch` INT NOT NULL,
        `executed_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uk_migrations_migration` (`migration`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
");

$executedMigrations = $pdo->query("SELECT `migration` FROM `migrations`")->fetchAll(PDO::FETCH_COLUMN);
$executedMigrations = array_flip($executedMigrations);

$batch = 1;
$lastBatch = $pdo->query("SELECT MAX(`batch`) FROM `migrations`")->fetchColumn();
if ($lastBatch) {
    $batch = (int)$lastBatch + 1;
}

$pending = 0;
$executed = 0;
$failed = 0;

foreach ($files as $file) {
    $filename = basename($file);
    
    if (isset($executedMigrations[$filename])) {
        echo "  [SKIP] {$filename} (already executed)\n";
        continue;
    }

    echo "  [RUN]  {$filename} ... ";
    
    $sql = file_get_contents($file);
    if ($sql === false) {
        echo "FAILED (could not read file)\n";
        $failed++;
        continue;
    }

    try {
        $pdo->beginTransaction();
        
        // Split by semicolon and execute each statement
        $statements = array_filter(array_map('trim', explode(';', $sql)));
        
        foreach ($statements as $statement) {
            if (!empty($statement)) {
                $pdo->exec($statement);
            }
        }
        
        // Record migration
        $stmt = $pdo->prepare("INSERT INTO `migrations` (`migration`, `batch`) VALUES (?, ?)");
        $stmt->execute([$filename, $batch]);
        
        $pdo->commit();
        echo "OK\n";
        $executed++;
        
    } catch (Exception $e) {
        $pdo->rollBack();
        echo "FAILED\n";
        echo "       Error: {$e->getMessage()}\n";
        $failed++;
    }
    
    $pending++;
}

echo "\n";
echo "Summary:\n";
echo "  Pending: {$pending}\n";
echo "  Executed: {$executed}\n";
echo "  Failed: {$failed}\n";

if ($failed > 0) {
    echo "\nSome migrations failed. Please check the errors above.\n";
    exit(1);
}

if ($executed > 0) {
    echo "\nAll migrations executed successfully!\n";
} else {
    echo "\nNo new migrations to run.\n";
}