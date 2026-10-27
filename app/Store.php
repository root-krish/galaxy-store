<?php
declare(strict_types=1);

/**
 * A small, lock-protected JSON repository used for the zero-install demo.
 * The accompanying database/schema.sql mirrors these core entities for MySQL deployment.
 */
final class Store
{
    use AdminOperations;
    use Commerce;
    use FinanceReports;
    public function __construct(private readonly string $path)
    {
        $directory = dirname($this->path);
        if (!is_dir($directory)) {
            mkdir($directory, 0775, true);
        }
    }

    public function categories(): array
    {
        $data = $this->read();
        return $data['categories'];
    }

    public function products(?string $search = null, ?int $categoryId = null): array
    {
        $data = $this->read();
        $needle = mb_strtolower(trim((string) $search));
        $products = array_filter($data['products'], static function (array $product) use ($needle, $categoryId): bool {
            if (!$product['is_active']) {
                return false;
            }
            if ($categoryId && (int) $product['category_id'] !== $categoryId) {
                return false;
            }
            if ($needle === '') {
                return true;
            }
            $haystack = mb_strtolower($product['name'] . ' ' . $product['brand'] . ' ' . $product['description'] . ' ' . $product['sku']);
            foreach (preg_split('/\s+/u', $needle) as $term) if (!str_contains($haystack, $term)) return false;
            return true;
        });

        usort($products, static fn(array $a, array $b): int => ((int) $b['featured'] <=> (int) $a['featured']) ?: strcmp($a['name'], $b['name']));
        return array_values($products);
    }

    public function product(int $id): ?array
    {
        $data = $this->read();
        return $this->findById($data['products'], $id);
    }

    public function user(int $id): ?array
    {
        $data = $this->read();
        return $this->findById($data['users'], $id);
    }

    public function authenticate(string $email, string $password): ?array
    {
        return $this->write(function (array &$data) use ($email, $password): ?array {
            $now = time();
            $limits = array_filter($data['settings']['login_limits'] ?? [], static fn(array $entry): bool => $entry['until'] > $now);
            $keys = [hash('sha256', 'email:' . mb_strtolower(trim($email))), hash('sha256', 'ip:' . ($_SERVER['REMOTE_ADDR'] ?? 'cli'))];
            foreach ($keys as $key) if (($limits[$key]['count'] ?? 0) >= 20) return null;
            $matched = null;
            foreach ($data['users'] as $user) {
                if (mb_strtolower($user['email']) === mb_strtolower(trim($email)) && $user['is_active'] && $this->accountAllowed($user, $data)
                    && password_verify($password, $user['password_hash'])) { $matched = $user; break; }
            }
            foreach ($keys as $key) {
                if ($matched) unset($limits[$key]);
                else $limits[$key] = ['count' => ($limits[$key]['count'] ?? 0) + 1, 'until' => $limits[$key]['until'] ?? $now + 900];
            }
            $data['settings']['login_limits'] = array_slice($limits, -10000, null, true);
            return $matched;
        });
    }

    public function isDemoAccount(array $user): bool
    {
        return !empty($user['is_demo']) || (int) $user['id'] <= 3;
    }

    public function accountAllowed(array $user, ?array $data = null): bool
    {
        $data ??= $this->read();
        return !$this->isDemoAccount($user) || ($data['settings']['demo_access'] ?? '1') === '1';
    }

    public function wishlist(int $userId): array
    {
        return $this->user($userId)['wishlist'] ?? [];
    }

    public function toggleWishlist(array $actor, int $productId): bool
    {
        return $this->write(function (array &$data) use ($actor, $productId): bool {
            $index = $this->findIndex($data['users'], (int) $actor['id']);
            $product = $this->findById($data['products'], $productId);
            if ($index === null || !$data['users'][$index]['is_active'] || !$this->accountAllowed($data['users'][$index], $data) || !$product || !$product['is_active']) throw new RuntimeException('Item or account unavailable.');
            $list = $data['users'][$index]['wishlist'] ?? [];
            $saved = !in_array($productId, $list, true);
            $data['users'][$index]['wishlist'] = $saved ? [...$list, $productId] : array_values(array_diff($list, [$productId]));
            return $saved;
        });
    }

    public function register(array $attributes): array
    {
        // Public registration can never provision staff, even with a forged role field.
        return $this->createAccount($attributes, 'customer');
    }

    public function createDeliveryPartner(array $attributes, array $actor): array
    {
        return $this->createAccount($attributes, 'delivery', $actor);
    }

    private function requireAdmin(array $data, array $actor): void
    {
        $stored = $this->findById($data['users'], (int) ($actor['id'] ?? 0));
        if (!$stored || !$stored['is_active'] || $stored['role'] !== 'admin') {
            throw new RuntimeException('Only an administrator can perform this action.');
        }
    }

    private function createAccount(array $attributes, string $role, ?array $actor = null): array
    {
        return $this->write(function (array &$data) use ($attributes, $role, $actor): array {
            if ($role !== 'customer') {
                $this->requireAdmin($data, $actor ?? []);
            }
            $contact = $this->validatedContact($data, $attributes, 0);
            if (strlen((string) ($attributes['password'] ?? '')) < 8 || strlen((string) ($attributes['password'] ?? '')) > 72) {
                throw new RuntimeException('Use a password between 8 and 72 bytes.');
            }
            $id = $this->nextId($data['users']);
            $user = [
                'id' => $id,
                'name' => $contact['name'],
                'email' => $contact['email'],
                'phone' => $contact['phone'],
                'role' => $role,
                'profile_image' => null,
                'password_hash' => password_hash((string) $attributes['password'], PASSWORD_DEFAULT),
                'is_active' => true,
                'created_at' => date('Y-m-d H:i:s'),
                'created_by' => $actor['id'] ?? null,
            ];
            $data['users'][] = $user;
            $this->notify($data, $id, 'welcome', 'Welcome to ' . ($data['settings']['shop_name'] ?? SG_NAME), 'Your account is ready. Explore the latest sports essentials.');
            return $user;
        });
    }

    private function validatedContact(array $data, array $attributes, int $excludeId): array
    {
        $name = trim((string) ($attributes['name'] ?? ''));
        $email = mb_strtolower(trim((string) ($attributes['email'] ?? '')));
        $phone = trim((string) ($attributes['phone'] ?? ''));
        $digits = preg_replace('/[^0-9]/', '', $phone);
        if (mb_strlen($name) < 2 || mb_strlen($name) > 120 || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 190
            || preg_match('/^\+?[0-9 ()-]{7,30}$/D', $phone) !== 1 || strlen($digits) < 7 || strlen($digits) > 15) {
            throw new RuntimeException('Enter a name, valid email address, and a valid phone number.');
        }
        foreach ($data['users'] as $user) {
            if ((int) $user['id'] !== $excludeId && mb_strtolower($user['email']) === $email) {
                throw new RuntimeException('An account already exists for this email address.');
            }
        }
        return compact('name', 'email', 'phone');
    }

    public function updateCustomerProfile(array $attributes, array $actor): array
    {
        return $this->write(function (array &$data) use ($attributes, $actor): array {
            $index = $this->findIndex($data['users'], (int) ($actor['id'] ?? 0));
            if ($index === null || !$data['users'][$index]['is_active'] || $data['users'][$index]['role'] !== 'customer') {
                throw new RuntimeException('Only customers can edit their own contact details. Delivery partner details are managed by the administrator.');
            }
            $contact = $this->validatedContact($data, $attributes, (int) $data['users'][$index]['id']);
            $data['users'][$index] = array_replace($data['users'][$index], $contact);
            return $data['users'][$index];
        });
    }

    public function updateDeliveryPartner(int $partnerId, array $attributes, array $actor): array
    {
        return $this->write(function (array &$data) use ($partnerId, $attributes, $actor): array {
            $this->requireAdmin($data, $actor);
            $index = $this->findIndex($data['users'], $partnerId);
            if ($index === null || $data['users'][$index]['role'] !== 'delivery' || !empty($data['users'][$index]['deleted_at'])) {
                throw new RuntimeException('Delivery partner not found.');
            }
            $contact = $this->validatedContact($data, $attributes, $partnerId);
            $password = (string) ($attributes['password'] ?? '');
            if ($password !== '') {
                if (strlen($password) < 8 || strlen($password) > 72) {
                    throw new RuntimeException('Use a password between 8 and 72 bytes, or leave it empty to keep the current password.');
                }
                $contact['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
                $contact['auth_version'] = (int) ($data['users'][$index]['auth_version'] ?? 0) + 1;
            }
            $data['users'][$index] = array_replace($data['users'][$index], $contact);
            return $data['users'][$index];
        });
    }

    public function settings(): array
    {
        return array_replace(Branding::DEFAULTS, $this->read()['settings']);
    }

    public function saveBranding(array $attributes, array $actor): array
    {
        return $this->write(function (array &$data) use ($attributes, $actor): array {
            $this->requireAdmin($data, $actor);
            $current = array_replace(Branding::DEFAULTS, $data['settings']);
            $branding = Branding::validate(array_replace($current, array_intersect_key($attributes, Branding::DEFAULTS)));
            if ($branding['demo_access'] === '0' && $current['demo_access'] !== '0') {
                $safeAdmins = array_filter($data['users'], fn(array $u): bool => $u['role'] === 'admin' && $u['is_active'] && !$this->isDemoAccount($u));
                if (!$safeAdmins) throw new RuntimeException('Create a personal administrator account before disabling demo access.');
                foreach ($data['users'] as &$u) if ($this->isDemoAccount($u)) $u['auth_version'] = (int) ($u['auth_version'] ?? 0) + 1;
                unset($u);
            }
            $data['settings'] = array_replace($data['settings'], $branding);
            return $branding;
        });
    }

    public function resetBranding(array $actor): void
    {
        $this->write(function (array &$data) use ($actor): void {
            $this->requireAdmin($data, $actor);
            $demo = $data['settings']['demo_access'] ?? '1';
            $data['settings'] = array_replace($data['settings'], Branding::DEFAULTS, ['demo_access' => $demo]);
        });
    }

    public function quote(array $cart, string $coupon = ''): array
    {
        $data = $this->read();
        return $this->calculateTotals($data, $this->buildCart($data, $cart)['subtotal'], $coupon);
    }

    private function calculateTotals(array $data, float $subtotal, string $coupon): array
    {
        $coupon = mb_strtoupper(trim($coupon));
        if ($coupon !== '' && $coupon !== 'GALAXY10') {
            throw new RuntimeException('That offer code is not available. Try GALAXY10.');
        }
        $discount = $coupon === 'GALAXY10' ? round($subtotal * 0.10, 2) : 0.0;
        $taxable = max(0, $subtotal - $discount);
        $taxRate = (float) $data['settings']['tax_rate'];
        $tax = round($taxable * $taxRate / 100, 2);
        $shipping = $subtotal === 0.0 || $taxable >= (float) $data['settings']['free_shipping_at'] ? 0.0 : (float) $data['settings']['shipping_fee'];
        return ['subtotal' => $subtotal, 'discount' => $discount, 'tax_rate' => $taxRate,
            'tax' => $tax, 'shipping' => $shipping, 'grand_total' => round($taxable + $tax + $shipping, 2),
            'discount_name' => $discount > 0 ? 'GALAXY10 (10% off)' : null,
            'shipping_fee' => (float) $data['settings']['shipping_fee'],
            'free_shipping_at' => (float) $data['settings']['free_shipping_at']];
    }

    public function cartDetails(array $cart): array
    {
        $data = $this->read();
        return $this->buildCart($data, $cart);
    }

    public function createOrder(array $user, array $cart, array $checkout): array
    {
        return $this->write(function (array &$data) use ($user, $cart, $checkout): array {
            $user = $this->findById($data['users'], (int) ($user['id'] ?? 0));
            if (!$user || !in_array($user['role'], ['customer', 'delivery'], true) || !$user['is_active'] || !empty($user['deleted_at'])) {
                throw new RuntimeException('A customer or delivery partner account is required to place an order.');
            }
            $cartData = $this->buildCart($data, $cart);
            if (count($cartData['items']) !== count($cart)) {
                throw new RuntimeException('Some cart items are unavailable. Update your cart and try again.');
            }
            if ($cartData['items'] === []) {
                throw new RuntimeException('Your cart is empty.');
            }

            foreach ($cartData['items'] as $item) {
                if ($item['quantity'] > $item['product']['stock']) {
                    throw new RuntimeException($item['product']['name'] . ' no longer has enough stock.');
                }
            }

            $totals = $this->calculateTotals($data, $cartData['subtotal'], (string) ($checkout['coupon'] ?? ''));
            ['discount' => $discount, 'discount_name' => $discountName, 'tax_rate' => $taxRate,
                'tax' => $tax, 'shipping' => $shipping, 'grand_total' => $grandTotal] = $totals;
            $orderId = $this->nextId($data['orders']);
            $lines = [];

            foreach ($cartData['items'] as $item) {
                foreach ($data['products'] as &$product) {
                    if ((int) $product['id'] !== (int) $item['product']['id']) {
                        continue;
                    }
                    $product['stock'] -= $item['quantity'];
                    $lines[] = [
                        'product_id' => $product['id'],
                        'sku' => $product['sku'],
                        'name' => $product['name'],
                        'brand' => $product['brand'],
                        'visual' => $product['visual'],
                        'image_path' => $product['image_path'] ?? null,
                        'unit_price' => (float) $product['price'],
                        'unit_cost' => (float) $product['cost'],
                        'quantity' => $item['quantity'],
                        'line_total' => round($item['quantity'] * (float) $product['price'], 2),
                    ];
                    $data['inventory_movements'][] = [
                        'id' => $this->nextId($data['inventory_movements']),
                        'product_id' => $product['id'],
                        'type' => 'sale',
                        'quantity_delta' => -$item['quantity'],
                        'note' => 'Order SG-' . date('Ymd') . '-' . str_pad((string) $orderId, 4, '0', STR_PAD_LEFT),
                        'created_at' => date('Y-m-d H:i:s'),
                    ];
                    if ($product['stock'] < $product['min_stock']) {
                        $this->notify($data, null, 'low_stock', 'Low stock: ' . $product['name'], $product['stock'] . ' unit(s) remain. Restock before the next demand peak.');
                    }
                    unset($product);
                    break;
                }
            }

            $address = [
                'recipient' => trim((string) $checkout['recipient']),
                'phone' => trim((string) $checkout['phone']),
                'line1' => trim((string) $checkout['address']),
                'city' => trim((string) $checkout['city']),
                'postal_code' => trim((string) $checkout['postal_code']),
            ];
            $paymentMethod = in_array($checkout['payment_method'] ?? 'cod', ['cod', 'upi', 'card', 'razorpay'], true) ? ($checkout['payment_method'] ?? 'cod') : 'cod';
            if (in_array($paymentMethod, ['upi', 'card'], true) && ($data['settings']['demo_access'] ?? '1') !== '1') throw new RuntimeException('Demo payments are disabled.');
            if ($paymentMethod === 'razorpay' && !Payments::configured()) throw new RuntimeException('Online payments are not configured.');
            $now = date('Y-m-d H:i:s');
            $order = [
                'id' => $orderId,
                'number' => 'SG-' . date('Ymd') . '-' . str_pad((string) $orderId, 4, '0', STR_PAD_LEFT),
                'delivery_code' => (string) random_int(100000, 999999),
                'tracking_code' => 'TRK' . date('ymd') . str_pad((string) $orderId, 5, '0', STR_PAD_LEFT),
                'user_id' => $user['id'],
                'customer_name' => $user['name'],
                'customer_email' => $user['email'],
                'address' => $address,
                'lines' => $lines,
                'subtotal' => $cartData['subtotal'],
                'discount' => $discount,
                'discount_name' => $discountName,
                'tax_rate' => $taxRate,
                'tax' => $tax,
                'shipping' => $shipping,
                'grand_total' => $grandTotal,
                'payment_method' => $paymentMethod,
                'payment_status' => in_array($paymentMethod, ['cod', 'razorpay'], true) ? 'pending' : 'paid',
                'status' => 'placed',
                'cancellation_reason' => null,
                'delivery_partner_id' => null,
                'delivery_events' => [[
                    'status' => 'placed',
                    'note' => 'Order received and queued for confirmation.',
                    'at' => $now,
                    'actor' => $data['settings']['shop_name'] ?? SG_NAME,
                ]],
                'created_at' => $now,
                'updated_at' => $now,
            ];
            $data['orders'][] = $order;
            $this->notify($data, $user['id'], 'order', 'Order ' . $order['number'] . ' confirmed', 'Order receipt: INR ' . $order['grand_total'] . '. Payment: ' . $order['payment_status'] . '. Items: ' . implode(', ', array_map(static fn(array $line): string => $line['name'] . ' x ' . $line['quantity'], $lines)) . '. Track it with ' . $order['tracking_code'] . '.');
            $this->notify($data, null, 'new_order', 'New order ' . $order['number'], $user['name'] . ' placed an order for ₹' . number_format($grandTotal, 2) . '.');
            return $order;
        });
    }

    public function order(int $id): ?array
    {
        $data = $this->read();
        return $this->findById($data['orders'], $id);
    }

    public function ordersForUser(int $userId): array
    {
        $data = $this->read();
        $orders = array_values(array_filter($data['orders'], static fn(array $order): bool => (int) $order['user_id'] === $userId));
        usort($orders, static fn(array $a, array $b): int => strcmp($b['created_at'], $a['created_at']) ?: ((int) $b['id'] <=> (int) $a['id']));
        return $orders;
    }

    public function allOrders(): array
    {
        $data = $this->read();
        $orders = $data['orders'];
        usort($orders, static fn(array $a, array $b): int => strcmp($b['created_at'], $a['created_at']) ?: ((int) $b['id'] <=> (int) $a['id']));
        return $orders;
    }

    public function deliveryOrders(int $partnerId): array
    {
        $orders = array_filter($this->allOrders(), static fn(array $order): bool => (int) ($order['delivery_partner_id'] ?? 0) === $partnerId);
        return array_values($orders);
    }

    public function deliveryPartners(bool $includeInactive = false): array
    {
        $data = $this->read();
        return array_values(array_filter($data['users'], static fn(array $user): bool => $user['role'] === 'delivery' && ($includeInactive || $user['is_active']) && ($includeInactive || empty($user['deleted_at']))));
    }

    public function assignDelivery(int $orderId, int $partnerId, array $actor): array
    {
        return $this->write(function (array &$data) use ($orderId, $partnerId, $actor): array {
            $this->requireAdmin($data, $actor);
            $orderIndex = $this->findIndex($data['orders'], $orderId);
            $partner = $this->findById($data['users'], $partnerId);
            if ($actor['role'] !== 'admin' || $orderIndex === null || !$partner || !$partner['is_active'] || $partner['role'] !== 'delivery') {
                throw new RuntimeException('The order or delivery partner could not be found.');
            }
            $order =& $data['orders'][$orderIndex];
            if ($order['payment_method'] === 'razorpay' && $order['payment_status'] !== 'paid') throw new RuntimeException('Verify online payment before fulfillment.');
            if (!in_array($order['status'], ['placed', 'confirmed', 'packed', 'assigned'], true)) {
                throw new RuntimeException('Only open orders awaiting dispatch can be assigned.');
            }
            if ((int) $order['user_id'] === $partnerId) {
                throw new RuntimeException('Assign a different partner to deliver this partner’s personal purchase.');
            }
            $order['delivery_partner_id'] = $partnerId;
            $order['status'] = 'assigned';
            $order['updated_at'] = date('Y-m-d H:i:s');
            $order['delivery_events'][] = [
                'status' => 'assigned',
                'note' => 'Assigned to ' . $partner['name'] . ' for delivery.',
                'at' => $order['updated_at'],
                'actor' => $actor['name'],
            ];
            $this->notify($data, $order['user_id'], 'delivery', 'Delivery partner assigned', $partner['name'] . ' is assigned to order ' . $order['number'] . '.');
            $this->notify($data, $partnerId, 'delivery', 'New delivery assignment', $order['number'] . ' is ready for your queue.');
            return $order;
        });
    }

    public function updateOrderStatus(int $orderId, string $status, array $actor, string $note = '', string $verificationCode = ''): array
    {
        $allowed = ['confirmed', 'packed', 'out_for_delivery', 'delivered', 'cancelled'];
        if (!in_array($status, $allowed, true)) {
            throw new RuntimeException('That delivery status is not valid.');
        }

        return $this->write(function (array &$data) use ($orderId, $status, $actor, $note, $verificationCode): array {
            $storedActor = $this->findById($data['users'], (int) ($actor['id'] ?? 0));
            if (!$storedActor || !$storedActor['is_active'] || !empty($storedActor['deleted_at'])) throw new RuntimeException('Your account is unavailable.');
            $actor = $storedActor;
            $orderIndex = $this->findIndex($data['orders'], $orderId);
            if ($orderIndex === null) {
                throw new RuntimeException('Order not found.');
            }
            $order =& $data['orders'][$orderIndex];
            if ($actor['role'] === 'delivery' && ((int) ($order['delivery_partner_id'] ?? 0) !== (int) $actor['id'] || (int) $order['user_id'] === (int) $actor['id'])) {
                throw new RuntimeException('You can only update orders assigned to you.');
            }
            if ($actor['role'] === 'delivery' && !in_array($status, ['out_for_delivery', 'delivered'], true)) {
                throw new RuntimeException('Delivery partners may only mark an assigned item out for delivery or delivered.');
            }
            if ($order['status'] === 'delivered' || $order['status'] === 'cancelled') {
                throw new RuntimeException('This order is already closed.');
            }
            if ($status !== 'cancelled' && $order['payment_method'] === 'razorpay' && $order['payment_status'] !== 'paid') throw new RuntimeException('Verify online payment before fulfillment.');
            $transitions = [
                'placed' => ['confirmed', 'packed', 'cancelled'],
                'confirmed' => ['packed', 'cancelled'],
                'packed' => ['cancelled'],
                'assigned' => ['out_for_delivery', 'cancelled'],
                'out_for_delivery' => ['delivered', 'cancelled'],
            ];
            if (!in_array($actor['role'], ['admin', 'delivery'], true)
                || !in_array($status, $transitions[$order['status']] ?? [], true)) {
                throw new RuntimeException('That status change is not available for this order.');
            }
            if ($status === 'delivered' && !hash_equals((string) ($order['delivery_code'] ?? ''), $verificationCode)) {
                throw new RuntimeException('Enter the six-digit verification code shared by the customer.');
            }
            if ($status === 'cancelled') {
                $reason = trim($note);
                if ($reason === '' || mb_strlen($reason) > 500) {
                    throw new RuntimeException('Enter a reason for cancellation between 1 and 500 characters.');
                }
                if ($order['payment_method'] === 'razorpay' && $order['payment_status'] === 'pending' && !empty($order['payment_link_id'])) {
                    $linkPath = 'payment_links/' . rawurlencode($order['payment_link_id']);
                    $link = Payments::request('GET', $linkPath);
                    if (($link['status'] ?? '') === 'created') {
                        Payments::request('POST', $linkPath . '/cancel');
                    } elseif (!in_array($link['status'] ?? '', ['expired', 'cancelled'], true)) {
                        throw new RuntimeException('Verify the payment link status before cancelling this order. Paid or partially paid links cannot be cancelled.');
                    }
                }
                $order['cancellation_reason'] = $reason;
                foreach ($order['lines'] as $line) {
                    $productIndex = $this->findIndex($data['products'], (int) $line['product_id']);
                    if ($productIndex !== null) {
                        $data['products'][$productIndex]['stock'] += $line['quantity'];
                        $data['inventory_movements'][] = ['id' => $this->nextId($data['inventory_movements']),
                            'product_id' => $line['product_id'], 'type' => 'cancellation',
                            'quantity_delta' => $line['quantity'], 'note' => 'Cancelled ' . $order['number'],
                            'created_by' => $actor['name'], 'created_at' => date('Y-m-d H:i:s')];
                    }
                }
                if ($order['payment_status'] === 'paid') {
                    $order['payment_status'] = $order['payment_method'] === 'razorpay' ? 'refund_pending' : 'refunded';
                }
            }
            $order['status'] = $status;
            $order['updated_at'] = date('Y-m-d H:i:s');
            if ($status === 'delivered' && $order['payment_method'] === 'cod') {
                $order['payment_status'] = 'paid';
            }
            $order['delivery_events'][] = [
                'status' => $status,
                'note' => trim($note) !== '' ? trim($note) : $this->defaultStatusNote($status),
                'at' => $order['updated_at'],
                'actor' => $actor['name'],
            ];
            $this->notify($data, $order['user_id'], 'order', 'Order ' . $order['number'] . ' is ' . str_replace('_', ' ', $status), end($order['delivery_events'])['note']);
            $this->notify($data, null, 'order', 'Order ' . $order['number'] . ' is ' . str_replace('_', ' ', $status), end($order['delivery_events'])['note']);
            return $order;
        });
    }

    public function saveProduct(array $attributes): array
    {
        return $this->write(function (array &$data) use ($attributes): array {
            foreach (['name', 'sku', 'brand', 'description'] as $field) {
                if (trim((string) ($attributes[$field] ?? '')) === '') {
                    throw new RuntimeException('Complete every product field before saving.');
                }
            }
            foreach (['price', 'cost', 'stock', 'min_stock'] as $field) {
                $value = $attributes[$field] ?? null;
                if (!is_numeric($value) || !is_finite((float) $value) || (float) $value < ($field === 'min_stock' ? 1 : 0)
                    || (in_array($field, ['stock', 'min_stock'], true) && (float) $value != (int) $value)) {
                    throw new RuntimeException('Enter valid non-negative prices and whole stock quantities.');
                }
            }
            if (!$this->findById($data['categories'], (int) ($attributes['category_id'] ?? 0))) {
                throw new RuntimeException('Choose a valid product category.');
            }
            if (!empty($attributes['restock_date'])) {
                $this->validateDate((string) $attributes['restock_date']);
            }
            $id = (int) ($attributes['id'] ?? 0);
            $existingProduct = $id ? $this->findById($data['products'], $id) : null;
            $imagePath = array_key_exists('image_path', $attributes) ? $attributes['image_path'] : ($existingProduct['image_path'] ?? null);
            if ($imagePath !== null && (!is_string($imagePath) || !ImageUploads::isValidName($imagePath))) {
                throw new RuntimeException('Choose a valid uploaded product photo.');
            }
            $product = [
                'id' => $id ?: $this->nextId($data['products']),
                'sku' => mb_strtoupper(trim((string) $attributes['sku'])),
                'name' => trim((string) $attributes['name']),
                'brand' => trim((string) $attributes['brand']),
                'category_id' => (int) $attributes['category_id'],
                'description' => trim((string) $attributes['description']),
                'price' => round((float) $attributes['price'], 2),
                'cost' => round((float) $attributes['cost'], 2),
                'stock' => max(0, (int) $attributes['stock']),
                'min_stock' => max(1, (int) $attributes['min_stock']),
                'restock_date' => trim((string) ($attributes['restock_date'] ?? '')) ?: null,
                'visual' => $this->validatedIcon($attributes['visual'] ?? '⚽'),
                'image_path' => $imagePath,
                'featured' => isset($attributes['featured']),
                'is_active' => true,
            ];

            foreach ($data['products'] as $existing) {
                if ($existing['sku'] === $product['sku'] && (int) $existing['id'] !== $id) {
                    throw new RuntimeException('That SKU is already in use.');
                }
            }

            if ($id) {
                $index = $this->findIndex($data['products'], $id);
                if ($index === null) {
                    throw new RuntimeException('Product not found.');
                }
                $delta = $product['stock'] - $data['products'][$index]['stock'];
                $data['products'][$index] = $product;
            } else {
                $delta = $product['stock'];
                $data['products'][] = $product;
            }
            if ($delta !== 0) {
                $data['inventory_movements'][] = ['id' => $this->nextId($data['inventory_movements']),
                    'product_id' => $product['id'], 'type' => 'adjustment', 'quantity_delta' => $delta,
                    'note' => 'Product editor stock update', 'created_at' => date('Y-m-d H:i:s')];
            }
            return $product;
        });
    }

    public function adjustStock(int $productId, int $delta, string $note, array $actor): array
    {
        return $this->write(function (array &$data) use ($productId, $delta, $note, $actor): array {
            $index = $this->findIndex($data['products'], $productId);
            if ($index === null) {
                throw new RuntimeException('Product not found.');
            }
            $product =& $data['products'][$index];
            if ($product['stock'] + $delta < 0) {
                throw new RuntimeException('Stock cannot fall below zero.');
            }
            $product['stock'] += $delta;
            $data['inventory_movements'][] = [
                'id' => $this->nextId($data['inventory_movements']),
                'product_id' => $productId,
                'type' => $delta >= 0 ? 'restock' : 'adjustment',
                'quantity_delta' => $delta,
                'note' => trim($note) ?: 'Manual inventory adjustment',
                'created_by' => $actor['name'],
                'created_at' => date('Y-m-d H:i:s'),
            ];
            if ($product['stock'] < $product['min_stock']) {
                $this->notify($data, null, 'low_stock', 'Low stock: ' . $product['name'], $product['stock'] . ' unit(s) remain after adjustment.');
            }
            return $product;
        });
    }

    public function addExpense(array $attributes, array $actor): array
    {
        return $this->write(function (array &$data) use ($attributes, $actor): array {
            $this->requireAdmin($data, $actor);
            $this->validateDate((string) ($attributes['date'] ?? date('Y-m-d')));
            if (!is_numeric($attributes['amount'] ?? null) || !is_finite((float) $attributes['amount']) || round((float) $attributes['amount'], 2) <= 0) {
                throw new RuntimeException('Enter an expense amount greater than zero.');
            }
            $expense = [
                'id' => $this->nextId($data['expenses']),
                'date' => $attributes['date'] ?? date('Y-m-d'),
                'category' => trim($attributes['category']) ?: 'Operations',
                'description' => trim($attributes['description']) ?: 'Store operating expense',
                'amount' => round((float) $attributes['amount'], 2),
                'recorded_by' => $actor['name'],
                'created_at' => date('Y-m-d H:i:s'),
            ];
            $data['expenses'][] = $expense;
            return $expense;
        });
    }

    public function updateProfileImage(array $actor, ?string $image): array
    {
        if ($image !== null && !ImageUploads::isValidName($image)) {
            throw new RuntimeException('Choose a valid uploaded profile photo.');
        }
        return $this->write(function (array &$data) use ($actor, $image): array {
            // Always target the signed-in actor; there is no arbitrary target user parameter.
            $index = $this->findIndex($data['users'], (int) $actor['id']);
            if ($index === null || !$data['users'][$index]['is_active']) {
                throw new RuntimeException('Your account is unavailable.');
            }
            $data['users'][$index]['profile_image'] = $image;
            return $data['users'][$index];
        });
    }

    public function addIncome(array $attributes, array $actor): array
    {
        return $this->write(function (array &$data) use ($attributes, $actor): array {
            $this->requireAdmin($data, $actor);
            $this->validateDate((string) ($attributes['date'] ?? ''));
            if (!is_numeric($attributes['amount'] ?? null) || !is_finite((float) $attributes['amount'])
                || round((float) $attributes['amount'], 2) <= 0
                || trim((string) ($attributes['category'] ?? '')) === ''
                || trim((string) ($attributes['description'] ?? '')) === '') {
                throw new RuntimeException('Enter an income category, description, and amount greater than zero.');
            }
            $data['incomes'] ??= [];
            $income = ['id' => $this->nextId($data['incomes']), 'date' => $attributes['date'],
                'category' => trim($attributes['category']), 'description' => trim($attributes['description']),
                'amount' => round((float) $attributes['amount'], 2), 'recorded_by' => $actor['name'],
                'created_at' => date('Y-m-d H:i:s')];
            $data['incomes'][] = $income;
            return $income;
        });
    }

    public function incomes(): array
    {
        $data = $this->read();
        $incomes = $data['incomes'] ?? [];
        usort($incomes, static fn(array $a, array $b): int => strcmp($b['date'], $a['date']) ?: ($b['id'] <=> $a['id']));
        return $incomes;
    }

    public function expenses(): array
    {
        $data = $this->read();
        $expenses = $data['expenses'];
        usort($expenses, static fn(array $a, array $b): int => strcmp($b['date'], $a['date']));
        return $expenses;
    }

    public function notifications(?int $userId, int $limit = 8): array
    {
        $data = $this->read();
        $notifications = array_filter($data['notifications'], static fn(array $notification): bool => $userId === null ? $notification['user_id'] === null : (int) ($notification['user_id'] ?? 0) === $userId);
        usort($notifications, static fn(array $a, array $b): int => strcmp($b['created_at'], $a['created_at']) ?: ((int) $b['id'] <=> (int) $a['id']));
        return array_slice(array_values($notifications), 0, $limit);
    }

    public function dashboardStats(): array
    {
        $data = $this->read();
        $today = date('Y-m-d');
        $month = date('Y-m');
        $eligible = array_filter($data['orders'], static fn(array $order): bool => !in_array($order['status'], ['cancelled'], true));
        $todayOrders = array_filter($eligible, static fn(array $order): bool => str_starts_with($order['created_at'], $today));
        $monthOrders = array_filter($eligible, static fn(array $order): bool => str_starts_with($order['created_at'], $month));
        $dailyRevenue = array_sum(array_column($todayOrders, 'grand_total'));
        $monthlyRevenue = array_sum(array_column($monthOrders, 'grand_total'));
        $monthlyNetSales = array_sum(array_map(static fn(array $order): float => (float) ($order['subtotal'] - $order['discount']), $monthOrders));
        $weeklyRevenue = [];
        for ($days = 6; $days >= 0; $days--) {
            $day = date('Y-m-d', strtotime('-' . $days . ' days'));
            $weeklyRevenue[] = ['label' => date('D', strtotime($day)), 'date' => $day,
                'total' => array_sum(array_column(array_filter($eligible, static fn(array $order): bool => str_starts_with($order['created_at'], $day)), 'grand_total'))];
        }
        $monthlyCost = 0.0;
        $salesByProduct = [];
        foreach ($monthOrders as $order) {
            foreach ($order['lines'] as $line) {
                $monthlyCost += $line['unit_cost'] * $line['quantity'];
                $key = $line['name'];
                $salesByProduct[$key] = ($salesByProduct[$key] ?? 0) + $line['quantity'];
            }
        }
        $monthlyExpenses = array_sum(array_map(static fn(array $expense): float => str_starts_with($expense['date'], $month) ? (float) $expense['amount'] : 0.0, $data['expenses']));
        $monthlyIncome = array_sum(array_map(static fn(array $income): float => str_starts_with($income['date'], $month) ? (float) $income['amount'] : 0.0, $data['incomes'] ?? []));
        arsort($salesByProduct);
        $lowStock = array_values(array_filter($data['products'], static fn(array $product): bool => $product['stock'] < $product['min_stock']));
        return [
            'daily_revenue' => $dailyRevenue,
            'daily_orders' => count($todayOrders),
            'month_revenue' => $monthlyRevenue,
            'month_cost' => $monthlyCost,
            'month_expenses' => $monthlyExpenses,
            'month_income' => $monthlyIncome,
            'month_profit' => $monthlyNetSales + $monthlyIncome - $monthlyCost - $monthlyExpenses,
            'month_net_sales' => $monthlyNetSales,
            'weekly_revenue' => $weeklyRevenue,
            'low_stock' => $lowStock,
            'top_products' => array_slice($salesByProduct, 0, 5, true),
            'orders' => $eligible,
        ];
    }

    private function validateDate(string $value): void
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        if (!$date || $date->format('Y-m-d') !== $value) {
            throw new RuntimeException('Enter a valid date in YYYY-MM-DD format.');
        }
    }

    private function buildCart(array $data, array $cart): array
    {
        $items = [];
        $subtotal = 0.0;
        foreach ($cart as $productId => $quantity) {
            $product = $this->findById($data['products'], (int) $productId);
            $quantity = max(0, (int) $quantity);
            if (!$product || !$product['is_active'] || $quantity === 0) {
                continue;
            }
            $lineTotal = round($quantity * (float) $product['price'], 2);
            $items[] = [
                'product' => $product,
                'quantity' => $quantity,
                'line_total' => $lineTotal,
            ];
            $subtotal += $lineTotal;
        }
        return ['items' => $items, 'subtotal' => round($subtotal, 2)];
    }

    private function notify(array &$data, ?int $userId, string $type, string $title, string $body): void
    {
        $data['notifications'][] = [
            'id' => $this->nextId($data['notifications']),
            'user_id' => $userId,
            'mail_to' => $userId ? ($this->findById($data['users'], $userId)['email'] ?? '') : (getenv('SG_ADMIN_EMAIL') ?: ''),
            'type' => $type,
            'title' => $title,
            'body' => $body,
            'created_at' => date('Y-m-d H:i:s'),
        ];
    }

    private function defaultStatusNote(string $status): string
    {
        return match ($status) {
            'confirmed' => 'Payment and stock have been verified.',
            'packed' => 'Your sports essentials are packed and ready to dispatch.',
            'out_for_delivery' => 'Your delivery partner is on the way.',
            'delivered' => 'Order delivered successfully. Enjoy the game!',
            'cancelled' => 'Order was cancelled by the store team.',
            default => 'Order status updated.',
        };
    }

    private function findById(array $records, int $id): ?array
    {
        foreach ($records as $record) {
            if ((int) $record['id'] === $id) {
                return $record;
            }
        }
        return null;
    }

    private function findIndex(array $records, int $id): ?int
    {
        foreach ($records as $index => $record) {
            if ((int) $record['id'] === $id) {
                return $index;
            }
        }
        return null;
    }

    private function nextId(array $records): int
    {
        if ($records === []) {
            return 1;
        }
        return max(array_map(static fn(array $record): int => (int) $record['id'], $records)) + 1;
    }

    private function read(): array
    {
        return $this->access(false, static fn(array &$data): array => $data);
    }

    private function write(callable $callback): mixed
    {
        return $this->access(true, $callback);
    }

    private function access(bool $write, callable $callback): mixed
    {
        if (getenv('SG_DB_DSN')) {
            require_once __DIR__ . '/PdoRepository.php';
            return (new PdoRepository())->access($write, $callback);
        }
        // A separate lock remains stable while the data file is atomically replaced.
        $handle = fopen($this->path . '.lock', 'c+');
        if ($handle === false) {
            throw new RuntimeException('The local data store is temporarily unavailable.');
        }
        if (!flock($handle, LOCK_EX)) {
            fclose($handle);
            throw new RuntimeException('The local data store is temporarily unavailable.');
        }
        $temporary = null;
        try {
            $raw = is_file($this->path) ? file_get_contents($this->path) : '';
            if ($raw === false) {
                throw new RuntimeException('Unable to read the local data store.');
            }
            $data = $raw === '' ? $this->seed() : json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
            $migrated = false;
            foreach ($data['orders'] as &$order) {
                if (!isset($order['delivery_code'])) {
                    $order['delivery_code'] = (string) random_int(100000, 999999);
                    $migrated = true;
                }
            }
            unset($order);
            $result = $callback($data);
            if ($write || $raw === '' || $migrated) {
                $encoded = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
                $temporary = tempnam(dirname($this->path), '.sg-');
                if ($temporary === false || file_put_contents($temporary, $encoded) !== strlen($encoded)
                    || !rename($temporary, $this->path)) {
                    throw new RuntimeException('Unable to save store data. Check available disk space.');
                }
            }
            return $result;
        } finally {
            if (is_string($temporary) && is_file($temporary)) {
                unlink($temporary);
            }
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    private function seed(): array
    {
        $now = date('Y-m-d H:i:s');
        $yesterday = date('Y-m-d H:i:s', strtotime('-1 day 10:30'));
        $twoDaysAgo = date('Y-m-d H:i:s', strtotime('-2 days 15:45'));
        return [
            'settings' => [
                'tax_rate' => 5,
                'shipping_fee' => 79,
                'free_shipping_at' => 999,
            ],
            'categories' => [
                ['id' => 1, 'name' => 'Team Sports', 'icon' => '⚽', 'color' => 'lime'],
                ['id' => 2, 'name' => 'Cricket', 'icon' => '🏏', 'color' => 'violet'],
                ['id' => 3, 'name' => 'Fitness', 'icon' => '🏋️', 'color' => 'orange'],
                ['id' => 4, 'name' => 'Racquet Sports', 'icon' => '🏸', 'color' => 'blue'],
                ['id' => 5, 'name' => 'Outdoor', 'icon' => '🚴', 'color' => 'pink'],
            ],
            'users' => [
                ['id' => 1, 'name' => 'Aarav Mehta', 'email' => 'admin@sportsgalaxy.local', 'phone' => '+91 98765 40001', 'role' => 'admin', 'password_hash' => password_hash('Admin@123', PASSWORD_DEFAULT), 'is_active' => true, 'created_at' => $now],
                ['id' => 2, 'name' => 'Ananya Roy', 'email' => 'customer@sportsgalaxy.local', 'phone' => '+91 98765 40002', 'role' => 'customer', 'password_hash' => password_hash('Customer@123', PASSWORD_DEFAULT), 'is_active' => true, 'created_at' => $now],
                ['id' => 3, 'name' => 'Rohan Das', 'email' => 'delivery@sportsgalaxy.local', 'phone' => '+91 98765 40003', 'role' => 'delivery', 'password_hash' => password_hash('Delivery@123', PASSWORD_DEFAULT), 'is_active' => true, 'created_at' => $now],
            ],
            'products' => [
                ['id' => 1, 'sku' => 'SG-FB-001', 'name' => 'Astro Vision Football', 'brand' => 'NIVIA', 'category_id' => 1, 'description' => 'Match-ready hand-stitched football with a durable PU cover.', 'price' => 1299, 'cost' => 650, 'stock' => 14, 'min_stock' => 5, 'restock_date' => null, 'visual' => '⚽', 'featured' => true, 'is_active' => true],
                ['id' => 2, 'sku' => 'SG-CR-014', 'name' => 'Prolite English Willow Bat', 'brand' => 'SG', 'category_id' => 2, 'description' => 'Lightweight English willow bat with an extended sweet spot.', 'price' => 2899, 'cost' => 1600, 'stock' => 4, 'min_stock' => 5, 'restock_date' => date('Y-m-d', strtotime('+6 days')), 'visual' => '🏏', 'featured' => true, 'is_active' => true],
                ['id' => 3, 'sku' => 'SG-RN-021', 'name' => 'Swift Run X Trainers', 'brand' => 'PUMA', 'category_id' => 5, 'description' => 'Cushioned road-running trainers built for responsive daily miles.', 'price' => 3999, 'cost' => 2400, 'stock' => 0, 'min_stock' => 4, 'restock_date' => date('Y-m-d', strtotime('+10 days')), 'visual' => '🏃', 'featured' => true, 'is_active' => true],
                ['id' => 4, 'sku' => 'SG-BB-007', 'name' => 'Arena Grip Basketball', 'brand' => 'COSCO', 'category_id' => 1, 'description' => 'High-grip composite ball for indoor and outdoor courts.', 'price' => 1199, 'cost' => 620, 'stock' => 17, 'min_stock' => 5, 'restock_date' => null, 'visual' => '🏀', 'featured' => true, 'is_active' => true],
                ['id' => 5, 'sku' => 'SG-RC-006', 'name' => 'Velocity 700 Racket', 'brand' => 'YONEX', 'category_id' => 4, 'description' => 'Balanced graphite racket with a confidence-building control frame.', 'price' => 2499, 'cost' => 1250, 'stock' => 6, 'min_stock' => 5, 'restock_date' => null, 'visual' => '🏸', 'featured' => false, 'is_active' => true],
                ['id' => 6, 'sku' => 'SG-FN-011', 'name' => 'Pulse Flex Yoga Mat', 'brand' => 'CULT', 'category_id' => 3, 'description' => 'Non-slip 6 mm training mat with a carry strap.', 'price' => 899, 'cost' => 390, 'stock' => 25, 'min_stock' => 6, 'restock_date' => null, 'visual' => '🧘', 'featured' => false, 'is_active' => true],
                ['id' => 7, 'sku' => 'SG-CR-028', 'name' => 'Club Leather Cricket Ball', 'brand' => 'KOOKABURRA', 'category_id' => 2, 'description' => 'Four-piece leather ball for focused club practice sessions.', 'price' => 699, 'cost' => 310, 'stock' => 32, 'min_stock' => 8, 'restock_date' => null, 'visual' => '🏏', 'featured' => false, 'is_active' => true],
                ['id' => 8, 'sku' => 'SG-GM-013', 'name' => 'Impact Boxing Gloves', 'brand' => 'ADIDAS', 'category_id' => 3, 'description' => 'Protective 12 oz gloves with multi-layer foam cushioning.', 'price' => 1899, 'cost' => 870, 'stock' => 3, 'min_stock' => 5, 'restock_date' => date('Y-m-d', strtotime('+4 days')), 'visual' => '🥊', 'featured' => false, 'is_active' => true],
                ['id' => 9, 'sku' => 'SG-OD-019', 'name' => 'Trek Hydro Bottle', 'brand' => 'WILDCRAFT', 'category_id' => 5, 'description' => 'Insulated steel bottle that keeps your next adventure hydrated.', 'price' => 749, 'cost' => 290, 'stock' => 20, 'min_stock' => 6, 'restock_date' => null, 'visual' => '🚴', 'featured' => false, 'is_active' => true],
                ['id' => 10, 'sku' => 'SG-TN-004', 'name' => 'Baseline Tennis Set', 'brand' => 'HEAD', 'category_id' => 4, 'description' => 'Two starter rackets and three pressurised balls for weekend rallies.', 'price' => 2199, 'cost' => 1050, 'stock' => 8, 'min_stock' => 4, 'restock_date' => null, 'visual' => '🎾', 'featured' => false, 'is_active' => true],
                ['id' => 11, 'sku' => 'SG-FN-024', 'name' => 'Core Kettlebell 8 kg', 'brand' => 'PROTONER', 'category_id' => 3, 'description' => 'Powder-coated cast-iron kettlebell for strength and mobility work.', 'price' => 1599, 'cost' => 720, 'stock' => 11, 'min_stock' => 4, 'restock_date' => null, 'visual' => '🏋️', 'featured' => false, 'is_active' => true],
                ['id' => 12, 'sku' => 'SG-FB-031', 'name' => 'Goalkeeper Guard Kit', 'brand' => 'NIVIA', 'category_id' => 1, 'description' => 'Junior goalkeeper gloves and shin guards for confident saves.', 'price' => 1699, 'cost' => 800, 'stock' => 9, 'min_stock' => 4, 'restock_date' => null, 'visual' => '🥅', 'featured' => false, 'is_active' => true],
            ],
            'orders' => [
                [
                    'id' => 1, 'number' => 'SG-' . date('Ymd', strtotime('-2 days')) . '-0001', 'tracking_code' => 'TRK' . date('ymd', strtotime('-2 days')) . '00001',
                    'user_id' => 2, 'customer_name' => 'Ananya Roy', 'customer_email' => 'customer@sportsgalaxy.local',
                    'address' => ['recipient' => 'Ananya Roy', 'phone' => '+91 98765 40002', 'line1' => '18 Lake View Road', 'city' => 'Kolkata', 'postal_code' => '700029'],
                    'lines' => [
                        ['product_id' => 1, 'sku' => 'SG-FB-001', 'name' => 'Astro Vision Football', 'brand' => 'NIVIA', 'visual' => '⚽', 'unit_price' => 1299, 'unit_cost' => 650, 'quantity' => 1, 'line_total' => 1299],
                        ['product_id' => 6, 'sku' => 'SG-FN-011', 'name' => 'Pulse Flex Yoga Mat', 'brand' => 'CULT', 'visual' => '🧘', 'unit_price' => 899, 'unit_cost' => 390, 'quantity' => 1, 'line_total' => 899],
                    ],
                    'subtotal' => 2198, 'discount' => 219.8, 'discount_name' => 'GALAXY10 (10% off)', 'tax_rate' => 5, 'tax' => 98.91, 'shipping' => 0, 'grand_total' => 2077.11,
                    'payment_method' => 'upi', 'payment_status' => 'paid', 'status' => 'delivered', 'delivery_partner_id' => 3,
                    'delivery_events' => [
                        ['status' => 'placed', 'note' => 'Order received and queued for confirmation.', 'at' => $twoDaysAgo, 'actor' => 'Sports Galaxy'],
                        ['status' => 'packed', 'note' => 'Your sports essentials are packed and ready to dispatch.', 'at' => date('Y-m-d H:i:s', strtotime('-2 days 18:00')), 'actor' => 'Aarav Mehta'],
                        ['status' => 'out_for_delivery', 'note' => 'Your delivery partner is on the way.', 'at' => date('Y-m-d H:i:s', strtotime('-1 day 09:10')), 'actor' => 'Rohan Das'],
                        ['status' => 'delivered', 'note' => 'Order delivered successfully. Enjoy the game!', 'at' => $yesterday, 'actor' => 'Rohan Das'],
                    ],
                    'created_at' => $twoDaysAgo, 'updated_at' => $yesterday,
                ],
                [
                    'id' => 2, 'number' => 'SG-' . date('Ymd') . '-0002', 'tracking_code' => 'TRK' . date('ymd') . '00002',
                    'user_id' => 2, 'customer_name' => 'Ananya Roy', 'customer_email' => 'customer@sportsgalaxy.local',
                    'address' => ['recipient' => 'Ananya Roy', 'phone' => '+91 98765 40002', 'line1' => '18 Lake View Road', 'city' => 'Kolkata', 'postal_code' => '700029'],
                    'lines' => [
                        ['product_id' => 5, 'sku' => 'SG-RC-006', 'name' => 'Velocity 700 Racket', 'brand' => 'YONEX', 'visual' => '🏸', 'unit_price' => 2499, 'unit_cost' => 1250, 'quantity' => 1, 'line_total' => 2499],
                    ],
                    'subtotal' => 2499, 'discount' => 0, 'discount_name' => null, 'tax_rate' => 5, 'tax' => 124.95, 'shipping' => 0, 'grand_total' => 2623.95,
                    'payment_method' => 'cod', 'payment_status' => 'pending', 'status' => 'assigned', 'delivery_partner_id' => 3,
                    'delivery_events' => [
                        ['status' => 'placed', 'note' => 'Order received and queued for confirmation.', 'at' => date('Y-m-d H:i:s', strtotime('today 08:45')), 'actor' => 'Sports Galaxy'],
                        ['status' => 'assigned', 'note' => 'Assigned to Rohan Das for delivery.', 'at' => date('Y-m-d H:i:s', strtotime('today 10:20')), 'actor' => 'Aarav Mehta'],
                    ],
                    'created_at' => date('Y-m-d H:i:s', strtotime('today 08:45')), 'updated_at' => date('Y-m-d H:i:s', strtotime('today 10:20')),
                ],
            ],
            'incomes' => [],
            'expenses' => [
                ['id' => 1, 'date' => date('Y-m-d', strtotime('first day of this month')), 'category' => 'Store operations', 'description' => 'Monthly hosting and utilities', 'amount' => 2400, 'recorded_by' => 'Aarav Mehta', 'created_at' => $now],
                ['id' => 2, 'date' => date('Y-m-d', strtotime('-3 days')), 'category' => 'Delivery', 'description' => 'Local delivery fuel allowance', 'amount' => 680, 'recorded_by' => 'Aarav Mehta', 'created_at' => $now],
            ],
            'inventory_movements' => [],
            'notifications' => [
                ['id' => 1, 'user_id' => null, 'type' => 'low_stock', 'title' => '3 products need attention', 'body' => 'Prolite Bat, Swift Run X Trainers and Impact Boxing Gloves are below their set threshold.', 'created_at' => $now],
                ['id' => 2, 'user_id' => 2, 'type' => 'delivery', 'title' => 'Racket delivery assigned', 'body' => 'Rohan will bring SG-' . date('Ymd') . '-0002 to you today.', 'created_at' => $now],
            ],
        ];
    }
}
