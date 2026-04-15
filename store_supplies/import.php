<?php
/**
 * One-time import script. Run once via browser, then delete this file.
 * URL: yourdomain.com/supplies/import.php
 */
require_once __DIR__ . '/db.php';

$pdo = get_db();

$pdo->exec("CREATE TABLE IF NOT EXISTS items (
    id               INT AUTO_INCREMENT PRIMARY KEY,
    item_number      VARCHAR(20)  NOT NULL,
    name             VARCHAR(255) NOT NULL,
    package_size     INT          NOT NULL DEFAULT 0,
    max_order_number INT          NOT NULL DEFAULT 0,
    category         VARCHAR(100) NOT NULL,
    INDEX idx_category (category),
    INDEX idx_item_number (item_number)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$pdo->exec("TRUNCATE TABLE items");

$file = fopen(__DIR__ . '/store_supplies.csv', 'r');
if (!$file) {
    die('Error: could not open store_supplies.csv');
}

fgetcsv($file); // skip header row

$stmt = $pdo->prepare(
    "INSERT INTO items (item_number, name, package_size, max_order_number, category)
     VALUES (?, ?, ?, ?, ?)"
);

$count = 0;
while (($row = fgetcsv($file)) !== false) {
    if (count($row) < 5) continue;
    [$item_number, $name, $package_size, $max_order_number, $category] = $row;
    $stmt->execute([
        trim($item_number),
        trim($name),
        (int) $package_size,
        (int) $max_order_number,
        trim($category),
    ]);
    $count++;
}

fclose($file);

echo "<p>✓ Imported <strong>$count items</strong> successfully.</p>";
echo "<p><strong>Delete this file (import.php) before sharing the URL.</strong></p>";
echo '<p><a href="index.php">→ Go to app</a></p>';
