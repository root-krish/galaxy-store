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
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_name('sports_galaxy_session');
    session_set_cookie_params([
        'httponly' => true,
        'samesite' => 'Lax',
        'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    ]);
    session_start();
}

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self' blob: data:; object-src 'none'; base-uri 'none'; frame-ancestors 'none'; form-action 'self'");
header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') header('Strict-Transport-Security: max-age=31536000');
header('Referrer-Policy: same-origin');
header('Cache-Control: no-store');

require_once __DIR__ . '/ImageUploads.php';
require_once __DIR__ . '/Branding.php';
require_once __DIR__ . '/AdminOperations.php';
require_once __DIR__ . '/Payments.php';
require_once __DIR__ . '/Commerce.php';
require_once __DIR__ . '/Store.php';

function shop_settings(): array
{
    static $settings = null;
    return $settings ??= store()->settings();
}

function shop_name(): string
{
    return shop_settings()['shop_name'];
}

function image_uploads(): ImageUploads
{
    static $uploads = null;
    return $uploads ??= new ImageUploads(getenv('SG_UPLOAD_PATH') ?: SG_ROOT . '/storage/uploads');
}

function can_shop(array $user): bool
{
    return in_array($user['role'], ['customer', 'delivery'], true);
}

function require_shopper(): array
{
    $user = require_login();
    if (!can_shop($user)) {
        flash('error', 'Please use a customer or delivery partner account to place an order.');
        redirect('home');
    }
    return $user;
}


function store(): Store
{
    static $store = null;
    return $store ??= new Store(getenv('SG_DATA_PATH') ?: SG_ROOT . '/storage/data.json');
}

function h(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function url(string $page = 'home', array $parameters = []): string
{
    $parameters = array_filter(['page' => $page] + $parameters, static fn(mixed $value): bool => $value !== null && $value !== '');
    unset($parameters['page']);
    $path = $page === 'home' ? '/' : '/' . rawurlencode($page);
    if ($page === 'shop' && isset($parameters['category'])) {
        foreach (store()->categories() as $category) if ((int) $category['id'] === (int) $parameters['category']) { $path .= '/' . slug($category['name']) . '-' . $category['id']; unset($parameters['category']); break; }
    }
    if ($page === 'product' && isset($parameters['id']) && ($product = store()->product((int) $parameters['id']))) { $path .= '/' . slug($product['name']) . '-' . $product['id']; unset($parameters['id']); }
    return $path . ($parameters ? '?' . http_build_query($parameters) : '');
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
    $token = $_POST['csrf_token'] ?? null;
    if (!is_string($token) || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
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
    $user = $id ? store()->user($id) : null;
    return $user && store()->accountAllowed($user) && $user['is_active'] && empty($user['deleted_at'])
        && (int) ($_SESSION['auth_version'] ?? 0) === (int) ($user['auth_version'] ?? 0) ? $user : null;
}

function login_user(array $user): void
{
    session_regenerate_id(true);
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['auth_version'] = (int) ($user['auth_version'] ?? 0);
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

function slug(string $value): string { return trim(preg_replace('/[^a-z0-9]+/', '-', strtolower($value)), '-') ?: 'item'; }
