<?php
declare(strict_types=1);

// Isolate provider state transitions without network calls or merchant credentials.
final class Payments
{
    public static string $status = 'created';
    public static array $requests = [];
    public static ?string $failMethod = null;
    public static function configured(): bool { return true; }
    public static function request(string $method, string $path): array
    {
        self::$requests[] = [$method, $path];
        if (self::$failMethod === $method) throw new RuntimeException('Synthetic provider outage');
        return ['status' => self::$status];
    }
}
const SG_NAME = 'Sports Galaxy';
date_default_timezone_set('Asia/Kolkata');
foreach (['Branding', 'AdminOperations', 'Commerce', 'FinanceReports', 'Store'] as $class) require __DIR__ . '/../app/' . $class . '.php';
putenv('SG_DB_DSN=');
$path = sys_get_temp_dir() . '/galaxy-review-' . bin2hex(random_bytes(6)) . '.json';
$store = new Store($path); $checks = 0;
function check_repair(bool $condition, string $message): void {
    global $checks; if (!$condition) throw new RuntimeException($message); $checks++;
}
function rejects_repair(callable $callback, string $message): void {
    try { $callback(); } catch (RuntimeException) { check_repair(true, $message); return; }
    throw new LogicException($message);
}
function read_fixture(): array { global $path; return json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR); }
function save_fixture(array $data): void { global $path; file_put_contents($path, json_encode($data, JSON_THROW_ON_ERROR)); }
try {
    $admin = $store->user(1); $buyer = $store->user(2);
    $order = $store->createOrder($buyer, [1=>1], ['recipient'=>'Buyer','phone'=>'9876543210','address'=>'Test address','city'=>'Kolkata','postal_code'=>'700001','payment_method'=>'razorpay']);
    $data = read_fixture(); $index = array_key_last($data['orders']);
    $data['orders'][$index]['payment_link_id'] = 'plink_synthetic'; save_fixture($data);
    $baseline = read_fixture(); $reserved = $store->product(1)['stock'];
    foreach (['', ' ', str_repeat('x', 501)] as $reason) {
        Payments::$requests = [];
        rejects_repair(fn() => $store->updateOrderStatus($order['id'], 'cancelled', $admin, $reason), 'Invalid cancellation reason rejected');
        check_repair(Payments::$requests === [], 'Invalid reason makes no provider request');
    }
    Payments::$requests = [];
    rejects_repair(fn() => $store->updateOrderStatus($order['id'], 'cancelled', $buyer, 'Valid reason'), 'Buyer cannot cancel through staff action');
    check_repair(Payments::$requests === [], 'Unauthorized actor makes no provider request');
    $invalidState = $baseline; $invalidState['orders'][$index]['status'] = 'invalid'; save_fixture($invalidState);
    rejects_repair(fn() => $store->updateOrderStatus($order['id'], 'cancelled', $admin, 'Valid reason'), 'Invalid transition rejected');
    check_repair(Payments::$requests === [], 'Invalid transition makes no provider request');
    save_fixture($baseline);
    foreach (['created', 'expired', 'cancelled'] as $state) {
        Payments::$status = $state; Payments::$requests = [];
        $cancelled = $store->updateOrderStatus($order['id'], 'cancelled', $admin, 'Cancel reserved order');
        check_repair($cancelled['status'] === 'cancelled', 'Cancellation succeeds for ' . $state);
        check_repair(count(Payments::$requests) === ($state === 'created' ? 2 : 1), 'Only created links receive POST');
        check_repair(Payments::$requests[0] === ['GET', 'payment_links/plink_synthetic'], 'Fetch link before cancellation');
        if ($state === 'created') check_repair(Payments::$requests[1] === ['POST','payment_links/plink_synthetic/cancel'], 'Cancel created link');
        check_repair($store->product(1)['stock'] === $reserved + 1, 'Restore inventory for ' . $state);
        rejects_repair(fn() => $store->updateOrderStatus($order['id'], 'cancelled', $admin, 'Again'), 'Closed order cannot cancel again');
        check_repair($store->product(1)['stock'] === $reserved + 1, 'Inventory restored once');
        save_fixture($baseline);
    }
    foreach (['paid', 'partially_paid', 'unknown'] as $state) {
        Payments::$status = $state; Payments::$requests = [];
        rejects_repair(fn() => $store->updateOrderStatus($order['id'], 'cancelled', $admin, 'Valid reason'), 'Refuse provider state ' . $state);
        check_repair(count(Payments::$requests) === 1 && Payments::$requests[0][0] === 'GET', 'No provider cancel for ' . $state);
        check_repair(read_fixture() === $baseline, 'Local order and inventory unchanged for ' . $state);
    }
    foreach (['GET', 'POST'] as $method) {
        Payments::$status = 'created'; Payments::$failMethod = $method;
        rejects_repair(fn() => $store->updateOrderStatus($order['id'], 'cancelled', $admin, 'Valid reason'), 'Provider failure rolls back');
        check_repair(read_fixture() === $baseline, 'Provider failure preserves local state');
    }
    Payments::$failMethod = null;
    $paid = $baseline; $paid['orders'][$index]['payment_status'] = 'paid'; save_fixture($paid);
    Payments::$requests = [];
    $cancelled = $store->updateOrderStatus($order['id'], 'cancelled', $admin, 'Refund requested');
    check_repair($cancelled['payment_status'] === 'refund_pending' && Payments::$requests === [], 'Verified paid cancellation preserves documented refund flow');

    $fixture = read_fixture();
    $fixture['notifications'] = [['id'=>1,'mail_to'=>'undeliverable@example.test','title'=>'Retry','body'=>'Synthetic']]; save_fixture($fixture);
    foreach ([1=>300, 2=>600, 3=>1200, 4=>2400, 5=>3600, 6=>0] as $attempt => $delay) {
        $before = time();
        rejects_repair(fn() => $store->sendQueuedMail(static function (): void { throw new RuntimeException('Synthetic delivery failure'); }), 'Failure reported');
        $fixture = read_fixture(); $message = $fixture['notifications'][0];
        check_repair($message['mail_attempts'] === $attempt, 'Failure increments persisted attempt count');
        if ($attempt < 6) {
            check_repair($message['mail_lease_until'] >= $before + $delay && $message['mail_lease_until'] <= time() + $delay, 'Exponential backoff capped at one hour');
            check_repair($store->sendQueuedMail(static function (): void { throw new LogicException('Backoff ignored'); }) === 0, 'Not retried before due time');
            $fixture['notifications'][0]['mail_lease_until'] = 0; save_fixture($fixture);
        } else {
            check_repair(!empty($message['mail_exhausted_at']) && $message['mail_lease_until'] === 0, 'Six failures exhaust message');
        }
    }
    $fixture = read_fixture();
    for ($id = 2; $id <= 20; $id++) $fixture['notifications'][] = ['id'=>$id,'mail_to'=>'bad@example.test','title'=>'Old','body'=>'','mail_attempts'=>6];
    $fixture['notifications'][] = ['id'=>21,'mail_to'=>'new@example.test','title'=>'New','body'=>'']; save_fixture($fixture);
    $sent = [];
    check_repair($store->sendQueuedMail(static function ($to) use (&$sent): void { $sent[] = $to; }) === 1, 'Exhausted messages do not starve newer mail');
    check_repair($sent === ['new@example.test'], 'Only eligible new mail sent');
    check_repair($store->sendQueuedMail(static function (): void { throw new LogicException('Duplicate send'); }) === 0, 'Sent and exhausted mail never selected again');
    echo "Passed $checks scoped review repair checks.\n";
} finally { @unlink($path); @unlink($path . '.lock'); }
