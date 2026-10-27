<?php
declare(strict_types=1);
require __DIR__ . '/../app/bootstrap.php';
if (!getenv('SG_DB_DSN')) throw new RuntimeException('A disposable imported database is required.');
$s = store();
if (($argv[1] ?? '') === 'worker') {
    try {
        $s->createOrder($s->user(2), [1=>10], ['recipient'=>'Concurrency test','phone'=>'9876543210','address'=>'Test address','city'=>'Kolkata','postal_code'=>'700001','payment_method'=>'cod']);
        exit(0);
    } catch (RuntimeException) { exit(2); }
}
$admin = $s->user(1); $before = $s->product(1)['stock'];
$s->adjustStock(1, 14 - $before, 'Concurrency fixture', $admin);
$workers = [];
for ($i=0;$i<2;$i++) $workers[] = proc_open([PHP_BINARY, __FILE__, 'worker'], [0=>['file','/dev/null','r'],1=>['file','/dev/null','w'],2=>['file','/dev/null','w']], $pipes);
$codes = array_map('proc_close', $workers); sort($codes);
try {
    if ($codes !== [0,2] || $s->product(1)['stock'] !== 4) throw new RuntimeException('Concurrent stock reservation failed');
    echo "Concurrent MySQL purchases prevent overselling\n";
} finally {
    foreach ($s->allOrders() as $order) if (($order['address']['recipient'] ?? '') === 'Concurrency test' && $order['status'] !== 'cancelled') $s->updateOrderStatus($order['id'], 'cancelled', $admin, 'Concurrency test cleanup');
    $delta = $before - $s->product(1)['stock']; if ($delta) $s->adjustStock(1, $delta, 'Restore concurrency fixture', $admin);
}
