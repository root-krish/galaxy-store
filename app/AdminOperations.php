<?php
declare(strict_types=1);

trait AdminOperations
{
    public function createAdministrator(array $attributes, array $actor): array
    {
        return $this->createAccount($attributes, 'admin', $actor);
    }

    public function administrators(): array
    {
        return array_values(array_filter($this->read()['users'], static fn(array $user): bool => $user['role'] === 'admin' && $user['is_active']));
    }

    public function updateAdminProfile(array $attributes, array $actor): array
    {
        return $this->write(function (array &$data) use ($attributes, $actor): array {
            $this->requireAdmin($data, $actor);
            $index = $this->findIndex($data['users'], (int) $actor['id']);
            $contact = $this->validatedContact($data, $attributes, (int) $actor['id']);
            $password = (string) ($attributes['password'] ?? '');
            if ($password !== '') {
                if (!password_verify((string) ($attributes['current_password'] ?? ''), $data['users'][$index]['password_hash'])) {
                    throw new RuntimeException('Enter your current password to choose a new password.');
                }
                if (strlen($password) < 8 || strlen($password) > 72) {
                    throw new RuntimeException('Use a new password between 8 and 72 bytes.');
                }
                $contact['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
                $contact['auth_version'] = (int) ($data['users'][$index]['auth_version'] ?? 0) + 1;
            }
            $data['users'][$index] = array_replace($data['users'][$index], $contact);
            return $data['users'][$index];
        });
    }

    public function manageDeliveryPartner(int $partnerId, string $operation, string $reason, array $actor): array
    {
        return $this->write(function (array &$data) use ($partnerId, $operation, $reason, $actor): array {
            $this->requireAdmin($data, $actor);
            $index = $this->findIndex($data['users'], $partnerId);
            if ($index === null || $data['users'][$index]['role'] !== 'delivery' || !empty($data['users'][$index]['deleted_at'])) {
                throw new RuntimeException('This delivery partner is unavailable.');
            }
            if (!in_array($operation, ['block', 'restore', 'delete'], true)) {
                throw new RuntimeException('Choose a valid account action.');
            }
            $reason = trim($reason);
            if (mb_strlen($reason) < 5 || mb_strlen($reason) > 500) {
                throw new RuntimeException('Explain the account action in 5 to 500 characters.');
            }
            $partner =& $data['users'][$index];
            if (($operation === 'block' && !$partner['is_active']) || ($operation === 'restore' && $partner['is_active'])) {
                throw new RuntimeException('This account already has that status.');
            }
            $now = date('Y-m-d H:i:s');
            $partner['is_active'] = $operation === 'restore';
            $partner['auth_version'] = (int) ($partner['auth_version'] ?? 0) + 1;
            $partner['account_reason'] = $reason;
            if ($operation === 'delete') $partner['deleted_at'] = $now;
            $partner['account_events'][] = ['action' => $operation, 'reason' => $reason, 'at' => $now, 'actor' => $actor['name'], 'actor_id' => $actor['id']];
            if ($operation !== 'restore') {
                foreach ($data['orders'] as &$order) {
                    if ((int) ($order['delivery_partner_id'] ?? 0) !== $partnerId || in_array($order['status'], ['delivered', 'cancelled'], true)) continue;
                    $order['delivery_partner_id'] = null;
                    $order['status'] = 'packed';
                    $order['updated_at'] = $now;
                    $order['delivery_events'][] = ['status' => 'packed', 'note' => 'Delivery assignment withdrawn. The store is arranging a new partner.', 'at' => $now, 'actor' => $actor['name']];
                    $this->notify($data, null, 'delivery', 'Reassign ' . $order['number'], 'A delivery partner account was ' . ($operation === 'block' ? 'blocked' : 'deleted') . '. This order is ready for reassignment.');
                }
                unset($order);
            }
            return $partner;
        });
    }

    public function category(int $id): ?array
    {
        return $this->findById($this->read()['categories'], $id);
    }

    private function validatedIcon(mixed $value): string
    {
        $icon = trim((string) $value);
        if ($icon === '' || mb_strlen($icon) > 16 || preg_match('/[<>\x00-\x1f]/u', $icon)) {
            throw new RuntimeException('Choose an icon or enter a short symbol (up to 16 characters).');
        }
        return $icon;
    }

    public function saveCategory(array $attributes, array $actor): array
    {
        return $this->write(function (array &$data) use ($attributes, $actor): array {
            $this->requireAdmin($data, $actor);
            $id = (int) ($attributes['id'] ?? 0);
            $existing = $id ? $this->findById($data['categories'], $id) : null;
            if ($id && !$existing) throw new RuntimeException('Category not found.');
            $name = trim((string) ($attributes['name'] ?? ''));
            if (mb_strlen($name) < 2 || mb_strlen($name) > 100) throw new RuntimeException('Use a category name between 2 and 100 characters.');
            foreach ($data['categories'] as $category) {
                if ((int) $category['id'] !== $id && mb_strtolower($category['name']) === mb_strtolower($name)) throw new RuntimeException('That category name already exists.');
            }
            $color = (string) ($attributes['color'] ?? 'lime');
            if (!in_array($color, ['lime', 'violet', 'orange', 'blue', 'pink'], true)) throw new RuntimeException('Choose a category tile colour.');
            $image = array_key_exists('image_path', $attributes) ? $attributes['image_path'] : ($existing['image_path'] ?? null);
            if ($image !== null && (!is_string($image) || !ImageUploads::isValidName($image))) throw new RuntimeException('Choose a valid category photo.');
            $category = ['id' => $id ?: $this->nextId($data['categories']), 'name' => $name, 'icon' => $this->validatedIcon($attributes['icon'] ?? '✦'), 'color' => $color, 'image_path' => $image];
            if ($id) $data['categories'][$this->findIndex($data['categories'], $id)] = $category;
            else $data['categories'][] = $category;
            return $category;
        });
    }
}
