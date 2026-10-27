<?php
declare(strict_types=1);

require_once __DIR__ . '/app/bootstrap.php';
require_once __DIR__ . '/app/views.php';
require_once __DIR__ . '/app/features.php';
require_once __DIR__ . '/app/admin.php';

if (($_GET['page'] ?? '') === 'media' && in_array($_SERVER['REQUEST_METHOD'], ['GET', 'HEAD'], true)) {
    image_uploads()->serve(is_string($_GET['image'] ?? null) ? $_GET['image'] : '');
}

function post_return_page(string $fallback = 'home'): never
{
    $allowed = ['home', 'shop', 'cart', 'checkout', 'account', 'admin', 'delivery', 'login', 'register', 'profile'];
    $page = (string) ($_POST['return_page'] ?? $fallback);
    $page = in_array($page, $allowed, true) ? $page : $fallback;
    $parameters = [];
    if ($page === 'admin' && !empty($_POST['return_tab'])) {
        $parameters['tab'] = preg_replace('/[^a-z]/', '', (string) $_POST['return_tab']);
    }
    redirect($page, $parameters);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = (string) ($_POST['action'] ?? '');
    $uploadedImage = null;

    try {
        switch ($action) {
            case 'add_to_cart':
                $product = store()->product((int) ($_POST['product_id'] ?? 0));
                $quantity = max(1, (int) ($_POST['quantity'] ?? 1));
                if (!$product || !$product['is_active']) {
                    throw new RuntimeException('That product is no longer available.');
                }
                if ($product['stock'] <= 0) {
                    throw new RuntimeException($product['name'] . ' is currently out of stock.');
                }
                $cart = cart();
                $currentQuantity = (int) ($cart[$product['id']] ?? 0);
                $cart[$product['id']] = min((int) $product['stock'], $currentQuantity + $quantity);
                set_cart($cart);
                flash('success', $product['name'] . ' was added to your cart.');
                post_return_page('shop');

            case 'update_cart':
                $cart = cart();
                if (!empty($_POST['remove_id'])) {
                    unset($cart[(int) $_POST['remove_id']]);
                    flash('success', 'Item removed from your cart.');
                } else {
                    foreach ((array) ($_POST['quantity'] ?? []) as $productId => $quantity) {
                        $product = store()->product((int) $productId);
                        if (!$product) {
                            unset($cart[(int) $productId]);
                            continue;
                        }
                        $cart[(int) $productId] = min(max(0, (int) $quantity), (int) $product['stock']);
                    }
                    flash('success', 'Your cart quantities are up to date.');
                }
                set_cart($cart);
                post_return_page('cart');

            case 'login':
                $user = store()->authenticate((string) ($_POST['email'] ?? ''), (string) ($_POST['password'] ?? ''));
                if (!$user) {
                    throw new RuntimeException('We could not match that email and password.');
                }
                login_user($user);
                flash('success', 'Welcome back, ' . explode(' ', $user['name'])[0] . '.');
                $next = (string) ($_POST['next'] ?? '');
                $allowedNext = ['home', 'shop', 'cart', 'checkout', 'account', 'admin', 'delivery'];
                if ($user['role'] === 'admin') {
                    redirect('admin');
                }
                if ($user['role'] === 'delivery') {
                    redirect(in_array($next, ['home', 'shop', 'cart', 'checkout', 'account', 'profile'], true) ? $next : 'delivery');
                }
                redirect(in_array($next, $allowedNext, true) ? $next : 'account');

            case 'register':
                $name = trim((string) ($_POST['name'] ?? ''));
                $email = trim((string) ($_POST['email'] ?? ''));
                $phone = trim((string) ($_POST['phone'] ?? ''));
                $password = (string) ($_POST['password'] ?? '');
                if (mb_strlen($name) < 2 || !filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($phone) < 7 || mb_strlen($password) < 8) {
                    throw new RuntimeException('Please complete every field with a valid email and an 8-character password.');
                }
                $user = store()->register(compact('name', 'email', 'phone', 'password'));
                login_user($user);
                flash('success', 'Your player profile is ready. Welcome to ' . shop_name() . '!');
                redirect('account');

            case 'logout':
                logout_user();
                flash('success', 'You have been signed out safely.');
                redirect('home');

            case 'checkout':
                $user = require_shopper();
                foreach (['recipient', 'phone', 'address', 'city', 'postal_code'] as $field) {
                    if (trim((string) ($_POST[$field] ?? '')) === '') {
                        throw new RuntimeException('Please complete the delivery details before placing your order.');
                    }
                }
                if (!preg_match('/^\d{6}$/', (string) $_POST['postal_code'])) {
                    throw new RuntimeException('Please enter a valid 6-digit PIN code.');
                }
                $order = store()->createOrder($user, cart(), $_POST);
                set_cart([]);
                $_SESSION['last_order_id'] = $order['id'];
                flash('success', 'Order ' . $order['number'] . ' is confirmed. Your receipt is ready.');
                redirect('invoice', ['id' => $order['id']]);

            case 'assign_delivery':
                $actor = require_role('admin');
                $partnerId = (int) ($_POST['delivery_partner_id'] ?? 0);
                if (!$partnerId) {
                    throw new RuntimeException('Choose a delivery partner before assigning the order.');
                }
                $order = store()->assignDelivery((int) ($_POST['order_id'] ?? 0), $partnerId, $actor);
                flash('success', $order['number'] . ' was assigned for delivery.');
                post_return_page('admin');

            case 'update_order_status':
                $actor = require_login();
                if (!in_array($actor['role'], ['admin', 'delivery'], true)) {
                    throw new RuntimeException('Only the store team can update delivery statuses.');
                }
                $order = store()->updateOrderStatus(
                    (int) ($_POST['order_id'] ?? 0),
                    (string) ($_POST['status'] ?? ''),
                    $actor,
                    trim((string) ($_POST['note'] ?? '')),
                    trim((string) ($_POST['verification_code'] ?? ''))
                );
                flash('success', $order['number'] . ' is now ' . status_label($order['status']) . '.');
                post_return_page($actor['role'] === 'delivery' ? 'delivery' : 'admin');

            case 'save_product':
                require_role('admin');
                foreach (['name', 'sku', 'brand', 'description'] as $field) {
                    if (trim((string) ($_POST[$field] ?? '')) === '') {
                        throw new RuntimeException('Complete every product field before saving.');
                    }
                }
                if ((float) ($_POST['price'] ?? -1) < 0 || (float) ($_POST['cost'] ?? -1) < 0) {
                    throw new RuntimeException('Prices and costs cannot be negative.');
                }
                $attributes = $_POST;
                unset($attributes['image_path']);
                $uploadedImage = image_uploads()->receive('product_photo');
                if ($uploadedImage !== null) {
                    $attributes['image_path'] = $uploadedImage;
                } elseif (isset($_POST['default_photo'])) {
                    $attributes['image_path'] = null;
                }
                $product = store()->saveProduct($attributes);
                flash('success', $product['name'] . ' has been saved to the catalog.');
                post_return_page('admin');

            case 'adjust_stock':
                $actor = require_role('admin');
                $delta = (int) ($_POST['delta'] ?? 0);
                if ($delta === 0) {
                    throw new RuntimeException('Enter a non-zero stock adjustment.');
                }
                $product = store()->adjustStock((int) ($_POST['product_id'] ?? 0), $delta, (string) ($_POST['note'] ?? ''), $actor);
                flash('success', $product['name'] . ' now has ' . $product['stock'] . ' unit(s) in stock.');
                post_return_page('admin');

            case 'save_category':
                $actor = require_role('admin');
                $attributes = $_POST;
                unset($attributes['image_path']);
                $uploadedImage = image_uploads()->receive('category_photo');
                if ($uploadedImage !== null) $attributes['image_path'] = $uploadedImage;
                elseif (isset($_POST['default_photo'])) $attributes['image_path'] = null;
                store()->saveCategory($attributes, $actor);
                flash('success', 'Category saved. You can now add gear to this category.');
                redirect('admin', ['tab' => 'categories']);

            case 'update_admin_profile':
                $actor = require_role('admin');
                $updated = store()->updateAdminProfile($_POST, $actor);
                $_SESSION['auth_version'] = (int) ($updated['auth_version'] ?? 0);
                flash('success', 'Your administrator profile has been updated.');
                redirect('profile');

            case 'create_admin':
                $actor = require_role('admin');
                store()->createAdministrator($_POST, $actor);
                flash('success', 'New administrator account created.');
                redirect('admin', ['tab' => 'admins']);

            case 'manage_delivery_partner':
                $actor = require_role('admin');
                store()->manageDeliveryPartner((int) ($_POST['partner_id'] ?? 0), (string) ($_POST['operation'] ?? ''), (string) ($_POST['reason'] ?? ''), $actor);
                flash('success', 'Partner account updated. Any active deliveries are ready for reassignment.');
                redirect('admin', ['tab' => 'partners']);

            case 'update_customer_profile':
                $actor = require_role('customer');
                store()->updateCustomerProfile($_POST, $actor);
                flash('success', 'Your contact details have been updated.');
                redirect('profile');

            case 'update_delivery_partner':
                $actor = require_role('admin');
                $partner = store()->updateDeliveryPartner((int) ($_POST['partner_id'] ?? 0), $_POST, $actor);
                flash('success', 'Contact details updated for ' . $partner['name'] . '.');
                redirect('admin', ['tab' => 'partners']);

            case 'save_branding':
                $actor = require_role('admin');
                $attributes = $_POST;
                unset($attributes['shop_logo']);
                $uploadedImage = image_uploads()->receive('shop_logo_file');
                if ($uploadedImage !== null) {
                    $attributes['shop_logo'] = $uploadedImage;
                } elseif (isset($_POST['default_photo'])) {
                    $attributes['shop_logo'] = null;
                }
                store()->saveBranding($attributes, $actor);
                flash('success', 'Shop name, logo, and colours saved.');
                redirect('admin', ['tab' => 'settings']);

            case 'reset_branding':
                $actor = require_role('admin');
                store()->resetBranding($actor);
                flash('success', 'Default shop name, logo, and colours restored.');
                redirect('admin', ['tab' => 'settings']);

            case 'create_delivery_partner':
                $actor = require_role('admin');
                $partner = store()->createDeliveryPartner($_POST, $actor);
                flash('success', 'Delivery partner account created for ' . $partner['name'] . '.');
                redirect('admin', ['tab' => 'partners']);

            case 'add_income':
                $actor = require_role('admin');
                store()->addIncome($_POST, $actor);
                flash('success', 'External income recorded. Monthly profit has been recalculated.');
                redirect('admin', ['tab' => 'finance']);

            case 'update_profile_photo':
                $actor = require_login();
                $uploadedImage = image_uploads()->receive('profile_photo');
                $image = $uploadedImage ?? (isset($_POST['default_photo']) ? null : ($actor['profile_image'] ?? null));
                store()->updateProfileImage($actor, $image);
                flash('success', 'Your profile photo has been saved.');
                redirect('profile');

            case 'add_expense':
                $actor = require_role('admin');
                if ((float) ($_POST['amount'] ?? 0) <= 0) {
                    throw new RuntimeException('Enter an expense amount greater than zero.');
                }
                store()->addExpense($_POST, $actor);
                flash('success', 'Operating expense recorded. Monthly profit has been recalculated.');
                post_return_page('admin');

            default:
                throw new RuntimeException('That action is not available.');
        }
    } catch (Throwable $exception) {
        image_uploads()->discard($uploadedImage);
        if (!$exception instanceof RuntimeException || $exception instanceof JsonException) {
            error_log((string) $exception);
            flash('error', 'We could not save your changes. Please try again.');
        } else {
            flash('error', $exception->getMessage());
        }
        post_return_page(match ($action) { 'login' => 'login', 'register' => 'register', 'update_customer_profile' => 'profile', 'update_delivery_partner' => 'admin', 'save_branding' => 'admin', 'reset_branding' => 'admin', 'create_delivery_partner' => 'admin', 'add_income' => 'admin', 'update_profile_photo' => 'profile', default => 'home' });
    }
}

$page = (string) ($_GET['page'] ?? 'home');
$allowedPages = ['home', 'shop', 'cart', 'checkout', 'login', 'register', 'account', 'invoice', 'admin', 'delivery', 'profile'];
if (!in_array($page, $allowedPages, true)) {
    $page = 'home';
}

switch ($page) {
    case 'home':
        render_header('Sport equipment for every orbit', 'home');
        render_home();
        break;
    case 'shop':
        render_header('Shop equipment', 'shop');
        render_shop();
        break;
    case 'cart':
        render_header('Your cart', 'cart');
        render_cart_page();
        break;
    case 'checkout':
        $user = require_shopper();
        if (store()->cartDetails(cart())['items'] === []) {
            flash('warning', 'Add something to your cart before checking out.');
            redirect('cart');
        }
        render_header('Checkout', 'checkout');
        render_checkout_page($user);
        break;
    case 'login':
        if (current_user()) {
            redirect(current_user()['role'] === 'admin' ? 'admin' : (current_user()['role'] === 'delivery' ? 'delivery' : 'account'));
        }
        render_header('Sign in', 'login');
        render_login_page();
        break;
    case 'register':
        if (current_user()) {
            redirect('account');
        }
        render_header('Create your account', 'register');
        render_register_page();
        break;
    case 'account':
        $user = require_shopper();
        render_header('My player profile', 'account');
        render_account_page($user);
        break;
    case 'profile':
        $user = require_login();
        render_header('Your profile', 'profile');
        render_profile_page($user);
        break;
    case 'invoice':
        $user = require_login();
        $order = store()->order((int) ($_GET['id'] ?? ($_SESSION['last_order_id'] ?? 0)));
        if (!$order || !can_view_order($order, $user)) {
            flash('error', 'That order record is not available for your account.');
            redirect($user['role'] === 'admin' ? 'admin' : ($user['role'] === 'delivery' ? 'delivery' : 'account'));
        }
        render_header('Order receipt ' . $order['number'], 'invoice');
        render_invoice_page($order, isset($_GET['track']));
        break;
    case 'admin':
        $user = require_role('admin');
        render_header('Admin control room', 'admin');
        render_admin_page($user);
        break;
    case 'delivery':
        $user = require_role('delivery');
        render_header('Delivery partner hub', 'delivery');
        render_delivery_page($user);
        break;
}

render_footer();
