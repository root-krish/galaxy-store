<?php
declare(strict_types=1);
require __DIR__ . '/../app/bootstrap.php';
if (!getenv('SG_DB_DSN')) throw new RuntimeException('Use a disposable database imported with bin/migrate.php.');
$s = store();
$customer = $s->authenticate('customer@sportsgalaxy.local', 'Customer@123');
$admin = $s->authenticate('admin@sportsgalaxy.local', 'Admin@123');
if (!$customer || !$admin) throw new RuntimeException('Imported accounts missing');
$before = $s->product(1)['stock'];
$order = $s->createOrder($customer, [1=>1], ['recipient'=>'Test','phone'=>'9876543210','address'=>'Test street','city'=>'Kolkata','postal_code'=>'700001','payment_method'=>'cod']);
if ($s->product(1)['stock'] !== $before - 1) throw new RuntimeException('Inventory did not decrement');
$s->updateOrderStatus($order['id'], 'cancelled', $admin, 'Database regression test');
if ($s->product(1)['stock'] !== $before) throw new RuntimeException('Inventory did not restore');
$s->toggleWishlist($customer, 1);
if (!in_array(1, $s->wishlist($customer['id']), true)) throw new RuntimeException('Wishlist not persisted');
$s->toggleWishlist($customer, 1);
try { $s->createOrder($customer, [1=>99999], ['payment_method'=>'cod']); throw new LogicException('Oversell allowed'); } catch (RuntimeException $e) {}
if ($s->product(1)['stock'] !== $before) throw new RuntimeException('Rollback failed');
$pdo = new PDO(getenv('SG_DB_DSN'), getenv('SG_DB_USER') ?: '', getenv('SG_DB_PASSWORD') ?: '');
if ((int) $pdo->query('SELECT COUNT(*) FROM sg_order_items')->fetchColumn() < 3) throw new RuntimeException('Order lines missing');
echo "MySQL import, checkout, stock rollback, cancellation, wishlist and relational lines passed\n";
