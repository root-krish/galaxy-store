<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit;
require __DIR__ . '/../app/bootstrap.php';
require __DIR__ . '/../app/PdoRepository.php';
if (!getenv('SG_DB_DSN') || empty($argv[1]) || !is_file($argv[1])) exit("Usage: SG_DB_DSN=... php bin/migrate.php /absolute/path/to/data.json\n");
$data = json_decode(file_get_contents($argv[1]), true, 512, JSON_THROW_ON_ERROR);
foreach (['users','categories','products','orders','settings'] as $key) if (!isset($data[$key]) || !is_array($data[$key])) throw new RuntimeException('Invalid source data.');
foreach ($data['orders'] as &$order) $order['delivery_code'] ??= (string) random_int(100000,999999);
unset($order);
$repository = new PdoRepository(); $repository->migrate(); $repository->import($data);
echo "Migration complete. Source file preserved.\n";
