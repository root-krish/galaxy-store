<?php
declare(strict_types=1);
require __DIR__ . '/../app/bootstrap.php';
$path = sys_get_temp_dir() . '/galaxy-finance-' . bin2hex(random_bytes(6)) . '.json';
$store = new Store($path); $checks = 0;
function verify_finance(bool $condition, string $message): void {
    global $checks; if (!$condition) throw new RuntimeException($message); $checks++;
}
try {
    $admin = $store->user(1); $customer = $store->user(2);
    $data = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
    $template = $data['orders'][0];
    $make = static fn(int $id, string $at, float $subtotal, float $discount, float $cost, string $status = 'placed'): array => array_replace($template, [
        'id' => $id, 'created_at' => $at, 'status' => $status, 'subtotal' => $subtotal, 'discount' => $discount,
        'lines' => [['unit_cost' => $cost, 'quantity' => 2]],
    ]);
    $data['orders'] = [
        $make(1, '2024-02-29 23:59:59', 1000, 100, 400),
        $make(2, '2024-03-01 00:00:00', 200, 0, 25),
        $make(3, '2023-12-31 23:59:59', 500, 0, 25),
        $make(4, '2024-02-29 09:00:00', 2000, 0, 100, 'cancelled'),
        $make(5, '2024-02-28 10:00:00', 100, 0, 10),
    ];
    $data['incomes'] = [['id'=>1,'date'=>'2024-02-29','amount'=>100], ['id'=>2,'date'=>'2023-12-31','amount'=>50]];
    $data['expenses'] = [['id'=>1,'date'=>'2024-02-29','amount'=>300], ['id'=>2,'date'=>'2024-03-01','amount'=>10]];
    file_put_contents($path, json_encode($data, JSON_THROW_ON_ERROR));
    $day = $store->financeReport('day', '2024-02-29', $admin);
    verify_finance(count($day['buckets']) === 24, 'Day has 24 hourly buckets');
    verify_finance($day['totals']['orders'] === 1 && count($day['orders']) === 1, 'Day excludes cancelled and adjacent dates');
    verify_finance(abs($day['totals']['profit'] + 100) < .001, 'Profit includes discount, historical costs, income and expense');
    verify_finance($day['buckets']['2024-02-29 23']['sales'] === 900.0, 'End of day order is in last hour');
    verify_finance($day['buckets']['2024-02-29 00']['expenses'] === 300.0, 'Dated ledger entries are at midnight');
    verify_finance($day['previous'] === '2024-02-28' && $day['next'] === '2024-03-01', 'Day navigation crosses leap day');
    $month = $store->financeReport('month', '2024-02-29', $admin);
    verify_finance(count($month['buckets']) === 29 && $month['totals']['orders'] === 2, 'Leap month length and orders');
    verify_finance(abs($month['totals']['profit'] + 20) < .001, 'Monthly totals reconcile');
    verify_finance($month['previous'] === '2024-01-01' && $month['next'] === '2024-03-01', 'Month navigation does not skip months');
    $year = $store->financeReport('year', '2024-10-12', $admin);
    verify_finance(count($year['buckets']) === 12 && $year['totals']['orders'] === 3, 'Year includes only selected year');
    verify_finance(abs($year['totals']['profit'] - 120) < .001, 'Annual financial totals reconcile');
    foreach (['sales','income','cost','expenses','profit','orders'] as $key) verify_finance(abs(array_sum(array_column($year['buckets'], $key)) - $year['totals'][$key]) < .001, 'Chart reconciles with summary ' . $key);
    verify_finance($store->financeReport('year', '2023-01-01', $admin)['totals']['orders'] === 1, 'Previous year available');
    verify_finance($store->financeReport('day', '2024-01-01', $admin)['totals']['profit'] === 0.0, 'Empty period totals are zero');
    verify_finance($store->financeReport('year', '1900-01-01', $admin)['previous'] === null, 'Minimum navigation boundary');
    verify_finance($store->financeReport('year', '9998-01-01', $admin)['next'] === null, 'Maximum navigation boundary');
    foreach ([['week','2024-02-29',$admin], ['day','2023-02-29',$admin], ['year','1899-01-01',$admin], ['month','2024-01-01',$customer]] as $args) {
        try { $store->financeReport(...$args); throw new LogicException('Invalid report accepted'); } catch (RuntimeException) { $checks++; }
    }
    echo "Passed $checks finance checks.\n";
} finally { @unlink($path); @unlink($path . '.lock'); }
