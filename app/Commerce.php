<?php
declare(strict_types=1);
trait Commerce
{
    public function paymentLink(int $id, array $actor): string
    {
        return $this->write(function (array &$data) use ($id, $actor): string {
            $index = $this->findIndex($data['orders'], $id);
            if ($index === null) throw new RuntimeException('Order unavailable.');
            $order =& $data['orders'][$index];
            if ((int) $order['user_id'] !== (int) $actor['id'] || $order['status'] === 'cancelled' || $order['payment_method'] !== 'razorpay' || $order['payment_status'] !== 'pending') throw new RuntimeException('Payment is unavailable for this order.');
            if (empty($order['payment_link_url'])) {
                $link = Payments::create($order);
                if (!preg_match('/^plink_[a-zA-Z0-9]+$/D', $link['id'] ?? '') || !preg_match('#^https://rzp\.io/[a-zA-Z0-9/_-]+$#D', $link['short_url'] ?? '')) throw new RuntimeException('Invalid payment provider response. Contact support.');
                $order['payment_link_id'] = $link['id']; $order['payment_link_url'] = $link['short_url'];
            }
            return $order['payment_link_url'];
        });
    }
    public function verifyPayment(int $id, array $actor): void
    {
        $order = $this->order($id);
        if (!$order || ((int) $order['user_id'] !== (int) $actor['id'] && $actor['role'] !== 'admin') || empty($order['payment_link_id'])) throw new RuntimeException('Payment unavailable.');
        $link = Payments::request('GET', 'payment_links/' . rawurlencode($order['payment_link_id']));
        $this->write(function (array &$data) use ($id, $link): void {
            $index = $this->findIndex($data['orders'], $id); $order =& $data['orders'][$index];
            if (!Payments::verified($order, $link)) throw new RuntimeException('Payment has not been confirmed by the provider yet.');
            if (in_array($order['payment_status'], ['paid', 'refund_pending', 'refunded'], true)) return;
            $order['payment_status'] = $order['status'] === 'cancelled' ? 'refund_pending' : 'paid';
            $this->notify($data, $order['user_id'], 'payment', 'Payment received for ' . $order['number'], 'Amount: INR ' . $order['grand_total'] . ($order['status'] === 'cancelled' ? '. Contact support for your refund.' : '. Your receipt is available in My orders.'));
        });
    }
    public function sendQueuedMail(callable $send): int
    {
        $token = bin2hex(random_bytes(16));
        $batch = $this->write(function (array &$data) use ($token): array {
            $batch = [];
            foreach ($data['notifications'] as &$notification) {
                if (empty($notification['mail_to']) || !empty($notification['mail_sent_at']) || ($notification['mail_lease_until'] ?? 0) > time()) continue;
                $notification['mail_lease'] = $token; $notification['mail_lease_until'] = time() + 900;
                $batch[] = $notification;
                if (count($batch) >= 20) break;
            }
            unset($notification); return $batch;
        });
        $count = 0;
        foreach ($batch as $message) {
            $success = false;
            try { $send($message['mail_to'], $message['title'], $message['body']); $success = true; $count++; }
            catch (Throwable $e) { /* Persist a retry without exposing provider credentials. */ }
            $this->write(function (array &$data) use ($message, $token, $success): void {
                $index = $this->findIndex($data['notifications'], $message['id']);
                if ($index === null || ($data['notifications'][$index]['mail_lease'] ?? '') !== $token) return;
                $notification =& $data['notifications'][$index];
                if ($success) { $notification['mail_sent_at'] = date('c'); unset($notification['mail_error']); }
                else $notification['mail_error'] = 'Delivery failed; retry scheduled.';
                $notification['mail_lease_until'] = $success ? 0 : time() + 300;
                unset($notification['mail_lease']);
            });
        }
        if ($count !== count($batch)) throw new RuntimeException('Some messages failed; they remain queued for retry.');
        return $count;
    }
}
