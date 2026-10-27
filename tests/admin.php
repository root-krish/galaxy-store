<?php
declare(strict_types=1);
require __DIR__ . '/../app/bootstrap.php';
$path = sys_get_temp_dir() . '/galaxy-admin-' . bin2hex(random_bytes(8)) . '.json';
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
    $account = ['name' => 'Second Admin', 'email' => 'SECOND@example.com', 'phone' => '+91 98765 43210', 'password' => 'Second@123', 'role' => 'customer'];
    foreach ([$customer, $courier, ['id' => 999, 'role' => 'admin'], array_replace($customer, ['role' => 'admin'])] as $actor) denied(fn() => $repository->createAdministrator($account, $actor), 'Only persisted active admins may create administrators');
    $second = $repository->createAdministrator($account, $admin);
    verify($second['role'] === 'admin' && $second['email'] === 'second@example.com' && $second['created_by'] === 1, 'Admin creates another admin with normalized details');
    verify(count($repository->administrators()) === 2 && $repository->authenticate('second@example.com', 'Second@123')['id'] === $second['id'], 'New admin can sign in and is listed');
    denied(fn() => $repository->createAdministrator($account, $admin), 'Duplicate admin email rejected');
    $profile = ['name' => 'Admin Updated', 'email' => 'updated.admin@example.com', 'phone' => '9876543210', 'id' => $second['id'], 'role' => 'delivery'];
    denied(fn() => $repository->updateAdminProfile($profile, $customer), 'Customers cannot update an admin profile');
    denied(fn() => $repository->updateAdminProfile($profile, $courier), 'Couriers cannot update an admin profile');
    $updated = $repository->updateAdminProfile($profile, $admin);
    verify($updated['id'] === 1 && $updated['role'] === 'admin' && $repository->user($second['id'])['name'] === 'Second Admin', 'Admin profile targets own account and preserves roles');
    verify($repository->authenticate('updated.admin@example.com', 'Admin@123')['id'] === 1, 'Admin email change updates sign in');
    denied(fn() => $repository->updateAdminProfile(array_replace($profile, ['email' => $second['email']]), $admin), 'Admin email must be unique');
    denied(fn() => $repository->updateAdminProfile($profile + ['password' => 'Changed@123', 'current_password' => 'wrong'], $admin), 'Current password is required to change admin password');
    verify($repository->authenticate('updated.admin@example.com', 'Admin@123') !== null, 'Rejected password change preserves current password');
    $updated = $repository->updateAdminProfile($profile + ['password' => 'Changed@123', 'current_password' => 'Admin@123'], $admin);
    verify($repository->authenticate('updated.admin@example.com', 'Changed@123') !== null && $updated['auth_version'] === 1, 'Admin can change own password and revoke other sessions');

    $attributes = ['name' => 'Swimming', 'icon' => '🏊', 'color' => 'blue'];
    foreach ([$customer, $courier] as $actor) denied(fn() => $repository->saveCategory($attributes, $actor), 'Category management is admin only');
    $category = $repository->saveCategory($attributes, $second);
    verify(count($repository->categories()) === 6 && $category['icon'] === '🏊', 'New category and icon persist');
    denied(fn() => $repository->saveCategory(array_replace($attributes, ['name' => 'swimming']), $admin), 'Category names are unique without case sensitivity');
    foreach ([['name' => ''], ['icon' => '<script>'], ['color' => 'red;evil'], ['image_path' => '../secret.php'], ['id' => 999]] as $invalid) denied(fn() => $repository->saveCategory(array_replace($attributes, $invalid), $admin), 'Invalid category input rejected atomically');
    $image = str_repeat('b', 32) . '.png';
    $repository->saveCategory($category + ['unused' => true], $admin);
    $repository->saveCategory(array_replace($category, ['image_path' => $image, 'name' => 'Water Sports']), $admin);
    $repository->saveCategory(array_replace($attributes, ['id' => $category['id'], 'name' => 'Water Sports']), $admin);
    verify($repository->category($category['id'])['image_path'] === $image, 'Editing a category without a photo preserves the photo');
    $repository->saveCategory(array_replace($category, ['image_path' => null]), $admin);
    verify($repository->category($category['id'])['image_path'] === null, 'Category photo can be reset to its icon');
    $gear = array_replace($repository->product(1), ['id' => 0, 'sku' => 'SWIM-001', 'name' => 'Swimming goggles', 'category_id' => $category['id'], 'visual' => '🏊', 'image_path' => $image, 'stock' => 10]);
    $product = $repository->saveProduct($gear);
    verify($repository->products(null, $category['id'])[0]['id'] === $product['id'] && $product['visual'] === '🏊', 'New gear uses newly created category and custom icon');

    foreach ([$customer, $courier] as $actor) denied(fn() => $repository->manageDeliveryPartner(3, 'block', 'Missed deliveries', $actor), 'Partner access controls are admin only');
    foreach (['', '  ', 'bad', str_repeat('a', 501)] as $reason) denied(fn() => $repository->manageDeliveryPartner(3, 'block', $reason, $admin), 'Account changes require a proper reason');
    foreach ([1, 2, 999] as $id) denied(fn() => $repository->manageDeliveryPartner($id, 'delete', 'Invalid target', $admin), 'Partner account controls cannot target other roles or unknown accounts');
    $repository->updateOrderStatus(2, 'out_for_delivery', $courier);
    $stock = $repository->product(1)['stock']; $payment = $repository->order(2)['payment_status'];
    $blocked = $repository->manageDeliveryPartner(3, 'block', '  Missed delivery commitments  ', $admin);
    verify(!$blocked['is_active'] && $blocked['account_reason'] === 'Missed delivery commitments', 'Blocking stores a trimmed reason and disables access');
    verify($repository->authenticate($courier['email'], 'Delivery@123') === null && count($repository->deliveryPartners()) === 0, 'Blocked partner cannot sign in or receive assignments');
    verify($repository->order(2)['status'] === 'packed' && $repository->order(2)['delivery_partner_id'] === null, 'In-progress deliveries return to packed for reassignment');
    verify($repository->order(1)['status'] === 'delivered' && $repository->order(1)['delivery_partner_id'] === 3, 'Completed delivery history remains attached to partner');
    verify($repository->product(1)['stock'] === $stock && $repository->order(2)['payment_status'] === $payment, 'Account blocking does not cancel orders, restore stock, or refund');
    denied(fn() => $repository->assignDelivery(2, 3, $admin), 'Blocked partner cannot be assigned');
    denied(fn() => $repository->updateOrderStatus(2, 'delivered', $courier, '', $repository->order(2)['delivery_code']), 'Stale blocked actor cannot update orders');
    denied(fn() => $repository->createOrder($courier, [1 => 1], []), 'Stale blocked actor cannot purchase');
    $restored = $repository->manageDeliveryPartner(3, 'restore', 'Account review completed', $second);
    verify($restored['is_active'] && $restored['auth_version'] > $blocked['auth_version'] && count($restored['account_events']) === 2, 'Restoring access increments session version and retains audit history');
    verify($repository->authenticate($courier['email'], 'Delivery@123') !== null, 'Restored partner can sign in again');
    $repository->assignDelivery(2, 3, $second);
    $deleted = $repository->manageDeliveryPartner(3, 'delete', 'Partner left the delivery team', $second);
    verify(!empty($deleted['deleted_at']) && !$deleted['is_active'] && count($deleted['account_events']) === 3, 'Deleting removes access permanently with reason and audit history');
    verify(count($repository->deliveryPartners()) === 0 && count($repository->deliveryPartners(true)) === 1, 'Deleted partner remains available only in administrative account history');
    verify($repository->order(2)['status'] === 'packed' && $repository->order(1)['delivery_partner_id'] === 3, 'Deleting releases active jobs and retains completed history');
    denied(fn() => $repository->manageDeliveryPartner(3, 'restore', 'Attempt restore deleted', $admin), 'Deleted accounts cannot be restored');
    denied(fn() => $repository->updateDeliveryPartner(3, $profile, $admin), 'Deleted accounts cannot be edited');
    verify($repository->authenticate($courier['email'], 'Delivery@123') === null, 'Deleted partner cannot sign in');

    $custom = ['hero_title' => 'Your next match starts here', 'announcement_text' => '', 'heading_font' => 'modern', 'card_style' => 'square', 'catalogue_columns' => '3', 'show_story' => '0', 'support_email' => 'help@example.com', 'support_phone' => '+91 98765 43210', 'secondary_color' => '#123abc'];
    $repository->saveBranding($custom, $second);
    verify($repository->settings()['hero_title'] === $custom['hero_title'] && $repository->settings()['show_story'] === '0', 'Extended website settings persist');
    verify(str_contains(Branding::cssVariables($repository->settings())['--display'], 'Arial') && Branding::cssVariables($repository->settings())['--violet'] === '#123abc', 'Font and secondary colour generate safe CSS');
    foreach ([['heading_font' => 'url(evil)'], ['card_style' => 'evil'], ['catalogue_columns' => '12'], ['show_story' => 'yes'], ['support_email' => 'bad'], ['support_phone' => 'bad'], ['hero_title' => str_repeat('a', 121)], ['secondary_color' => 'red;evil']] as $invalid) denied(fn() => $repository->saveBranding($invalid, $second), 'Invalid customization rejected atomically');
    $repository->resetBranding($second);
    verify($repository->settings()['hero_title'] === Branding::DEFAULTS['hero_title'] && $repository->settings()['show_story'] === '1', 'Reset restores all website options');
    echo "Passed $checks administrator checks.\n";
} finally {
    @unlink($path); @unlink($path . '.lock');
}
