<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit;
require __DIR__ . '/../app/bootstrap.php';
$failed = false;
foreach (store()->allOrders() as $order) {
    if ($order['payment_method'] !== 'razorpay' || $order['payment_status'] !== 'pending' || empty($order['payment_link_id'])) continue;
    try { store()->verifyPayment($order['id'], store()->user($order['user_id'])); }
    catch (RuntimeException $e) { if ($e->getMessage() !== 'Payment has not been confirmed by the provider yet.') { fwrite(STDERR, 'Unable to reconcile order ' . $order['number'] . "\n"); $failed = true; } }
}
exit($failed ? 1 : 0);
