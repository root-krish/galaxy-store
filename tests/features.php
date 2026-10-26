<?php
declare(strict_types=1);
require __DIR__ . '/../app/bootstrap.php';
$path = sys_get_temp_dir() . '/galaxy-features-' . bin2hex(random_bytes(8)) . '.json';
$repository = new Store($path);
$checks = 0;
function verify(bool $condition, string $message): void {
    global $checks;
    if (!$condition) throw new RuntimeException($message);
    $checks++;
}
function denied(callable $callback, string $message): void {
    try { $callback(); } catch (RuntimeException) { verify(true, $message); return; }
    throw new RuntimeException($message);
}
try {
    $admin = $repository->user(1); $customer = $repository->user(2); $courier = $repository->user(3);
    $attributes = ['name' => 'New Partner', 'email' => 'partner@example.com', 'phone' => '9876543210', 'password' => 'Partner@123'];
    denied(fn() => $repository->createDeliveryPartner($attributes, $customer), 'Customer cannot provision a partner');
    denied(fn() => $repository->createDeliveryPartner($attributes, $courier), 'Courier cannot provision a partner');
    $partner = $repository->createDeliveryPartner($attributes + ['role' => 'admin'], $admin);
    verify($partner['role'] === 'delivery', 'Admin provisions delivery role only');
    verify($repository->authenticate('PARTNER@example.com', 'Partner@123')['id'] === $partner['id'], 'New partner can sign in');
    denied(fn() => $repository->createDeliveryPartner($attributes, $admin), 'Duplicate email rejected');
    denied(fn() => $repository->createDeliveryPartner(array_replace($attributes, ['email' => 'bad@example.com', 'password' => 'short']), $admin), 'Weak initial password rejected');
    $registered = $repository->register(array_replace($attributes, ['email' => 'public@example.com', 'role' => 'delivery']));
    verify($registered['role'] === 'customer', 'Public registration ignores role escalation');

    $income = ['date' => date('Y-m-d'), 'category' => 'Sponsorship', 'description' => 'Tournament bonus', 'amount' => '123.45'];
    $stats = $repository->dashboardStats();
    denied(fn() => $repository->addIncome($income, $customer), 'Customer cannot add income');
    denied(fn() => $repository->addIncome($income, $courier), 'Courier cannot add income');
    $repository->addIncome($income, $admin);
    verify(abs($repository->dashboardStats()['month_profit'] - $stats['month_profit'] - 123.45) < .001, 'External income increases monthly profit');
    verify(abs($repository->dashboardStats()['month_income'] - 123.45) < .001, 'Monthly income recorded');
    verify($repository->dashboardStats()['month_net_sales'] === $stats['month_net_sales'], 'External income does not change product sales');
    $repository->addIncome(array_replace($income, ['date' => date('Y-m-d', strtotime('first day of last month')), 'amount' => 500]), $admin);
    verify(abs($repository->dashboardStats()['month_income'] - 123.45) < .001, 'Other months excluded from current income');
    foreach (['0', '-5', 'invalid', 'INF', '0.001'] as $amount) {
        denied(fn() => $repository->addIncome(array_replace($income, ['amount' => $amount]), $admin), 'Invalid income rejected');
    }
    denied(fn() => $repository->addIncome(array_replace($income, ['date' => '2026-02-31']), $admin), 'Invalid income date rejected');
    denied(fn() => $repository->addIncome(array_replace($income, ['description' => '']), $admin), 'Income description required');

    $image = str_repeat('a', 32) . '.png';
    foreach ([$admin, $customer, $courier] as $actor) {
        verify($repository->updateProfileImage($actor, $image)['profile_image'] === $image, 'Each role may update its own photo');
        verify($repository->updateProfileImage($actor, null)['profile_image'] === null, 'Each role may restore the default');
    }
    denied(fn() => $repository->updateProfileImage($customer, '../secret.php'), 'Unsafe profile filename rejected');
    $product = $repository->product(1);
    $repository->saveProduct($product + ['image_path' => $image]);
    verify($repository->product(1)['image_path'] === $image, 'Product photo is saved');
    $preserve = $repository->product(1); unset($preserve['image_path']);
    $repository->saveProduct($preserve);
    verify($repository->product(1)['image_path'] === $image, 'Omitting a photo preserves it');
    $address = ['recipient' => 'Rohan', 'phone' => '9876543210', 'address' => 'Test Road', 'city' => 'Kolkata', 'postal_code' => '700001', 'payment_method' => 'cod'];
    $order = $repository->createOrder($courier, [1 => 1], $address);
    verify($order['user_id'] === $courier['id'], 'Courier can purchase');
    verify($order['lines'][0]['image_path'] === $image, 'Receipt keeps its product photo snapshot');
    verify(count($repository->ordersForUser($courier['id'])) === 1, 'Courier has personal order history');
    denied(fn() => $repository->assignDelivery($order['id'], $courier['id'], $admin), 'Self delivery is blocked');
    $repository->assignDelivery($order['id'], $partner['id'], $admin);
    verify($repository->order($order['id'])['delivery_partner_id'] === $partner['id'], 'Another courier can deliver the purchase');
    $repository->saveProduct(array_replace($repository->product(1), ['image_path' => null]));
    verify($repository->product(1)['image_path'] === null, 'Product default can be restored');
    verify($repository->order($order['id'])['lines'][0]['image_path'] === $image, 'Reset does not alter past receipts');
    denied(fn() => $repository->saveProduct(array_replace($repository->product(1), ['image_path' => 'http://example.com/a.png'])), 'Untrusted image path rejected');

    $oldOrderName = $repository->order(1)['customer_name'];
    $contact = ['name' => 'Ananya Updated', 'email' => 'ANANYA.NEW@example.com', 'phone' => '+91 98765 43210', 'id' => 1, 'role' => 'admin'];
    $updated = $repository->updateCustomerProfile($contact, $customer);
    verify($updated['id'] === 2 && $updated['role'] === 'customer', 'Customer edits only their own permitted fields');
    verify($updated['email'] === 'ananya.new@example.com', 'Updated email normalized');
    verify($repository->authenticate('ananya.new@example.com', 'Customer@123')['id'] === 2, 'Customer can sign in with updated email');
    verify($repository->authenticate($customer['email'], 'Customer@123') === null, 'Old email no longer signs in');
    verify($repository->order(1)['customer_name'] === $oldOrderName, 'Customer edit preserves historical receipts');
    denied(fn() => $repository->updateCustomerProfile($contact, $courier), 'Courier cannot edit own contact details');
    denied(fn() => $repository->updateCustomerProfile(array_replace($contact, ['email' => $admin['email']]), $customer), 'Duplicate contact email rejected');
    denied(fn() => $repository->updateCustomerProfile(array_replace($contact, ['phone' => 'abcdefghi']), $customer), 'Invalid contact phone rejected');
    $partnerContact = ['name' => 'Partner Updated', 'email' => 'updated.partner@example.com', 'phone' => '9876543210', 'password' => 'Changed@123', 'role' => 'admin'];
    denied(fn() => $repository->updateDeliveryPartner(3, $partnerContact, $customer), 'Customer cannot edit partner details');
    denied(fn() => $repository->updateDeliveryPartner(3, $partnerContact, $courier), 'Courier cannot edit partner details');
    denied(fn() => $repository->updateDeliveryPartner(2, $partnerContact, $admin), 'Partner editor cannot target customer accounts');
    $repository->updateDeliveryPartner(3, $partnerContact, $admin);
    verify($repository->user(3)['name'] === 'Partner Updated' && $repository->user(3)['role'] === 'delivery', 'Admin can change partner contact without changing role');
    verify($repository->authenticate('updated.partner@example.com', 'Changed@123')['id'] === 3, 'Admin can reset partner password');
    $repository->updateDeliveryPartner(3, array_replace($partnerContact, ['password' => '']), $admin);
    verify($repository->authenticate('updated.partner@example.com', 'Changed@123') !== null, 'Blank partner password preserves existing password');
    denied(fn() => $repository->updateDeliveryPartner(3, array_replace($partnerContact, ['email' => $admin['email']]), $admin), 'Partner email must be unique');

    $theme = ['shop_name' => 'Orbit Sports', 'accent_color' => '#2563eb', 'background_color' => '#f5f7ff', 'text_color' => '#111827', 'shop_logo' => $image, 'tax_rate' => 99];
    denied(fn() => $repository->saveBranding($theme, $customer), 'Customer cannot change shop settings');
    denied(fn() => $repository->saveBranding($theme, $courier), 'Courier cannot change shop settings');
    $tax = $repository->settings()['tax_rate'];
    $repository->saveBranding($theme, $admin);
    verify($repository->settings()['shop_name'] === 'Orbit Sports' && $repository->settings()['shop_logo'] === $image, 'Shop name and logo persist');
    verify($repository->settings()['tax_rate'] === $tax, 'Branding cannot alter financial settings');
    verify(Branding::cssVariables($repository->settings())['--on-accent'] === '#ffffff', 'Dark accent gets readable button text');
    denied(fn() => $repository->saveBranding(['accent_color' => 'red;}</style><script>'], $admin), 'CSS injection rejected');
    denied(fn() => $repository->saveBranding(['shop_logo' => '../secret.php'], $admin), 'Unsafe logo rejected');
    denied(fn() => $repository->saveBranding(['shop_name' => ''], $admin), 'Empty shop name rejected');
    denied(fn() => $repository->saveBranding(['text_color' => '#ffffff'], $admin), 'Unreadable text colour rejected');
    $repository->saveBranding(['shop_name' => 'Orbit Updated'], $admin);
    verify($repository->settings()['shop_logo'] === $image, 'Editing shop name preserves logo');
    denied(fn() => $repository->resetBranding($customer), 'Default branding reset is admin only');
    $repository->resetBranding($admin);
    verify($repository->settings()['shop_name'] === SG_NAME && $repository->settings()['shop_logo'] === null, 'Branding reset restores defaults');
    verify($repository->settings()['tax_rate'] === $tax, 'Reset preserves financial settings');

    $cancelOrder = $repository->createOrder($repository->user(2), [6 => 1], $address);
    $stockBefore = $repository->product(6)['stock'];
    foreach (['', '  ', "\t\n", str_repeat('a', 501)] as $reason) {
        denied(fn() => $repository->updateOrderStatus($cancelOrder['id'], 'cancelled', $admin, $reason), 'Cancellation requires a nonblank reason up to 500 characters');
    }
    verify($repository->order($cancelOrder['id'])['status'] === 'placed' && $repository->product(6)['stock'] === $stockBefore, 'Failed cancellation does not change stock or order');
    $cancelled = $repository->updateOrderStatus($cancelOrder['id'], 'cancelled', $admin, '  Customer requested a different item.  ');
    verify($cancelled['cancellation_reason'] === 'Customer requested a different item.', 'Trimmed cancellation reason is stored');
    verify(end($cancelled['delivery_events'])['note'] === $cancelled['cancellation_reason'], 'Reason is present in customer timeline');
    verify($repository->product(6)['stock'] === $stockBefore + 1, 'Cancellation with reason restores stock');
    echo "Passed $checks feature checks.\n";
} finally {
    @unlink($path); @unlink($path . '.lock');
}
