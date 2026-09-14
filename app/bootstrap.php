<?php
declare(strict_types=1);

const SG_ROOT = __DIR__ . '/..';
const SG_NAME = 'Sports Galaxy';

date_default_timezone_set('Asia/Kolkata');

// The project also runs on lightweight classroom PHP installations where
// mbstring is not enabled. These fallbacks cover the simple names/emails used
// by the UI while full PHP deployments can use the native extension.
if (!function_exists('mb_strtolower')) {
    function mb_strtolower(string $value, ?string $encoding = null): string
    {
        return strtolower($value);
    }
}
if (!function_exists('mb_strtoupper')) {
    function mb_strtoupper(string $value, ?string $encoding = null): string
    {
        return strtoupper($value);
    }
}
if (!function_exists('mb_substr')) {
    function mb_substr(string $value, int $offset, ?int $length = null, ?string $encoding = null): string
    {
        return $length === null ? substr($value, $offset) : substr($value, $offset, $length);
    }
}
if (!function_exists('mb_strlen')) {
    function mb_strlen(string $value, ?string $encoding = null): int
    {
        return strlen($value);
    }
}

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_name('sports_galaxy_session');
    session_set_cookie_params([
        'httponly' => true,
        'samesite' => 'Lax',
        'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    ]);
    session_start();
}

require_once __DIR__ . '/Store.php';

function store(): Store
{
    static $store = null;
    return $store ??= new Store(SG_ROOT . '/storage/data.json');
}

function h(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function url(string $page = 'home', array $parameters = []): string
{
    $parameters = array_filter(['page' => $page] + $parameters, static fn(mixed $value): bool => $value !== null && $value !== '');
    return '?' . http_build_query($parameters);
}

function redirect(string $page = 'home', array $parameters = []): never
{
    header('Location: ' . url($page, $parameters));
    exit;
}

function csrf_token(): string
{
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . h(csrf_token()) . '">';
}

function verify_csrf(): void
{
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) {
        http_response_code(419);
        exit('Your form session expired. Please refresh the page and try again.');
    }
}

function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function pull_flashes(): array
{
    $flashes = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $flashes;
}

function current_user(): ?array
{
    $id = (int) ($_SESSION['user_id'] ?? 0);
    return $id ? store()->user($id) : null;
}

function login_user(array $user): void
{
    session_regenerate_id(true);
    $_SESSION['user_id'] = $user['id'];
}

function logout_user(): void
{
    unset($_SESSION['user_id']);
    session_regenerate_id(true);
}

function require_login(): array
{
    $user = current_user();
    if (!$user) {
        flash('warning', 'Please sign in to continue.');
        redirect('login', ['next' => $_GET['page'] ?? 'account']);
    }
    return $user;
}

function require_role(string $role): array
{
    $user = require_login();
    if ($user['role'] !== $role) {
        flash('error', 'That workspace is not available for your account.');
        redirect('home');
    }
    return $user;
}

function cart(): array
{
    return $_SESSION['cart'] ?? [];
}

function set_cart(array $cart): void
{
    $_SESSION['cart'] = array_filter($cart, static fn(int $quantity): bool => $quantity > 0);
}

function cart_count(): int
{
    return array_sum(array_map('intval', cart()));
}

function money(float|int $amount): string
{
    return '₹' . number_format((float) $amount, 2);
}

function category_name(int $categoryId): string
{
    foreach (store()->categories() as $category) {
        if ((int) $category['id'] === $categoryId) {
            return $category['name'];
        }
    }
    return 'Sports';
}

function status_label(string $status): string
{
    return ucwords(str_replace('_', ' ', $status));
}

function status_tone(string $status): string
{
    return match ($status) {
        'delivered', 'paid', 'in_stock' => 'success',
        'cancelled', 'out_of_stock', 'failed' => 'danger',
        'packed', 'out_for_delivery', 'assigned', 'confirmed' => 'info',
        default => 'warning',
    };
}

function can_view_order(array $order, array $user): bool
{
    return $user['role'] === 'admin'
        || (int) $order['user_id'] === (int) $user['id']
        || ($user['role'] === 'delivery' && (int) ($order['delivery_partner_id'] ?? 0) === (int) $user['id']);
}

function format_date(string $date, string $format = 'd M Y, h:i A'): string
{
    return date($format, strtotime($date));
}
