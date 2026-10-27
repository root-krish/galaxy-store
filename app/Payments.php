<?php
declare(strict_types=1);

final class Payments
{
    public static function configured(): bool { return (bool) getenv('SG_RAZORPAY_KEY_ID') && (bool) getenv('SG_RAZORPAY_KEY_SECRET'); }
    public static function request(string $method, string $path, ?array $body = null): array
    {
        if (!self::configured()) throw new RuntimeException('Online payment is not configured. Choose cash on delivery.');
        $curl = curl_init('https://api.razorpay.com/v1/' . $path);
        curl_setopt_array($curl, [CURLOPT_RETURNTRANSFER => true, CURLOPT_CUSTOMREQUEST => $method, CURLOPT_USERPWD => getenv('SG_RAZORPAY_KEY_ID') . ':' . getenv('SG_RAZORPAY_KEY_SECRET'), CURLOPT_HTTPHEADER => ['Content-Type: application/json'], CURLOPT_CONNECTTIMEOUT => 5, CURLOPT_TIMEOUT => 20, CURLOPT_PROTOCOLS => CURLPROTO_HTTPS]);
        if ($body !== null) curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($body, JSON_THROW_ON_ERROR));
        $result = curl_exec($curl); $status = curl_getinfo($curl, CURLINFO_HTTP_CODE); curl_close($curl);
        if ($result === false || $status < 200 || $status >= 300) throw new RuntimeException('Payment provider unavailable. Your order is saved; retry from its receipt or contact support.');
        return json_decode($result, true, 512, JSON_THROW_ON_ERROR);
    }
    public static function create(array $order): array
    {
        return self::request('POST', 'payment_links', [
            'amount' => (int) round($order['grand_total'] * 100), 'currency' => 'INR', 'accept_partial' => false,
            'reference_id' => $order['number'], 'description' => 'Order ' . $order['number'],
            'customer' => ['name' => $order['customer_name'], 'email' => $order['customer_email']],
            'notify' => ['email' => true, 'sms' => false], 'expire_by' => time() + 86400,
        ]);
    }
    public static function verified(array $order, array $link): bool
    {
        return ($link['id'] ?? '') === ($order['payment_link_id'] ?? '')
            && ($link['reference_id'] ?? '') === $order['number'] && ($link['currency'] ?? '') === 'INR'
            && ($link['status'] ?? '') === 'paid' && (int) ($link['amount_paid'] ?? 0) === (int) round($order['grand_total'] * 100);
    }
}
