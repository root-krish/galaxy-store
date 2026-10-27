<?php
declare(strict_types=1);

trait FinanceReports
{
    public function financeReport(string $period, string $date, array $actor): array
    {
        $data = $this->read();
        $this->requireAdmin($data, $actor);
        if (!in_array($period, ['day', 'month', 'year'], true)) throw new RuntimeException('Choose day, month, or year.');
        $this->validateDate($date);
        $anchor = new DateTimeImmutable($date);
        if ((int) $anchor->format('Y') < 1900 || (int) $anchor->format('Y') > 9998) throw new RuntimeException('Choose a year from 1900 to 9998.');
        $start = match ($period) {
            'month' => $anchor->modify('first day of this month'),
            'year' => $anchor->setDate((int) $anchor->format('Y'), 1, 1),
            default => $anchor,
        };
        $end = $start->modify('+1 ' . $period);
        $zero = ['sales' => 0.0, 'income' => 0.0, 'cost' => 0.0, 'expenses' => 0.0, 'profit' => 0.0, 'orders' => 0];
        $buckets = [];
        $step = match ($period) { 'day' => 'hour', 'month' => 'day', 'year' => 'month' };
        $keyFormat = match ($period) { 'day' => 'Y-m-d H', 'month' => 'Y-m-d', 'year' => 'Y-m' };
        for ($cursor = $start; $cursor < $end; $cursor = $cursor->modify('+1 ' . $step)) {
            $key = $cursor->format($keyFormat);
            $buckets[$key] = $zero + ['date' => $cursor->format('Y-m-d'), 'label' => $cursor->format(match ($period) { 'day' => 'H:00', 'month' => 'd M', 'year' => 'M Y' })];
        }
        $orders = $expenses = $incomes = [];
        $from = $start->format('Y-m-d'); $until = $end->format('Y-m-d');
        foreach ($data['orders'] as $order) {
            $day = substr($order['created_at'], 0, 10);
            if ($order['status'] === 'cancelled' || $day < $from || $day >= $until) continue;
            $key = (new DateTimeImmutable($order['created_at']))->format($keyFormat);
            $buckets[$key]['sales'] += $order['subtotal'] - $order['discount'];
            $buckets[$key]['cost'] += array_sum(array_map(static fn(array $line): float => $line['unit_cost'] * $line['quantity'], $order['lines']));
            $buckets[$key]['orders']++;
            $orders[] = $order;
        }
        foreach (['expenses' => 'expenses', 'incomes' => 'income'] as $collection => $metric) {
            foreach ($data[$collection] ?? [] as $entry) {
                if ($entry['date'] < $from || $entry['date'] >= $until) continue;
                $key = (new DateTimeImmutable($entry['date']))->format($keyFormat);
                $buckets[$key][$metric] += $entry['amount'];
                if ($collection === 'expenses') $expenses[] = $entry; else $incomes[] = $entry;
            }
        }
        $totals = $zero;
        foreach ($buckets as &$bucket) {
            $bucket['profit'] = $bucket['sales'] + $bucket['income'] - $bucket['cost'] - $bucket['expenses'];
            foreach (array_keys($zero) as $metric) $totals[$metric] += $bucket[$metric];
        }
        unset($bucket);
        usort($orders, static fn(array $a, array $b): int => strcmp($b['created_at'], $a['created_at']));
        return compact('period', 'date', 'buckets', 'totals', 'orders', 'expenses', 'incomes') + [
            'label' => $start->format(match ($period) { 'day' => 'd F Y', 'month' => 'F Y', 'year' => 'Y' }),
            'previous' => (int) $start->format('Y') > 1900 || $start->format('m-d') !== '01-01' ? $start->modify('-1 ' . $period)->format('Y-m-d') : null,
            'next' => $end->format('Y') <= '9998' ? $end->format('Y-m-d') : null,
        ];
    }
}
