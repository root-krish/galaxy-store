<?php
declare(strict_types=1);
$path = trim(rawurldecode(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/'), '/');
if ($path !== '' && $path !== 'index.php') {
    $parts = explode('/', $path);
    $pages = ['shop','product','cart','checkout','login','register','account','invoice','admin','delivery','profile','wishlist','search','cart-data','media'];
    if (!in_array($parts[0], $pages, true) || count($parts) > 2 || (count($parts) === 2 && !in_array($parts[0], ['shop','product'], true))) { http_response_code(404); exit('Not found'); }
    $_GET['page'] = $parts[0];
    if (isset($parts[1])) {
        $found = false;
        $records = $parts[0] === 'shop' ? store()->categories() : store()->products();
        foreach ($records as $record) {
            if (in_array($parts[1], [slug($record['name']), slug($record['name']) . '-' . $record['id']], true)) {
                $_GET[$parts[0] === 'shop' ? 'category' : 'id'] = $record['id']; $found = true; break;
            }
        }
        if (!$found) { http_response_code(404); exit('Not found'); }
    }
}
function json_response(array $data, int $status = 200): never {
    http_response_code($status); header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_THROW_ON_ERROR); exit;
}
function cart_response(): array {
    $details = store()->cartDetails(cart());
    return ['count' => cart_count(), 'subtotal' => money($details['subtotal']), 'items' => array_map(static fn(array $item): array => ['name' => $item['product']['name'], 'quantity' => $item['quantity']], $details['items'])];
}
