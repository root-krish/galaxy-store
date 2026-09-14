<?php
declare(strict_types=1);

/**
 * A small, lock-protected JSON repository used for the zero-install demo.
 * The accompanying database/schema.sql mirrors these core entities for MySQL deployment.
 */
final class Store
{
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
            return str_contains($haystack, $needle);
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
        $data = $this->read();
        foreach ($data['users'] as $user) {
            if (mb_strtolower($user['email']) === mb_strtolower(trim($email))
                && $user['is_active']
                && password_verify($password, $user['password_hash'])) {
                return $user;
            }
        }
        return null;
    }

    public function register(array $attributes): array
    {
        return $this->write(function (array &$data) use ($attributes): array {
            $email = mb_strtolower(trim((string) $attributes['email']));
            foreach ($data['users'] as $user) {
                if ($user['email'] === $email) {
                    throw new RuntimeException('An account already exists for this email address.');
                }
            }

            $id = $this->nextId($data['users']);
            $user = [
                'id' => $id,
                'name' => trim((string) $attributes['name']),
                'email' => $email,
                'phone' => trim((string) $attributes['phone']),
                'role' => 'customer',
                'password_hash' => password_hash((string) $attributes['password'], PASSWORD_DEFAULT),
                'is_active' => true,
                'created_at' => date('Y-m-d H:i:s'),
            ];
            $data['users'][] = $user;
            $this->notify($data, $id, 'welcome', 'Welcome to Sports Galaxy', 'Your account is ready. Explore the latest sports essentials.');
            return $user;
        });
    }

    public function cartDetails(array $cart): array
    {
        $data = $this->read();
        return $this->buildCart($data, $cart);
    }

    public function createOrder(array $user, array $cart, array $checkout): array
    {
        return $this->write(function (array &$data) use ($user, $cart, $checkout): array {
            $cartData = $this->buildCart($data, $cart);
            if ($cartData['items'] === []) {
                throw new RuntimeException('Your cart is empty.');
            }

            foreach ($cartData['items'] as $item) {
                if ($item['quantity'] > $item['product']['stock']) {
                    throw new RuntimeException($item['product']['name'] . ' no longer has enough stock.');
                }
            }

            $coupon = mb_strtoupper(trim((string) ($checkout['coupon'] ?? '')));
            $discount = 0.0;
            $discountName = null;
            if ($coupon === 'GALAXY10') {
                $discount = round($cartData['subtotal'] * 0.10, 2);
                $discountName = 'GALAXY10 (10% off)';
            } elseif ($coupon !== '') {
                throw new RuntimeException('That offer code is not available. Try GALAXY10.');
            }

            $taxRate = (float) $data['settings']['tax_rate'];
            $taxable = max(0, $cartData['subtotal'] - $discount);
            $tax = round($taxable * $taxRate / 100, 2);
            $shipping = $taxable >= (float) $data['settings']['free_shipping_at'] ? 0.0 : (float) $data['settings']['shipping_fee'];
            $grandTotal = round($taxable + $tax + $shipping, 2);
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
            $paymentMethod = in_array($checkout['payment_method'], ['cod', 'upi', 'card'], true) ? $checkout['payment_method'] : 'cod';
            $now = date('Y-m-d H:i:s');
            $order = [
                'id' => $orderId,
                'number' => 'SG-' . date('Ymd') . '-' . str_pad((string) $orderId, 4, '0', STR_PAD_LEFT),
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
                'payment_status' => $paymentMethod === 'cod' ? 'pending' : 'paid',
                'status' => 'placed',
                'delivery_partner_id' => null,
                'delivery_events' => [[
                    'status' => 'placed',
                    'note' => 'Order received and queued for confirmation.',
                    'at' => $now,
                    'actor' => 'Sports Galaxy',
                ]],
                'created_at' => $now,
                'updated_at' => $now,
            ];
            $data['orders'][] = $order;
            $this->notify($data, $user['id'], 'order', 'Order ' . $order['number'] . ' confirmed', 'We received your order. Track it with ' . $order['tracking_code'] . '.');
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
        usort($orders, static fn(array $a, array $b): int => strcmp($b['created_at'], $a['created_at']));
        return $orders;
    }

    public function allOrders(): array
    {
        $data = $this->read();
        $orders = $data['orders'];
        usort($orders, static fn(array $a, array $b): int => strcmp($b['created_at'], $a['created_at']));
        return $orders;
    }

    public function deliveryOrders(int $partnerId): array
    {
        $orders = array_filter($this->allOrders(), static fn(array $order): bool => (int) ($order['delivery_partner_id'] ?? 0) === $partnerId);
        return array_values($orders);
    }

    public function deliveryPartners(): array
    {
        $data = $this->read();
        return array_values(array_filter($data['users'], static fn(array $user): bool => $user['role'] === 'delivery'));
    }

    public function assignDelivery(int $orderId, int $partnerId, array $actor): array
    {
        return $this->write(function (array &$data) use ($orderId, $partnerId, $actor): array {
            $orderIndex = $this->findIndex($data['orders'], $orderId);
            $partner = $this->findById($data['users'], $partnerId);
            if ($orderIndex === null || !$partner || $partner['role'] !== 'delivery') {
                throw new RuntimeException('The order or delivery partner could not be found.');
            }
            $order =& $data['orders'][$orderIndex];
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

    public function updateOrderStatus(int $orderId, string $status, array $actor, string $note = ''): array
    {
        $allowed = ['confirmed', 'packed', 'out_for_delivery', 'delivered', 'cancelled'];
        if (!in_array($status, $allowed, true)) {
            throw new RuntimeException('That delivery status is not valid.');
        }

        return $this->write(function (array &$data) use ($orderId, $status, $actor, $note): array {
            $orderIndex = $this->findIndex($data['orders'], $orderId);
            if ($orderIndex === null) {
                throw new RuntimeException('Order not found.');
            }
            $order =& $data['orders'][$orderIndex];
            if ($actor['role'] === 'delivery' && (int) ($order['delivery_partner_id'] ?? 0) !== (int) $actor['id']) {
                throw new RuntimeException('You can only update orders assigned to you.');
            }
            if ($actor['role'] === 'delivery' && !in_array($status, ['out_for_delivery', 'delivered'], true)) {
                throw new RuntimeException('Delivery partners may only mark an assigned item out for delivery or delivered.');
            }
            if ($order['status'] === 'delivered' || $order['status'] === 'cancelled') {
                throw new RuntimeException('This order is already closed.');
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
            return $order;
        });
    }

    public function saveProduct(array $attributes): array
    {
        return $this->write(function (array &$data) use ($attributes): array {
            $id = (int) ($attributes['id'] ?? 0);
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
                'visual' => in_array($attributes['visual'] ?? '', ['⚽', '🏏', '🏀', '🏸', '🎾', '🏃', '🧘', '🏋️', '🥊', '🚴'], true) ? $attributes['visual'] : '⚽',
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
                $data['products'][$index] = $product;
            } else {
                $data['products'][] = $product;
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
            $expense = [
                'id' => $this->nextId($data['expenses']),
                'date' => $attributes['date'] ?: date('Y-m-d'),
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
        $notifications = array_filter($data['notifications'], static fn(array $notification): bool => $notification['user_id'] === null || (int) $notification['user_id'] === $userId);
        usort($notifications, static fn(array $a, array $b): int => strcmp($b['created_at'], $a['created_at']));
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
        arsort($salesByProduct);
        $lowStock = array_values(array_filter($data['products'], static fn(array $product): bool => $product['stock'] < $product['min_stock']));
        return [
            'daily_revenue' => $dailyRevenue,
            'daily_orders' => count($todayOrders),
            'month_revenue' => $monthlyRevenue,
            'month_cost' => $monthlyCost,
            'month_expenses' => $monthlyExpenses,
            'month_profit' => $monthlyRevenue - $monthlyCost - $monthlyExpenses,
            'low_stock' => $lowStock,
            'top_products' => array_slice($salesByProduct, 0, 5, true),
            'orders' => $eligible,
        ];
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
        $handle = fopen($this->path, 'c+');
        // Use one exclusive lock for the short JSON transaction. On Windows,
        // seeding an empty file while holding a shared lock is rejected.
        if ($handle === false || !flock($handle, LOCK_EX)) {
            throw new RuntimeException('The local data store is temporarily unavailable.');
        }
        try {
            rewind($handle);
            $raw = stream_get_contents($handle);
            $data = $raw === '' ? $this->seed() : json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
            $result = $callback($data);
            if ($write || $raw === '') {
                rewind($handle);
                ftruncate($handle, 0);
                fwrite($handle, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
                fflush($handle);
            }
            return $result;
        } finally {
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
