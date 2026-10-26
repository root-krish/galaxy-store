<?php
declare(strict_types=1);
require __DIR__ . '/../app/bootstrap.php';

$path = sys_get_temp_dir() . '/galaxy-test-' . bin2hex(random_bytes(8)) . '.json';
$repository = new Store($path);
$checks = 0;
function check(bool $condition, string $message): void {
    global $checks;
    if (!$condition) { throw new RuntimeException($message); }
    $checks++;
}
function rejects(callable $action, string $message): void {
    try { $action(); } catch (RuntimeException) { check(true, $message); return; }
    throw new RuntimeException($message);
}
try {
    $customer = $repository->authenticate('CUSTOMER@sportsgalaxy.local', 'Customer@123');
    $admin = $repository->authenticate('admin@sportsgalaxy.local', 'Admin@123');
    $courier = $repository->authenticate('delivery@sportsgalaxy.local', 'Delivery@123');
    check($customer !== null && $admin !== null && $courier !== null, 'Demo accounts authenticate');
    check($repository->authenticate($customer['email'], 'wrong') === null, 'Wrong password rejected');
    $registered = $repository->register(['name' => 'Test Player', 'email' => 'NEW@example.com', 'phone' => '9876543210', 'password' => 'Testing@123']);
    check($registered['role'] === 'customer' && $registered['email'] === 'new@example.com', 'Registration normalizes email and role');
    rejects(fn() => $repository->register(['email' => 'NEW@example.com']), 'Duplicate email rejected');
    $address = ['recipient' => 'Test Player', 'phone' => '9876543210', 'address' => '12 Test Road', 'city' => 'Kolkata', 'postal_code' => '700001', 'payment_method' => 'cod', 'coupon' => 'GALAXY10'];
    check($repository->quote([6 => 1], 'galaxy10')['grand_total'] === 928.56, 'Discount, tax, shipping calculated correctly');
    check($repository->quote([])['grand_total'] === 0.0, 'Empty cart costs zero');
    rejects(fn() => $repository->quote([1 => 1], 'INVALID'), 'Unknown coupon rejected');
    $before = $repository->product(1)['stock'];
    $order = $repository->createOrder($customer, [1 => 2], $address);
    check($repository->product(1)['stock'] === $before - 2, 'Checkout deducts stock');
    check($order['grand_total'] === $repository->quote([1 => 2], 'GALAXY10')['grand_total'], 'Invoice matches quote');
    check(preg_match('/^\d{6}$/', $order['delivery_code']) === 1, 'Order has handover code');
    rejects(fn() => $repository->createOrder($customer, [1 => 999], $address), 'Overselling rejected');
    check($repository->product(1)['stock'] === $before - 2, 'Failed transaction preserves inventory');
    rejects(fn() => $repository->createOrder($customer, [999 => 1, 1 => 1], $address), 'Unavailable cart items rejected');
    rejects(fn() => $repository->createOrder($admin, [1 => 1], $address), 'Staff checkout rejected');
    rejects(fn() => $repository->updateOrderStatus($order['id'], 'delivered', $admin, '', $order['delivery_code']), 'Delivery cannot skip dispatch');
    rejects(fn() => $repository->assignDelivery($order['id'], $courier['id'], $customer), 'Customer cannot assign orders');
    $repository->updateOrderStatus($order['id'], 'packed', $admin);
    $repository->assignDelivery($order['id'], $courier['id'], $admin);
    $otherCourier = $courier; $otherCourier['id'] = 99;
    rejects(fn() => $repository->updateOrderStatus($order['id'], 'out_for_delivery', $otherCourier), 'Unassigned courier blocked');
    $repository->updateOrderStatus($order['id'], 'out_for_delivery', $courier);
    rejects(fn() => $repository->updateOrderStatus($order['id'], 'delivered', $courier, '', 'wrong'), 'Wrong verification code rejected');
    check($repository->order($order['id'])['status'] === 'out_for_delivery', 'Wrong code leaves order open');
    $delivered = $repository->updateOrderStatus($order['id'], 'delivered', $courier, '', $order['delivery_code']);
    check($delivered['payment_status'] === 'paid', 'COD collected on verified delivery');
    rejects(fn() => $repository->assignDelivery($order['id'], $courier['id'], $admin), 'Closed order cannot be reassigned');
    $cancel = $repository->createOrder($customer, [1 => 1], array_replace($address, ['payment_method' => 'upi']));
    $profit = $repository->dashboardStats()['month_profit'];
    $cancelled = $repository->updateOrderStatus($cancel['id'], 'cancelled', $admin, 'Customer requested cancellation');
    check($cancelled['payment_status'] === 'refunded', 'Simulated prepaid cancellation refunded');
    check($repository->product(1)['stock'] === $before - 2, 'Cancellation restores exact stock');
    rejects(fn() => $repository->updateOrderStatus($cancel['id'], 'cancelled', $admin), 'Cancellation cannot restore twice');
    check(abs(($profit - $repository->dashboardStats()['month_profit']) - ($cancel['subtotal'] - $cancel['discount'] - 650)) < 0.001, 'Profit excludes GST and shipping');
    foreach ($repository->notifications($customer['id']) as $notification) {
        check($notification['user_id'] === $customer['id'], 'Customer notifications stay private');
    }
    $product = $repository->product(1);
    rejects(fn() => $repository->saveProduct(array_replace($product, ['price' => 'invalid'])), 'Invalid price rejected');
    rejects(fn() => $repository->saveProduct(array_replace($product, ['category_id' => 999])), 'Invalid category rejected');
    rejects(fn() => $repository->saveProduct(array_replace($product, ['stock' => 1.5])), 'Fractional stock rejected');
    rejects(fn() => $repository->saveProduct(array_replace($product, ['restock_date' => '2026-02-31'])), 'Invalid restock date rejected');
    rejects(fn() => $repository->adjustStock(1, -999, '', $admin), 'Negative inventory rejected');
    $repository->saveProduct(array_replace($product, ['stock' => $product['stock'] + 3]));
    check($repository->product(1)['stock'] === $before + 1, 'Product editor updates stock');
    rejects(fn() => $repository->addExpense(['date' => '2026-02-31', 'amount' => 20], $admin), 'Invalid expense date rejected');
    rejects(fn() => $repository->addExpense(['date' => date('Y-m-d'), 'amount' => 'oops'], $admin), 'Invalid expense amount rejected');
    $profit = $repository->dashboardStats()['month_profit'];
    $repository->addExpense(['date' => date('Y-m-d'), 'category' => 'Test', 'description' => 'Test expense', 'amount' => 100], $admin);
    check(abs($repository->dashboardStats()['month_profit'] - ($profit - 100)) < 0.001, 'Expense affects monthly profit');
    check(count($repository->dashboardStats()['weekly_revenue']) === 7, 'Chart returns seven actual dates');
    check((new Store($path))->product(1)['stock'] === $before + 1, 'Changes persist in a new repository');
    echo "Passed $checks store checks.\n";
} finally {
    @unlink($path);
    @unlink($path . '.lock');
}
