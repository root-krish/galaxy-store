<?php
declare(strict_types=1);
require __DIR__ . '/../app/bootstrap.php';
$path = sys_get_temp_dir() . '/sg-upgrades-' . bin2hex(random_bytes(5)) . '.json';
$s = new Store($path);
function expect(bool $condition, string $message): void { if (!$condition) throw new RuntimeException($message); }
try {
    $admin = $s->user(1); $user = $s->user(2);
    expect($s->toggleWishlist($user, 1), 'Save item'); expect($s->wishlist(2) === [1], 'Wishlist persists');
    expect(!$s->toggleWishlist($user, 1) && $s->wishlist(2) === [], 'Remove item');
    try { $s->saveBranding(['demo_access' => '0'], $admin); throw new LogicException('Lockout allowed'); } catch (RuntimeException $e) {}
    $personal = $s->createAdministrator(['name'=>'Owner Account','email'=>'owner@example.test','phone'=>'9876543210','password'=>'Owner@12345'], $admin);
    $s->saveBranding(['demo_access'=>'0'], $personal);
    expect($s->authenticate($admin['email'], 'Admin@123') === null, 'Demo login disabled');
    expect(!$s->accountAllowed($user), 'Existing demo sessions denied');
    expect($s->authenticate('owner@example.test', 'Owner@12345') !== null, 'Owner can login');
    $s->resetBranding($personal); expect($s->settings()['demo_access'] === '0', 'Reset cannot enable demo');
    $order = ['number'=>'SG-1','payment_link_id'=>'plink_1','grand_total'=>100];
    $link = ['id'=>'plink_1','reference_id'=>'SG-1','currency'=>'INR','status'=>'paid','amount_paid'=>10000];
    expect(Payments::verified($order,$link), 'Verified provider response');
    foreach (['amount_paid'=>1,'reference_id'=>'SG-2','currency'=>'USD','status'=>'pending','id'=>'plink_2'] as $key=>$value) expect(!Payments::verified($order, array_replace($link,[$key=>$value])), 'Reject incorrect payment ' . $key);
    expect(count($s->products('NIVIA Football')) >= 1, 'Search matches separate terms');
    $buyer = $s->register(['name'=>'Buyer Account','email'=>'buyer@example.test','phone'=>'9876543210','password'=>'Buyer@12345']);
    $sent = [];
    expect($s->sendQueuedMail(function($to,$title,$body) use (&$sent) { $sent[] = [$to,$title,$body]; }) > 0, 'Queued mail sends');
    expect($s->sendQueuedMail(function() { throw new LogicException('Duplicate send'); }) === 0, 'Sent mail is not sent again');
    $s->register(['name'=>'Retry Account','email'=>'retry@example.test','phone'=>'9876543210','password'=>'Retry@12345']);
    try { $s->sendQueuedMail(function() { throw new RuntimeException('Synthetic SMTP outage'); }); throw new LogicException('Failure concealed'); } catch (RuntimeException $e) {}
    expect($s->sendQueuedMail(function() { throw new LogicException('Retry backoff ignored'); }) === 0, 'Failed mail uses retry backoff');
    putenv('SG_RAZORPAY_KEY_ID=synthetic'); putenv('SG_RAZORPAY_KEY_SECRET=synthetic');
    $details = ['recipient'=>'Buyer','phone'=>'9876543210','address'=>'Test address','city'=>'Kolkata','postal_code'=>'700001','payment_method'=>'razorpay'];
    $pending = $s->createOrder($buyer, [1=>1], $details);
    expect($pending['payment_status'] === 'pending', 'Real checkout remains unpaid');
    try { $s->assignDelivery($pending['id'], 3, $personal); throw new LogicException('Unpaid order assigned'); } catch (RuntimeException $e) {}
    try { $s->updateOrderStatus($pending['id'], 'packed', $personal); throw new LogicException('Unpaid order packed'); } catch (RuntimeException $e) {}
    try { $s->createOrder($buyer, [1=>1], array_replace($details,['payment_method'=>'upi'])); throw new LogicException('Demo payment allowed'); } catch (RuntimeException $e) {}
    $s->updateOrderStatus($pending['id'], 'cancelled', $personal, 'Synthetic test cleanup');
    putenv('SG_RAZORPAY_KEY_ID'); putenv('SG_RAZORPAY_KEY_SECRET');
    echo "Upgrade security checks passed\n";
} finally { @unlink($path); @unlink($path . '.lock'); }
