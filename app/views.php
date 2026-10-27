<?php
declare(strict_types=1);

function render_header(string $title, string $page): void
{
    $user = current_user();
    $flashes = pull_flashes();
    ?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="<?= h(shop_settings()['text_color']) ?>">
    <meta property="og:title" content="<?= h($title . ' · ' . shop_name()) ?>">
    <meta property="og:type" content="<?= $page === 'product' ? 'product' : 'website' ?>">
    <meta property="og:image" content="<?= h(rtrim(getenv('SG_PUBLIC_URL') ?: 'http://localhost:8000', '/') . (!empty($GLOBALS['og_image']) ? $GLOBALS['og_image'] : '/assets/images/gear.svg')) ?>">
    <title><?= h($title) ?> · <?= h(shop_name()) ?></title>
    <link rel="stylesheet" href="/assets/css/app.css">
    <style data-store-theme>:root {<?php foreach (Branding::cssVariables(shop_settings()) as $key => $value): ?><?= h($key) ?>:<?= h($value) ?>;<?php endforeach; ?>}</style>
</head>
<body class="cards-<?= h(shop_settings()['card_style']) ?> catalogue-cols-<?= h(shop_settings()['catalogue_columns']) ?>">
<div class="site-shell">
    <?php if (shop_settings()['announcement_text'] !== ''): ?><div class="announcement"><span class="announce-dot"></span><span><?= h(shop_settings()['announcement_text']) ?></span><span class="announcement-right">Secure checkout ✦ Live order tracking</span></div><?php endif; ?>
    <header class="site-header">
        <div class="nav-wrap">
            <a class="brand" href="<?= h(url('home')) ?>" aria-label="<?= h(shop_name()) ?> home">
                <?= brand_markup() ?>
            </a>
            <button class="menu-toggle" type="button" aria-label="Toggle navigation" aria-expanded="false" data-menu-toggle>
                <span></span><span></span><span></span>
            </button>
            <nav class="main-nav" data-main-nav>
                <a class="<?= $page === 'home' ? 'active' : '' ?>" href="<?= h(url('home')) ?>">Home</a>
                <a class="<?= $page === 'shop' ? 'active' : '' ?>" href="<?= h(url('shop')) ?>">Shop</a>
                <?php if (shop_settings()['show_story'] === '1'): ?><a href="<?= h(url('home')) ?>#why-galaxy">Why Galaxy</a><?php endif; ?>
                <?php if ($user && can_shop($user)): ?>
                    <a class="<?= $page === 'account' ? 'active' : '' ?>" href="<?= h(url('account')) ?>">My orders</a>
                <?php endif; ?>
                <?php if ($user && $user['role'] === 'delivery'): ?><a href="<?= h(url('delivery')) ?>">Delivery hub</a><?php endif; ?>
                <?php if ($user): ?><a href="/wishlist">Wishlist</a><a href="<?= h(url('profile')) ?>">Profile</a><?php endif; ?>
            </nav>
            <form action="/shop" method="get" class="header-search" role="search"><label class="sr-only" for="header-search">Search equipment</label><input id="header-search" name="q" placeholder="Search gear or brands" autocomplete="off" aria-controls="search-suggestions"><ul id="search-suggestions" hidden aria-label="Search suggestions"></ul></form>
            <div class="nav-actions">
                <a class="cart-link <?= $page === 'cart' ? 'active' : '' ?>" href="<?= h(url('cart')) ?>" aria-label="Shopping cart">
                    <span class="cart-icon"><?= cart_icon() ?></span>
                    <span class="cart-label">Cart</span>
                    <span class="cart-count"><?= cart_count() ?></span>
                </a>
                <?php if ($user): ?>
                    <?php $workspace = $user['role'] === 'admin' ? 'admin' : ($user['role'] === 'delivery' ? 'delivery' : 'account'); ?>
                    <a class="user-link" href="<?= h(url($workspace)) ?>" aria-label="<?= h($user['name']) ?> workspace">
                        <span class="avatar"><?= avatar_content($user) ?></span>
                        <span class="user-link-copy"><small><?= h(ucfirst($user['role'])) ?></small><?= h(explode(' ', $user['name'])[0]) ?></span>
                    </a>
                    <form class="inline-form logout-form" method="post">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="logout">
                        <button class="icon-button" type="submit" title="Sign out" aria-label="Sign out">↪</button>
                    </form>
                <?php else: ?>
                    <a class="sign-in-link" href="<?= h(url('login')) ?>">Sign in</a>
                    <a class="button button-small button-primary" href="<?= h(url('register')) ?>">Join the team</a>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <?php if ($flashes): ?>
        <div class="flash-stack" aria-live="polite">
            <?php foreach ($flashes as $flash): ?>
                <div class="flash flash-<?= h($flash['type']) ?>">
                    <span><?= $flash['type'] === 'success' ? '✓' : ($flash['type'] === 'error' ? '!' : 'i') ?></span>
                    <p><?= h($flash['message']) ?></p>
                    <button type="button" aria-label="Dismiss" data-dismiss-flash>×</button>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    <main>
    <?php
}

function render_footer(): void
{
    ?>
    </main>
    <footer class="site-footer">
        <div class="footer-grid">
            <div class="footer-brand">
                <a class="brand" href="<?= h(url('home')) ?>">
                    <?= brand_markup() ?>
                </a>
                <p><?= h(shop_settings()['footer_description']) ?></p>
                <div class="social-row"><span>◎</span><span>◌</span><span>◈</span></div>
            </div>
            <div>
                <h3>Explore</h3>
                <a href="<?= h(url('shop')) ?>">All equipment</a>
                <?php foreach (array_slice(store()->categories(), 0, 2) as $category): ?><a href="<?= h(url('shop', ['category' => $category['id']])) ?>"><?= h($category['name']) ?></a><?php endforeach; ?>
            </div>
            <div>
                <h3>Service</h3>
                <?php if (shop_settings()['support_email'] !== ''): ?><a href="mailto:<?= h(shop_settings()['support_email']) ?>"><?= h(shop_settings()['support_email']) ?></a><?php endif; ?>
                <?php if (shop_settings()['support_phone'] !== ''): ?><a href="tel:<?= h(preg_replace('/[^+0-9]/', '', shop_settings()['support_phone'])) ?>"><?= h(shop_settings()['support_phone']) ?></a><?php endif; ?>
                <a href="<?= h(url('cart')) ?>">Your cart</a>
                <a href="<?= h(url('login')) ?>">Order tracking</a>
                <?php if (shop_settings()['show_story'] === '1'): ?><a href="<?= h(url('home')) ?>#why-galaxy">Delivery promise</a><?php endif; ?>
            </div>
            <div class="newsletter">
                <h3>Your next game</h3>
                <p>Keep your orders, receipts, and delivery updates together.</p>
                <a href="<?= h(url('account')) ?>">Open your player profile →</a>
            </div>
        </div>
        <div class="footer-bottom">
            <span>© <?= date('Y') ?> <?= h(shop_name()) ?></span>
            <span>Made for the love of the game <b>✦</b></span>
            <span>Demo payment &amp; local data mode</span>
        </div>
    </footer>
</div>
<dialog id="cart-drawer" aria-labelledby="cart-heading"><button type="button" data-close-cart aria-label="Close cart">×</button><h2 id="cart-heading">Your cart</h2><div data-cart-content aria-live="polite"></div><a class="button" href="/cart">Edit cart</a><a class="button button-primary" href="/checkout">Checkout</a></dialog>
<script src="/assets/js/app.js"></script>
</body>
</html>
<?php
}

function render_section_heading(string $eyebrow, string $title, string $copy = '', ?string $link = null, ?string $linkText = null): void
{
    ?>
    <div class="section-heading">
        <div>
            <p class="eyebrow"><?= h($eyebrow) ?></p>
            <h2><?= h($title) ?></h2>
            <?php if ($copy !== ''): ?><p class="section-copy"><?= h($copy) ?></p><?php endif; ?>
        </div>
        <?php if ($link): ?><a class="text-link" href="<?= h($link) ?>"><?= h($linkText ?: 'View all') ?> <span>→</span></a><?php endif; ?>
    </div>
    <?php
}

function render_availability(array $product): void
{
    if ($product['stock'] <= 0): ?>
        <span class="availability unavailable">Restocking <?= h($product['restock_date'] ? format_date($product['restock_date'], 'd M') : 'soon') ?></span>
    <?php elseif ($product['stock'] < $product['min_stock']): ?>
        <span class="availability low">Only <?= (int) $product['stock'] ?> left</span>
    <?php else: ?>
        <span class="availability">Ready to ship</span>
    <?php endif;
}

function render_product_card(array $product, bool $compact = false): void
{
    $category = category_name((int) $product['category_id']);
    $stockClass = $product['stock'] <= 0 ? 'is-sold-out' : ($product['stock'] < $product['min_stock'] ? 'is-low-stock' : '');
    ?>
    <article class="product-card <?= $compact ? 'product-card-compact' : '' ?> <?= $stockClass ?>" data-product-card data-name="<?= h(mb_strtolower($product['name'] . ' ' . $product['brand'] . ' ' . $category . ' ' . $product['description'] . ' ' . $product['sku'])) ?>" data-category="<?= (int) $product['category_id'] ?>">
        <div class="product-art art-<?= (int) $product['category_id'] ?>">
            <div class="art-orbit orbit-one"></div><div class="art-orbit orbit-two"></div>
            <span class="product-emoji <?= !empty($product['image_path']) ? 'has-photo' : '' ?>"><?= product_visual($product) ?></span>
            <?php if ($product['featured']): ?><span class="featured-tag">GALAXY PICK</span><?php endif; ?>
            <?php if ($product['stock'] <= 0): ?><span class="soldout-tag">RESTOCKING</span><?php endif; ?>
            <span class="art-sku"><?= h($product['sku']) ?></span>
        </div>
        <div class="product-details">
            <div class="product-meta"><span><?= h($category) ?></span><span class="meta-dot">·</span><span><?= h($product['brand']) ?></span></div>
            <h3><a href="<?= h(url('product', ['id' => $product['id']])) ?>"><?= h($product['name']) ?></a></h3>
            <?php if (!$compact): ?><p><?= h($product['description']) ?></p><?php endif; ?>
            <?php if ($viewer = current_user()): ?><form method="post" data-wishlist-form><?= csrf_field() ?><input type="hidden" name="action" value="wishlist"><input type="hidden" name="product_id" value="<?= (int) $product['id'] ?>"><button class="button button-small" aria-pressed="<?= in_array((int) $product['id'], store()->wishlist($viewer['id']), true) ? 'true' : 'false' ?>" type="submit"><?= in_array((int) $product['id'], store()->wishlist($viewer['id']), true) ? 'Saved to wishlist' : 'Add to wishlist' ?></button></form><?php else: ?><a href="<?= h(url('login')) ?>">Sign in to save</a><?php endif; ?>
            <div class="product-bottom">
                <div><strong><?= money((float) $product['price']) ?></strong><?php render_availability($product); ?></div>
                <?php if ($product['stock'] > 0): ?>
                    <form method="post">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="add_to_cart">
                        <input type="hidden" name="product_id" value="<?= (int) $product['id'] ?>">
                        <input type="hidden" name="quantity" value="1">
                        <input type="hidden" name="return_page" value="<?= h($_GET['page'] ?? 'home') ?>">
                        <button class="add-button" type="submit" aria-label="Add <?= h($product['name']) ?> to cart">+</button>
                    </form>
                <?php else: ?>
                    <button class="add-button disabled" type="button" disabled aria-label="Unavailable">—</button>
                <?php endif; ?>
            </div>
        </div>
    </article>
    <?php
}

function render_home(): void
{
    $categories = store()->categories();
    $featured = array_slice(array_filter(store()->products(), static fn(array $product): bool => $product['featured']), 0, 4);
    ?>
    <section class="hero">
        <div class="hero-content">
            <div class="hero-kicker"><span class="pulse-ring"></span> THE SEASON IS YOURS</div>
            <?php if (shop_settings()['hero_title'] === Branding::DEFAULTS['hero_title']): ?><h1>Find your <em>game.</em><br>Own your <span>orbit.</span></h1><?php else: ?><h1 class="custom-hero-title"><?= h(shop_settings()['hero_title']) ?></h1><?php endif; ?>
            <p><?= h(shop_settings()['hero_description']) ?></p>
            <div class="hero-actions">
                <a class="button button-primary button-large" href="<?= h(url('shop')) ?>"><?= h(shop_settings()['hero_button_text']) ?> <span>→</span></a>
                <?php if (shop_settings()['show_featured'] === '1'): ?><a class="button button-ghost button-large" href="#featured-products"><span class="play-mark">▶</span> See what’s new</a><?php endif; ?>
            </div>
            <div class="hero-proof">
                <div class="avatar-group"><span><img src="/assets/images/football.svg" alt=""></span><span><img src="/assets/images/bat.svg" alt=""></span><span><img src="/assets/images/racket.svg" alt=""></span></div>
                <p><strong>4.9 / 5 from players</strong><br>Gear that earns a place in your kit.</p>
            </div>
        </div>
        <div class="hero-visual" aria-label="Sports gear orbit illustration">
            <div class="hero-grid"></div>
            <div class="planet planet-main"><img src="/assets/images/football.svg" alt="Football"></div>
            <div class="planet planet-small planet-one"><img src="/assets/images/bat.svg" alt=""></div>
            <div class="planet planet-small planet-two"><img src="/assets/images/racket.svg" alt=""></div>
            <div class="planet planet-small planet-three"><img src="/assets/images/basketball.svg" alt=""></div>
            <div class="orbit orbit-large"></div><div class="orbit orbit-mid"></div><div class="orbit orbit-small"></div>
            <div class="hero-stat stat-one"><strong>48h</strong><span>dispatch</span></div>
            <div class="hero-stat stat-two"><strong>12k+</strong><span>players equipped</span></div>
            <div class="constellation c-one">✦</div><div class="constellation c-two">✦</div><div class="constellation c-three">·</div>
        </div>
        <div class="hero-scroll">SCROLL TO EXPLORE <span>↓</span></div>
    </section>

    <?php if (shop_settings()['show_benefits'] === '1'): ?><section class="benefit-strip">
        <div><span class="benefit-icon">✦</span><p><strong>Authentic equipment</strong><small>From trusted sport brands</small></p></div>
        <div><span class="benefit-icon">↗</span><p><strong>Quick dispatch</strong><small>Orders packed in 48 hours</small></p></div>
        <div><span class="benefit-icon">◈</span><p><strong>Secure payments</strong><small>COD, card and UPI options</small></p></div>
        <div><span class="benefit-icon">⌁</span><p><strong>Always in the loop</strong><small>Track every delivery step</small></p></div>
    </section>

    <?php endif; ?><?php if (shop_settings()['show_categories'] === '1'): ?><section class="content-section categories-section">
        <?php render_section_heading('PICK YOUR PLAYGROUND', 'Whatever moves you.', 'A compact collection of serious equipment for everyday players.', url('shop'), 'Explore all gear'); ?>
        <div class="category-grid">
            <?php foreach ($categories as $category): ?>
                <a class="category-card category-<?= h($category['color']) ?>" href="<?= h(url('shop', ['category' => $category['id']])) ?>">
                    <span class="category-icon"><?= category_visual($category) ?></span>
                    <span><strong><?= h($category['name']) ?></strong><small>Shop now <i>→</i></small></span>
                    <span class="category-spark">✦</span>
                </a>
            <?php endforeach; ?>
        </div>
    </section>

    <?php endif; ?><?php if (shop_settings()['show_featured'] === '1'): ?><section class="content-section featured-section" id="featured-products">
        <?php render_section_heading('CURATED FOR THE SEASON', 'The current line-up.', 'Reliable favourites, fresh kit, and no filler.', url('shop'), 'View the full store'); ?>
        <div class="product-grid">
            <?php foreach ($featured as $product): render_product_card($product); endforeach; ?>
        </div>
    </section>

    <?php endif; ?><?php if (shop_settings()['show_story'] === '1'): ?><section class="story-panel" id="why-galaxy">
        <div class="story-content">
            <p class="eyebrow">THE GALAXY STANDARD</p>
            <h2>Less time hunting for kit.<br><em>More time playing.</em></h2>
            <p>We made <?= h(shop_name()) ?> feel like your trusted local sports shop—just faster, clearer, and open whenever your next match calls.</p>
            <a class="text-link text-link-light" href="<?= h(url('shop')) ?>">Meet your next essential <span>→</span></a>
        </div>
        <div class="story-stats">
            <div><strong><?= count($categories) ?></strong><span>sports<br>categories</span></div>
            <div><strong>48h</strong><span>dispatch<br>promise</span></div>
            <div><strong>100%</strong><span>stock<br>visibility</span></div>
        </div>
        <div class="story-orb"><span>✦</span></div>
    </section>

    <?php endif; ?><?php if (shop_settings()['show_quote'] === '1'): ?><section class="quote-strip">
        <span class="quote-mark">“</span>
        <p>Sport isn’t just what you play. It’s how you show up.</p>
        <span class="quote-star">✦</span>
    </section>
    <?php endif; ?>
    <?php
}

function render_shop(): void
{
    $search = trim((string) ($_GET['q'] ?? ''));
    $categoryId = (int) ($_GET['category'] ?? 0);
    $products = store()->products(null, $categoryId ?: null);
    $categories = store()->categories();
    ?>
    <section class="shop-hero">
        <div>
            <p class="eyebrow">THE EQUIPMENT ROOM</p>
            <h1>Built for your next <em>move.</em></h1>
            <p>Search by sport, brand, or product. Every availability label reflects the live store inventory.</p>
        </div>
        <div class="shop-hero-mark"><span>✦</span><small>PLAY<br>ON</small></div>
    </section>
    <section class="shop-toolbar content-section">
        <form class="search-field" method="get" data-search-form>
            <input type="hidden" name="page" value="shop">
            <?php if ($categoryId): ?><input type="hidden" name="category" value="<?= $categoryId ?>"><?php endif; ?>
            <span>⌕</span><input type="search" name="q" value="<?= h($search) ?>" placeholder="Search footballs, bats, fitness..." aria-label="Search equipment" autocomplete="off" data-product-search>
            <button type="submit">Search</button>
        </form>
        <div class="filter-row" data-category-filters>
            <a class="filter-pill <?= !$categoryId ? 'active' : '' ?>" href="<?= h(url('shop', $search ? ['q' => $search] : [])) ?>" data-filter-category="all">All gear <span><?= count(store()->products()) ?></span></a>
            <?php foreach ($categories as $category): ?>
                <a class="filter-pill <?= $categoryId === (int) $category['id'] ? 'active' : '' ?>" href="<?= h(url('shop', ['category' => $category['id'], 'q' => $search ?: null])) ?>" data-filter-category="<?= (int) $category['id'] ?>"><?= h($category['name']) ?></a>
            <?php endforeach; ?>
        </div>
    </section>
    <section class="content-section shop-results">
        <div class="results-meta" aria-live="polite"><span><strong data-result-count><?= count($products) ?></strong> pieces of equipment</span><span>Updated stock <i class="live-dot"></i></span></div>
        <div class="product-grid" data-product-grid>
            <?php foreach ($products as $product): render_product_card($product); endforeach; ?>
        </div>
        <div class="empty-catalog" data-empty-catalog <?= $products ? 'hidden' : '' ?>>
            <span>⌕</span><h2>No gear in this orbit yet.</h2><p>Try a wider search or browse every category.</p><a class="button button-primary" href="<?= h(url('shop')) ?>">See all equipment</a>
        </div>
    </section>
    <?php
}

function render_cart_page(): void
{
    $cart = store()->cartDetails(cart());
    $user = current_user();
    $quote = store()->quote(cart());
    $estimatedTax = $quote['tax'];
    $shipping = $quote['shipping'];
    ?>
    <section class="page-intro compact-intro">
        <p class="eyebrow">YOUR EQUIPMENT LOCKER</p>
        <h1>Ready when you are.</h1>
        <p>Your cart is saved for this browser session. We validate live stock one more time at checkout.</p>
    </section>
    <section class="cart-layout content-section">
        <?php if ($cart['items']): ?>
            <div class="cart-items-panel">
                <div class="cart-panel-head"><span><?= count($cart['items']) ?> item<?= count($cart['items']) === 1 ? '' : 's' ?></span><a href="<?= h(url('shop')) ?>">Continue shopping <span>→</span></a></div>
                <form method="post">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="update_cart">
                    <input type="hidden" name="return_page" value="cart">
                    <div class="cart-list">
                        <?php foreach ($cart['items'] as $item): $product = $item['product']; ?>
                            <article class="cart-item">
                                <div class="cart-product-art art-<?= (int) $product['category_id'] ?>"><?= product_visual($product) ?></div>
                                <div class="cart-product-copy">
                                    <span><?= h(category_name((int) $product['category_id'])) ?> · <?= h($product['brand']) ?></span>
                                    <h3><a href="<?= h(url('product', ['id' => $product['id']])) ?>"><?= h($product['name']) ?></a></h3>
                                    <?php render_availability($product); ?>
                                </div>
                                <div class="quantity-control">
                                    <button type="button" data-quantity-down aria-label="Decrease quantity">−</button>
                                    <input type="number" aria-label="Quantity for <?= h($product['name']) ?>" min="1" max="<?= max(1, (int) $product['stock']) ?>" name="quantity[<?= (int) $product['id'] ?>]" value="<?= (int) $item['quantity'] ?>" data-quantity-input>
                                    <button type="button" data-quantity-up aria-label="Increase quantity">+</button>
                                </div>
                                <strong class="cart-line-price"><?= money((float) $item['line_total']) ?></strong>
                                <button class="remove-button" type="submit" name="remove_id" value="<?= (int) $product['id'] ?>" title="Remove <?= h($product['name']) ?>">×</button>
                            </article>
                        <?php endforeach; ?>
                    </div>
                    <div class="cart-update-row"><button class="text-button" type="submit">Update quantities</button><span>Stock is reserved only after purchase.</span></div>
                </form>
            </div>
            <aside class="order-summary">
                <div class="summary-label">ORDER SUMMARY</div>
                <h2>Almost game time.</h2>
                <div class="summary-row"><span>Subtotal</span><strong><?= money((float) $cart['subtotal']) ?></strong></div>
                <div class="summary-row"><span>Delivery</span><strong><?= $shipping === 0 ? 'Free' : money($shipping) ?></strong></div>
                <div class="summary-row muted"><span>Estimated GST</span><strong><?= money($estimatedTax) ?></strong></div>
                <div class="summary-total"><span>Estimated total</span><strong><?= money($cart['subtotal'] + $shipping + $estimatedTax) ?></strong></div>
                <?php if ($user && can_shop($user)): ?>
                    <a class="button button-primary button-full" href="<?= h(url('checkout')) ?>">Secure checkout <span>→</span></a>
                <?php elseif (!$user): ?>
                    <a class="button button-primary button-full" href="<?= h(url('login', ['next' => 'checkout'])) ?>">Sign in to check out <span>→</span></a>
                    <p class="summary-note">Browsing is open to everyone. A customer account keeps orders and invoices secure.</p>
                <?php else: ?>
                    <p class="summary-note">Please use a customer or delivery partner account to place an order.</p>
                <?php endif; ?>
                <div class="summary-assurance"><span>◈</span><p><strong>Safe and simple</strong><br>Choose COD, UPI, or card at checkout.</p></div>
            </aside>
        <?php else: ?>
            <div class="empty-state cart-empty">
                <div class="empty-orb cart-empty-icon"><?= cart_icon() ?></div><p class="eyebrow">YOUR LOCKER IS OPEN</p><h2>Nothing in your cart yet.</h2><p>Find the gear that makes you want to get back out there.</p><a class="button button-primary" href="<?= h(url('shop')) ?>">Explore equipment <span>→</span></a>
            </div>
        <?php endif; ?>
    </section>
    <?php
}

function render_checkout_page(array $user): void
{
    $cart = store()->cartDetails(cart());
    $quote = store()->quote(cart());
    if ($cart['items'] === []) {
        flash('warning', 'Add something to your cart before checking out.');
        redirect('cart');
    }
    ?>
    <section class="page-intro compact-intro checkout-intro">
        <div><p class="eyebrow">SECURE CHECKOUT</p><h1>One last lap.</h1><p>Choose cash on delivery or an available online payment option.</p></div>
        <div class="checkout-steps"><span class="active">1<br><small>Details</small></span><i></i><span>2<br><small>Confirm</small></span><i></i><span>3<br><small>Invoice</small></span></div>
    </section>
    <section class="checkout-layout content-section">
        <form class="checkout-form" method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="checkout">
            <input type="hidden" name="return_page" value="checkout">
            <section class="checkout-block">
                <div class="form-block-heading"><span class="form-step">01</span><div><h2>Where should we deliver?</h2><p>We only share the delivery details with your assigned partner.</p></div></div>
                <div class="form-grid two">
                    <label>Recipient name<input name="recipient" required value="<?= h($user['name']) ?>" autocomplete="name"></label>
                    <label>Phone number<input name="phone" required value="<?= h($user['phone']) ?>" inputmode="tel" autocomplete="tel"></label>
                    <label class="wide">Street address<input name="address" required placeholder="House number, street, area" autocomplete="street-address"></label>
                    <label>City<input name="city" required value="Kolkata" autocomplete="address-level2"></label>
                    <label>PIN code<input name="postal_code" required pattern="[0-9]{6}" maxlength="6" placeholder="700000" inputmode="numeric" autocomplete="postal-code"></label>
                </div>
            </section>
            <section class="checkout-block">
                <div class="form-block-heading"><span class="form-step">02</span><div><h2>How would you like to pay?</h2><p>Online payments are confirmed by the payment provider.</p></div></div>
                <div class="payment-options">
                    <label class="payment-choice"><input type="radio" name="payment_method" value="cod" checked><span class="payment-icon">₹</span><span><strong>Cash on delivery</strong><small>Pay when the order arrives</small></span><i></i></label>
                    <?php if (Payments::configured()): ?><label class="payment-choice"><input type="radio" name="payment_method" value="razorpay"><span><strong>Pay online with Razorpay</strong><small>UPI, cards and more · pay from your receipt</small></span></label><?php endif; ?>
                    <?php if (shop_settings()['demo_access'] === '1'): ?><label class="payment-choice"><input type="radio" name="payment_method" value="upi"><span class="payment-icon">◌</span><span><strong>UPI</strong><small>Instant demo confirmation</small></span><i></i></label>
                    <label class="payment-choice"><input type="radio" name="payment_method" value="card"><span class="payment-icon">▣</span><span><strong>Credit / debit card</strong><small>Secure simulated payment</small></span><i></i></label><?php endif; ?>
                </div>
            </section>
            <section class="checkout-block coupon-block">
                <div class="form-block-heading"><span class="form-step">03</span><div><h2>Have a playmaker code?</h2><p>Try <strong>GALAXY10</strong> for 10% off this order.</p></div></div>
                <label class="coupon-input"><input name="coupon" placeholder="Offer code" data-coupon><span class="sr-only" data-coupon-message aria-live="polite"></span><span>10% OFF</span></label>
            </section>
            <button class="button button-primary button-large checkout-submit" type="submit">Place secure order <span>→</span></button>
        </form>
        <aside class="checkout-summary" data-checkout-summary data-subtotal="<?= h($quote['subtotal']) ?>" data-tax-rate="<?= h($quote['tax_rate']) ?>" data-shipping-fee="<?= h($quote['shipping_fee']) ?>" data-free-shipping="<?= h($quote['free_shipping_at']) ?>">
            <p class="summary-label">YOUR ORDER</p>
            <?php foreach ($cart['items'] as $item): ?>
                <div class="checkout-line"><span class="mini-product-art art-<?= (int) $item['product']['category_id'] ?>"><?= product_visual($item['product']) ?><b><?= (int) $item['quantity'] ?></b></span><span><strong><?= h($item['product']['name']) ?></strong><small><?= h($item['product']['brand']) ?></small></span><em><?= money((float) $item['line_total']) ?></em></div>
            <?php endforeach; ?>
            <div class="summary-divider"></div>
            <div class="summary-row"><span>Subtotal</span><strong><?= money((float) $cart['subtotal']) ?></strong></div>
            <div class="summary-row"><span>Delivery</span><strong><span data-summary-shipping><?= $quote['shipping'] === 0.0 ? 'Free' : money($quote['shipping']) ?></span></strong></div>
            <div class="summary-row"><span>GST (estimated)</span><strong><span data-summary-tax><?= money($quote['tax']) ?></span></strong></div>
            <div class="summary-row" data-discount-row hidden><span>GALAXY10 discount</span><strong data-summary-discount></strong></div>
            <div class="summary-total"><span>Total</span><strong data-summary-total><?= money($quote['grand_total']) ?></strong></div>
            <div class="order-trust"><span>◈</span><p><strong>Inventory protected</strong><br>We verify every item before confirmation.</p></div>
        </aside>
    </section>
    <?php
}

function render_login_page(): void
{
    $next = preg_replace('/[^a-z_]/', '', (string) ($_GET['next'] ?? ''));
    ?>
    <section class="auth-shell">
        <div class="auth-visual">
            <div class="auth-visual-inner">
                <p class="eyebrow">WELCOME BACK, PLAYER</p>
                <h1>Your next move<br>starts <em>here.</em></h1>
                <p>Sign in to see your orders, invoices, delivery updates, and the gear waiting in your locker.</p>
                <div class="auth-orbit"><span class="auth-ball">⚽</span><i></i><b>✦</b><em>🏸</em></div>
                <div class="auth-quote">“The kit is just the start.”<span>— TEAM GALAXY</span></div>
            </div>
        </div>
        <div class="auth-panel">
            <div class="auth-form-wrap">
                <a class="auth-back" href="<?= h(url('home')) ?>">← Back to store</a>
                <p class="eyebrow">ACCOUNT ACCESS</p>
                <h1>Good to see you.</h1>
                <p class="auth-subcopy">Use your account to keep every delivery and receipt in one place.</p>
                <form class="auth-form" method="post">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="login"><input type="hidden" name="return_page" value="login">
                    <input type="hidden" name="next" value="<?= h($next) ?>">
                    <label>Email address<input type="email" name="email" required autocomplete="email" placeholder="you@example.com"></label>
                    <label>Password<input type="password" name="password" required autocomplete="current-password" placeholder="••••••••"></label>
                    <button class="button button-primary button-full" type="submit">Sign in <span>→</span></button>
                </form>
                <p class="auth-switch">New to <?= h(shop_name()) ?>? <a href="<?= h(url('register')) ?>">Create an account</a></p>
                <?php if (shop_settings()['demo_access'] === '1'): ?><div class="demo-accounts">
                    <p><span>◌</span> Demo access</p>
                    <div><button type="button" data-demo-login data-email="customer@sportsgalaxy.local" data-password="Customer@123">Customer</button><button type="button" data-demo-login data-email="admin@sportsgalaxy.local" data-password="Admin@123">Admin</button><button type="button" data-demo-login data-email="delivery@sportsgalaxy.local" data-password="Delivery@123">Delivery</button></div>
                </div><?php endif; ?>
            </div>
        </div>
    </section>
    <?php
}

function render_register_page(): void
{
    ?>
    <section class="auth-shell register-shell">
        <div class="auth-visual">
            <div class="auth-visual-inner">
                <p class="eyebrow">JOIN THE GALAXY</p>
                <h1>Make room for<br><em>more play.</em></h1>
                <p>A customer account gives you fast checkout, clean invoices, and a front-row seat to every delivery update.</p>
                <div class="join-benefits"><span>✓ Order history &amp; printable invoices</span><span>✓ Delivery tracking from dispatch to doorstep</span><span>✓ Restock and offer notifications</span></div>
                <div class="auth-orbit join-orbit"><span class="auth-ball">🏀</span><i></i><b>✦</b><em>🏋️</em></div>
            </div>
        </div>
        <div class="auth-panel">
            <div class="auth-form-wrap">
                <a class="auth-back" href="<?= h(url('home')) ?>">← Back to store</a>
                <p class="eyebrow">CREATE ACCOUNT</p>
                <h1>Enter the field.</h1>
                <p class="auth-subcopy">A few details, then you’re ready to build your equipment locker.</p>
                <form class="auth-form" method="post">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="register"><input type="hidden" name="return_page" value="register">
                    <div class="form-grid two">
                        <label>Full name<input type="text" name="name" required autocomplete="name" placeholder="Your name"></label>
                        <label>Phone number<input type="tel" name="phone" required autocomplete="tel" placeholder="+91 98765 43210"></label>
                    </div>
                    <label>Email address<input type="email" name="email" required autocomplete="email" placeholder="you@example.com"></label>
                    <label>Create a password<input type="password" name="password" minlength="8" required autocomplete="new-password" placeholder="At least 8 characters"></label>
                    <button class="button button-primary button-full" type="submit">Create my account <span>→</span></button>
                </form>
                <p class="auth-switch">Already have an account? <a href="<?= h(url('login')) ?>">Sign in</a></p>
            </div>
        </div>
    </section>
    <?php
}

function render_account_page(array $user): void
{
    $orders = store()->ordersForUser((int) $user['id']);
    $notifications = store()->notifications((int) $user['id']);
    $activeOrders = array_filter($orders, static fn(array $order): bool => !in_array($order['status'], ['delivered', 'cancelled'], true));
    ?>
    <section class="account-hero">
        <div class="account-welcome"><span class="avatar avatar-large"><?= avatar_content($user) ?></span><div><p class="eyebrow">YOUR PLAYER PROFILE</p><h1>Hey, <?= h(explode(' ', $user['name'])[0]) ?>.</h1><p>Everything you need for your next game, in one clear view.</p></div></div>
        <div class="account-hero-stats"><div><strong><?= count($orders) ?></strong><span>total orders</span></div><div><strong><?= count($activeOrders) ?></strong><span>in motion</span></div><div><strong><?= count($notifications) ?></strong><span>updates</span></div></div>
    </section>
    <section class="account-layout content-section">
        <aside class="account-sidebar">
            <a class="account-side-link active" href="#my-orders">⌑ <span>My orders</span><b><?= count($orders) ?></b></a>
            <a class="account-side-link" href="#order-updates">◌ <span>Order updates</span><b><?= count($notifications) ?></b></a>
            <a class="account-side-link" href="#profile">◒ <span>Profile details</span></a>
            <div class="account-help"><span>✦</span><p><strong>Need a hand?</strong><br>Our delivery timeline is always attached to your order.</p></div>
        </aside>
        <div class="account-content">
            <section id="my-orders">
                <?php render_section_heading('YOUR ORDER HISTORY', 'Your equipment, in motion.', 'Open an order for its invoice and the full delivery trail.'); ?>
                <?php if ($orders): ?>
                    <div class="order-card-list">
                        <?php foreach ($orders as $order): ?>
                            <article class="customer-order-card">
                                <div class="order-card-top"><div><span class="order-number"><?= h($order['number']) ?></span><small>Placed <?= h(format_date($order['created_at'], 'd M Y')) ?></small></div><span class="status-pill <?= h(status_tone($order['status'])) ?>"><?= h(status_label($order['status'])) ?></span></div>
                                <div class="order-product-previews"><?php foreach (array_slice($order['lines'], 0, 4) as $line): ?><span><?= product_visual($line) ?></span><?php endforeach; ?><p><?= count($order['lines']) ?> item<?= count($order['lines']) === 1 ? '' : 's' ?> · <?= money((float) $order['grand_total']) ?></p></div>
                                <div class="order-card-footer"><span class="tracking-copy">Tracking <b><?= h($order['tracking_code']) ?></b></span><div><a class="text-button" href="<?= h(url('invoice', ['id' => $order['id']])) ?>">View invoice</a><a class="button button-small button-dark" href="<?= h(url('invoice', ['id' => $order['id'], 'track' => 1])) ?>">Track order <span>→</span></a></div></div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state small-empty"><div class="empty-orb">⌑</div><h2>Your first great order is waiting.</h2><p>Explore the store and everything else will show up here.</p><a class="button button-primary" href="<?= h(url('shop')) ?>">Shop equipment</a></div>
                <?php endif; ?>
            </section>
            <section id="order-updates" class="account-section-break">
                <?php render_section_heading('YOUR FEED', 'Fresh from the Galaxy.', 'Product, order, and delivery messages meant for you.'); ?>
                <div class="notification-list">
                    <?php foreach ($notifications as $notification): ?>
                        <article class="notification-item"><span class="notification-icon notification-<?= h($notification['type']) ?>"><?= $notification['type'] === 'order' ? '⌑' : ($notification['type'] === 'delivery' ? '↗' : '✦') ?></span><div><strong><?= h($notification['title']) ?></strong><p><?= h($notification['body']) ?></p></div><time><?= h(format_date($notification['created_at'], 'd M')) ?></time></article>
                    <?php endforeach; ?>
                </div>
            </section>
            <section id="profile" class="account-section-break">
                <?php render_section_heading('PLAYER DETAILS', 'The practical bits.', 'These details pre-fill your next checkout.'); ?>
                <a class="text-link" href="<?= h(url('profile')) ?>">Change profile photo →</a><div class="profile-card"><span class="avatar avatar-large"><?= avatar_content($user) ?></span><div><strong><?= h($user['name']) ?></strong><p><?= h($user['email']) ?><br><?= h($user['phone']) ?></p></div><span class="profile-safe">◈ Account secured</span></div>
            </section>
        </div>
    </section>
    <?php
}

function render_invoice_page(array $order, bool $track = false): void
{
    if ($order['payment_method'] === 'razorpay' && $order['payment_status'] === 'pending') {
        echo '<section class="content-section"><h2>Online payment pending</h2><p>Complete payment, then check its status here. Your order ships after verification.</p>';
        foreach (['pay_order' => 'Pay securely with Razorpay', 'verify_payment' => 'Check payment status'] as $action => $label) echo '<form method="post">' . csrf_field() . '<input type="hidden" name="action" value="' . $action . '"><input type="hidden" name="order_id" value="' . (int) $order['id'] . '"><button class="button button-primary" type="submit">' . $label . '</button></form>';
        echo '</section>';
    }
    ?>
    <section class="invoice-page <?= $track ? 'tracking-mode' : '' ?>">
        <div class="invoice-actions content-section">
            <a class="back-link" href="<?= h((int) $order['user_id'] === (int) current_user()['id'] ? url('account') : url(current_user()['role'] === 'admin' ? 'admin' : 'delivery')) ?>">← Back to workspace</a>
            <div><button class="button button-ghost print-button" type="button" onclick="window.print()">Print receipt <span>↗</span></button><?php if (!$track): ?><a class="button button-primary" href="<?= h(url('invoice', ['id' => $order['id'], 'track' => 1])) ?>">Track order <span>→</span></a><?php endif; ?></div>
        </div>
        <div class="invoice-layout content-section">
            <article class="invoice-card">
                <div class="invoice-brand"><a class="brand" href="<?= h(url('home')) ?>"><?= brand_markup() ?></a><div><span>ORDER RECEIPT</span><strong><?= h($order['number']) ?></strong></div></div>
                <div class="invoice-meta"><div><span>Issued to</span><strong><?= h($order['address']['recipient']) ?></strong><p><?= h($order['address']['line1']) ?><br><?= h($order['address']['city']) ?> · <?= h($order['address']['postal_code']) ?><br><?= h($order['address']['phone']) ?></p></div><div><span>Order details</span><strong><?= h(format_date($order['created_at'], 'd M Y')) ?></strong><p>Tracking: <?= h($order['tracking_code']) ?><br>Payment: <?= h(strtoupper($order['payment_method'])) ?><br><b class="invoice-payment <?= h(status_tone($order['payment_status'])) ?>"><?= h(status_label($order['payment_status'])) ?></b></p></div></div>
                <div class="invoice-lines"><div class="invoice-line-head"><span>ITEM</span><span>QTY</span><span>TOTAL</span></div><?php foreach ($order['lines'] as $line): ?><div class="invoice-line"><span><b class="invoice-item-visual"><?= product_visual($line) ?></b><span><strong><?= h($line['name']) ?></strong><small><?= h($line['sku']) ?> · <?= money((float) $line['unit_price']) ?></small></span></span><span>×<?= (int) $line['quantity'] ?></span><strong><?= money((float) $line['line_total']) ?></strong></div><?php endforeach; ?></div>
                <div class="invoice-totals"><div><span>Subtotal</span><strong><?= money((float) $order['subtotal']) ?></strong></div><?php if ($order['discount'] > 0): ?><div class="invoice-discount"><span><?= h($order['discount_name']) ?></span><strong>−<?= money((float) $order['discount']) ?></strong></div><?php endif; ?><div><span>GST (<?= h((string) $order['tax_rate']) ?>%)</span><strong><?= money((float) $order['tax']) ?></strong></div><div><span>Delivery</span><strong><?= (float) $order['shipping'] === 0.0 ? 'Free' : money((float) $order['shipping']) ?></strong></div><div class="invoice-grand"><span>Grand total</span><strong><?= money((float) $order['grand_total']) ?></strong></div></div>
                <div class="invoice-footer"><span>Thank you for playing with us. ✦</span><span>Tax invoice · Demo project record</span></div>
            </article>
            <aside class="tracking-panel">
                <p class="eyebrow"><?= $track ? 'LIVE DELIVERY VIEW' : 'ORDER STATUS' ?></p>
                <div class="tracking-head"><div><h2><?= h(status_label($order['status'])) ?></h2><p><?= h($order['status'] === 'delivered' ? 'This order is complete. Enjoy every minute of it.' : 'Tracking code: ' . $order['tracking_code']) ?></p></div><span class="tracking-status-icon"><?= $order['status'] === 'delivered' ? '✓' : '↗' ?></span></div>
                <?php if ((int) $order['user_id'] === (int) current_user()['id'] && !in_array($order['status'], ['delivered', 'cancelled'], true)): ?>
                    <div class="handover-code"><small>DELIVERY VERIFICATION CODE</small><strong><?= h($order['delivery_code']) ?></strong><p>Share this code with your partner when you receive your order.</p></div>
                <?php endif; ?>
                <ol class="timeline">
                    <?php foreach ($order['delivery_events'] as $index => $event): ?>
                        <li class="<?= $index === array_key_last($order['delivery_events']) ? 'current' : '' ?>"><i></i><div><strong><?= h(status_label($event['status'])) ?></strong><p><?= h($event['note']) ?></p><small><?= h(format_date($event['at'])) ?> · <?= h($event['actor']) ?></small></div></li>
                    <?php endforeach; ?>
                </ol>
                <?php if ($order['delivery_partner_id']): $partner = store()->user((int) $order['delivery_partner_id']); ?>
                    <div class="partner-card"><span class="partner-avatar"><?= avatar_content($partner) ?></span><div><small>YOUR DELIVERY PARTNER</small><strong><?= h($partner['name']) ?></strong><p>Order and address details are protected in this workspace.</p></div></div>
                <?php endif; ?>
            </aside>
        </div>
    </section>
    <?php
}

function render_admin_page(array $user): void
{
    $tab = $_GET['tab'] ?? 'dashboard';
    $allowedTabs = ['dashboard', 'products', 'categories', 'inventory', 'orders', 'finance', 'partners', 'admins', 'settings'];
    $tab = in_array($tab, $allowedTabs, true) ? $tab : 'dashboard';
    $stats = store()->dashboardStats();
    $orders = store()->allOrders();
    $products = store()->products();
    $partners = store()->deliveryPartners();
    $editingProduct = isset($_GET['edit']) ? store()->product((int) $_GET['edit']) : null;
    ?>
    <section class="workspace-header admin-workspace-header">
        <div><div class="workspace-kicker"><span>CONTROL ROOM</span><i></i><small>ADMIN ACCESS</small></div><h1>Good morning, <?= h(explode(' ', $user['name'])[0]) ?>.</h1><p>Your shop is moving. Here’s the signal from <?= h(shop_name()) ?> today.</p></div>
        <div class="workspace-header-actions"><span class="live-indicator"><i></i> Live store data</span><a class="button button-dark" href="<?= h(url('shop')) ?>">View storefront ↗</a></div>
    </section>
    <section class="workspace-shell">
        <aside class="workspace-nav">
            <div class="workspace-user"><span class="avatar avatar-large"><?= avatar_content($user) ?></span><div><strong><?= h($user['name']) ?></strong><small>Store administrator</small></div></div>
            <nav>
                <a class="<?= $tab === 'dashboard' ? 'active' : '' ?>" href="<?= h(url('admin', ['tab' => 'dashboard'])) ?>"><span>▦</span> Overview</a>
                <a class="<?= $tab === 'products' ? 'active' : '' ?>" href="<?= h(url('admin', ['tab' => 'products'])) ?>"><span>◈</span> Products</a>
                <a class="<?= $tab === 'categories' ? 'active' : '' ?>" href="<?= h(url('admin', ['tab' => 'categories'])) ?>"><span>▧</span> Categories &amp; gear</a>
                <a class="<?= $tab === 'inventory' ? 'active' : '' ?>" href="<?= h(url('admin', ['tab' => 'inventory'])) ?>"><span>⌑</span> Inventory <?php if ($stats['low_stock']): ?><b><?= count($stats['low_stock']) ?></b><?php endif; ?></a>
                <a class="<?= $tab === 'orders' ? 'active' : '' ?>" href="<?= h(url('admin', ['tab' => 'orders'])) ?>"><span>↗</span> Orders</a>
                <a class="<?= $tab === 'partners' ? 'active' : '' ?>" href="<?= h(url('admin', ['tab' => 'partners'])) ?>"><span>♙</span> Delivery team</a>
                <a class="<?= $tab === 'admins' ? 'active' : '' ?>" href="<?= h(url('admin', ['tab' => 'admins'])) ?>"><span>♧</span> Administrators</a>
                <a class="<?= $tab === 'finance' ? 'active' : '' ?>" href="<?= h(url('admin', ['tab' => 'finance'])) ?>"><span>◒</span> Finance</a>
                <a class="<?= $tab === 'settings' ? 'active' : '' ?>" href="<?= h(url('admin', ['tab' => 'settings'])) ?>"><span>⚙</span> Store settings</a>
            </nav>
            <div class="workspace-nav-help"><span>✦</span><p><strong>Daily summary ready</strong><br><?= count($stats['low_stock']) ?> item<?= count($stats['low_stock']) === 1 ? '' : 's' ?> need inventory attention.</p></div>
            <a class="workspace-store-link" href="<?= h(url('home')) ?>">← Back to storefront</a>
        </aside>
        <div class="workspace-content">
            <?php if ($tab === 'dashboard'): ?>
                <div class="workspace-content-head"><div><p class="eyebrow">TODAY'S PULSE</p><h2>The dashboard at a glance.</h2></div><span class="date-chip"><?= date('D, d M Y') ?></span></div>
                <div class="metric-grid">
                    <article class="metric-card accent-lime"><span class="metric-icon">₹</span><div><small>Today’s revenue</small><strong><?= money((float) $stats['daily_revenue']) ?></strong><em>Orders placed today, excluding cancellations</em></div></article>
                    <article class="metric-card accent-violet"><span class="metric-icon">⌑</span><div><small>Today’s transactions</small><strong><?= (int) $stats['daily_orders'] ?></strong><em><?= count($orders) ?> orders in demo history</em></div></article>
                    <article class="metric-card accent-cyan"><span class="metric-icon">◒</span><div><small>Monthly net profit</small><strong><?= money((float) $stats['month_profit']) ?></strong><em>After product cost &amp; expenses</em></div></article>
                    <article class="metric-card accent-orange"><span class="metric-icon">!</span><div><small>Low-stock alerts</small><strong><?= count($stats['low_stock']) ?></strong><em>Below individual threshold</em></div></article>
                </div>
                <div class="admin-main-grid">
                    <section class="admin-card revenue-card"><div class="admin-card-head"><div><p class="eyebrow">REVENUE SIGNAL</p><h3>Sales, seven day orbit.</h3></div><span class="chart-period">Last 7 days</span></div><div class="bar-chart" aria-label="Weekly sales bar chart"><?php $peak = max(1, max(array_column($stats['weekly_revenue'], 'total'))); foreach ($stats['weekly_revenue'] as $day): ?><div title="<?= h($day['date'] . ': ' . money($day['total'])) ?>"><i style="height: <?= round($day['total'] / $peak * 100, 2) ?>%"></i><span><?= h($day['label']) ?></span></div><?php endforeach; ?></div><div class="chart-legend"><span><i class="legend-lime"></i> Revenue</span><strong><?= money((float) $stats['month_revenue']) ?> this month</strong></div></section>
                    <section class="admin-card top-products-card"><div class="admin-card-head"><div><p class="eyebrow">POPULAR NOW</p><h3>Top-selling equipment.</h3></div><a href="<?= h(url('admin', ['tab' => 'products'])) ?>">Manage →</a></div><ol class="rank-list"><?php $rank = 1; foreach ($stats['top_products'] ?: ['No sales yet' => 0] as $name => $quantity): ?><li><b><?= $rank++ ?></b><span><?= h($name) ?></span><strong><?= (int) $quantity ?> sold</strong></li><?php endforeach; ?></ol></section>
                </div>
                <div class="admin-main-grid lower-grid">
                    <section class="admin-card alert-card"><div class="admin-card-head"><div><p class="eyebrow">INVENTORY WATCH</p><h3>Items asking for a refill.</h3></div><a href="<?= h(url('admin', ['tab' => 'inventory'])) ?>">Open inventory →</a></div><?php if ($stats['low_stock']): ?><div class="low-stock-list"><?php foreach (array_slice($stats['low_stock'], 0, 4) as $product): ?><div><span class="mini-product-art art-<?= (int) $product['category_id'] ?>"><?= product_visual($product) ?></span><span><strong><?= h($product['name']) ?></strong><small><?= (int) $product['stock'] ?> / <?= (int) $product['min_stock'] ?> units · <?= $product['stock'] === 0 ? 'restocking ' . ($product['restock_date'] ? format_date($product['restock_date'], 'd M') : 'soon') : 'threshold breached' ?></small></span><a href="<?= h(url('admin', ['tab' => 'inventory'])) ?>">Adjust</a></div><?php endforeach; ?></div><?php else: ?><p class="quiet-copy">All products are currently above their minimum stock level.</p><?php endif; ?></section>
                    <section class="admin-card recent-orders-card"><div class="admin-card-head"><div><p class="eyebrow">LATEST ACTIVITY</p><h3>Recent orders.</h3></div><a href="<?= h(url('admin', ['tab' => 'orders'])) ?>">View all →</a></div><div class="mini-order-list"><?php foreach (array_slice($orders, 0, 4) as $order): ?><div><span class="order-mini-avatar"><?= h(mb_strtoupper(mb_substr($order['customer_name'], 0, 1))) ?></span><span><strong><?= h($order['customer_name']) ?></strong><small><?= h($order['number']) ?></small></span><em><?= money((float) $order['grand_total']) ?></em><i class="status-dot <?= h(status_tone($order['status'])) ?>"></i></div><?php endforeach; ?></div></section>
                </div>
            <?php elseif ($tab === 'products'): ?>
                <div class="workspace-content-head"><div><p class="eyebrow">PRODUCT CATALOG</p><h2><?= $editingProduct ? 'Fine-tune an item.' : 'Add to the equipment room.' ?></h2></div><a class="button button-ghost" href="<?= h(url('admin', ['tab' => 'products'])) ?>">+ New product</a></div>
                <section class="admin-editor-card">
                    <form method="post" enctype="multipart/form-data" class="product-editor-form">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="save_product"><input type="hidden" name="return_page" value="admin"><input type="hidden" name="return_tab" value="products"><input type="hidden" name="id" value="<?= (int) ($editingProduct['id'] ?? 0) ?>">
                        <div class="form-grid product-form-grid">
                            <label>Product name<input required name="name" value="<?= h($editingProduct['name'] ?? '') ?>" placeholder="e.g. Pro training football"></label>
                            <label>SKU<input required name="sku" value="<?= h($editingProduct['sku'] ?? '') ?>" placeholder="SG-FB-001"></label>
                            <label>Brand<input required name="brand" value="<?= h($editingProduct['brand'] ?? '') ?>" placeholder="Brand name"></label>
                            <label>Category<select name="category_id"><?php foreach (store()->categories() as $category): ?><option value="<?= (int) $category['id'] ?>" <?= (int) ($editingProduct['category_id'] ?? ($_GET['category_id'] ?? 1)) === (int) $category['id'] ? 'selected' : '' ?>><?= h($category['name']) ?></option><?php endforeach; ?></select></label>
                            <label>Selling price<input required type="number" min="0" step="0.01" name="price" value="<?= h($editingProduct['price'] ?? '') ?>" placeholder="0.00"></label>
                            <label>Unit cost<input required type="number" min="0" step="0.01" name="cost" value="<?= h($editingProduct['cost'] ?? '') ?>" placeholder="0.00"></label>
                            <label>Current stock<input required type="number" min="0" name="stock" value="<?= h($editingProduct['stock'] ?? '0') ?>"></label>
                            <label>Low-stock threshold<input required type="number" min="1" name="min_stock" value="<?= h($editingProduct['min_stock'] ?? '5') ?>"></label>
                            <label>Expected restock<input type="date" name="restock_date" value="<?= h($editingProduct['restock_date'] ?? '') ?>"></label>
                            <label>Product icon or emoji<input name="visual" required maxlength="16" value="<?= h($editingProduct['visual'] ?? '⚽') ?>" placeholder="e.g. 🏊"></label>
                            <label class="wide">Description<textarea required name="description" rows="2" placeholder="A short helpful product description"><?= h($editingProduct['description'] ?? '') ?></textarea></label>
                        </div>
                        <div class="product-photo-editor">
                            <div class="photo-current mini-product-art"><?= $editingProduct ? product_visual($editingProduct) : '⚽' ?></div>
                            <?php render_photo_input('product_photo', 'Product photo', !empty($editingProduct['image_path'])); ?>
                        </div>
                        <label class="checkbox-label"><input type="checkbox" name="featured" <?= !empty($editingProduct['featured']) ? 'checked' : '' ?>> <span>Feature this product on the storefront</span></label>
                        <button class="button button-primary" type="submit"><?= $editingProduct ? 'Save changes' : 'Add product' ?> <span>→</span></button>
                    </form>
                </section>
                <section class="admin-card catalog-table-card"><div class="admin-card-head"><div><p class="eyebrow">ALL EQUIPMENT</p><h3><?= count($products) ?> active products.</h3></div><span class="table-hint">Click edit to modify</span></div><div class="table-scroll"><table class="admin-table"><thead><tr><th>Product</th><th>SKU</th><th>Price</th><th>Stock</th><th>Status</th><th></th></tr></thead><tbody><?php foreach ($products as $product): ?><tr><td><span class="table-product"><i class="mini-product-art art-<?= (int) $product['category_id'] ?>"><?= product_visual($product) ?></i><span><strong><?= h($product['name']) ?></strong><small><?= h($product['brand']) ?> · <?= h(category_name((int) $product['category_id'])) ?></small></span></span></td><td><?= h($product['sku']) ?></td><td><?= money((float) $product['price']) ?></td><td><?= (int) $product['stock'] ?> <small>/ min <?= (int) $product['min_stock'] ?></small></td><td><?php render_availability($product); ?></td><td><a class="table-action" href="<?= h(url('admin', ['tab' => 'products', 'edit' => $product['id']])) ?>">Edit</a></td></tr><?php endforeach; ?></tbody></table></div></section>
            <?php elseif ($tab === 'inventory'): ?>
                <div class="workspace-content-head"><div><p class="eyebrow">STOCK CONTROL</p><h2>Keep every shelf in orbit.</h2></div><span class="inventory-key"><i></i> Below reorder threshold = attention</span></div>
                <section class="admin-card inventory-table-card"><div class="admin-card-head"><div><p class="eyebrow">LIVE INVENTORY</p><h3>Receive stock or record an adjustment.</h3></div><span class="table-hint">Each change is logged</span></div><div class="inventory-list"><?php foreach ($products as $product): ?><article class="inventory-row"><div class="inventory-product"><span class="mini-product-art art-<?= (int) $product['category_id'] ?>"><?= product_visual($product) ?></span><div><strong><?= h($product['name']) ?></strong><a class="table-action" href="<?= h(url('admin', ['tab' => 'products', 'edit' => $product['id']])) ?>">Edit item &amp; photo</a><small><?= h($product['sku']) ?> · Reorder at <?= (int) $product['min_stock'] ?></small></div></div><div class="stock-meter-wrap"><div class="stock-number <?= $product['stock'] < $product['min_stock'] ? 'low' : '' ?>"><?= (int) $product['stock'] ?><small>units</small></div><div class="stock-meter"><i style="width: <?= min(100, max(6, ($product['stock'] / max(1, $product['min_stock'] * 3)) * 100)) ?>%"></i></div></div><form method="post" class="inventory-adjust-form"><?= csrf_field() ?><input type="hidden" name="action" value="adjust_stock"><input type="hidden" name="return_page" value="admin"><input type="hidden" name="return_tab" value="inventory"><input type="hidden" name="product_id" value="<?= (int) $product['id'] ?>"><input type="number" name="delta" value="1" min="-<?= (int) $product['stock'] ?>" max="999" aria-label="Quantity change for <?= h($product['name']) ?>"><input name="note" placeholder="Reason (optional)" aria-label="Adjustment note"><button class="button button-small button-dark" type="submit">Apply</button></form></article><?php endforeach; ?></div></section>
            <?php elseif ($tab === 'orders'): ?>
                <?php render_order_command($orders, $partners); ?>
            <?php elseif ($tab === 'categories'): ?>
                <?php render_categories_admin(); ?>
            <?php elseif ($tab === 'admins'): ?>
                <?php render_administrators_admin(); ?>
            <?php elseif ($tab === 'settings'): ?>
                <?php render_branding_settings(); ?>
            <?php elseif ($tab === 'partners'): ?>
                <?php render_delivery_partners_admin($partners); ?>
            <?php elseif ($tab === 'finance'): ?>
                <?php render_finance_page($user); ?>
            <?php endif; ?>
        </div>
    </section>
    <?php
}

function render_delivery_page(array $user): void
{
    $orders = store()->deliveryOrders((int) $user['id']);
    $activeOrders = array_filter($orders, static fn(array $order): bool => !in_array($order['status'], ['delivered', 'cancelled'], true));
    ?>
    <section class="delivery-header">
        <div><p class="eyebrow">DELIVERY PARTNER HUB</p><h1>Hey, <?= h(explode(' ', $user['name'])[0]) ?>.<br><em>Let’s make it happen.</em></h1><p>Your route, order addresses, and every status update—made simple for the road.</p></div>
        <div class="delivery-signal"><span>↗</span><div><strong><?= count($activeOrders) ?> active drop<?= count($activeOrders) === 1 ? '' : 's' ?></strong><small>Today’s delivery queue</small></div></div>
    </section>
    <section class="delivery-layout content-section">
        <aside class="delivery-side-card"><div class="delivery-avatar"><?= avatar_content($user) ?></div><strong><?= h($user['name']) ?></strong><small>Verified delivery partner</small><div class="delivery-metric"><b><?= count($orders) ?></b><span>assigned<br>orders</span></div><div class="delivery-metric"><b><?= count(array_filter($orders, static fn(array $order): bool => $order['status'] === 'delivered')) ?></b><span>completed<br>drops</span></div><div class="delivery-side-note"><span>◈</span><p>Customer details are only visible for orders assigned to you.</p></div></aside>
        <div class="delivery-content"><div class="delivery-content-head"><div><p class="eyebrow">YOUR QUEUE</p><h2>Make every handoff count.</h2></div><span class="live-indicator"><i></i> Updated live</span></div><?php if ($orders): ?><div class="delivery-order-list"><?php foreach ($orders as $order): $nextStatus = $order['status'] === 'assigned' ? 'out_for_delivery' : ($order['status'] === 'out_for_delivery' ? 'delivered' : null); ?><article class="delivery-order-card <?= $order['status'] === 'delivered' ? 'completed' : '' ?>"><div class="delivery-order-head"><div><span class="order-number"><?= h($order['number']) ?></span><small><?= count($order['lines']) ?> item<?= count($order['lines']) === 1 ? '' : 's' ?> · <?= money((float) $order['grand_total']) ?></small></div><span class="status-pill <?= h(status_tone($order['status'])) ?>"><?= h(status_label($order['status'])) ?></span></div><div class="delivery-address"><span>⌖</span><div><strong><?= h($order['address']['recipient']) ?> · <?= h($order['address']['phone']) ?></strong><p><?= h($order['address']['line1']) ?>, <?= h($order['address']['city']) ?> — <?= h($order['address']['postal_code']) ?></p></div></div><div class="delivery-products"><?php foreach ($order['lines'] as $line): ?><span><?= product_visual($line) ?> <?= h($line['name']) ?> <b>×<?= (int) $line['quantity'] ?></b></span><?php endforeach; ?></div><div class="delivery-card-actions"><a class="text-button" href="<?= h(url('invoice', ['id' => $order['id'], 'track' => 1])) ?>">Open tracking</a><?php if ($nextStatus): ?><form method="post" class="delivery-update-form"><?= csrf_field() ?><input type="hidden" name="action" value="update_order_status"><input type="hidden" name="return_page" value="delivery"><input type="hidden" name="order_id" value="<?= (int) $order['id'] ?>"><input type="hidden" name="status" value="<?= h($nextStatus) ?>"><?php if ($nextStatus === 'delivered'): ?><input name="verification_code" required pattern="[0-9]{6}" maxlength="6" inputmode="numeric" autocomplete="off" placeholder="Customer’s 6-digit code" aria-label="Delivery verification code"><?php endif; ?><input name="note" aria-label="Delivery note" placeholder="<?= $nextStatus === 'delivered' ? 'Delivery note (optional)' : 'Location / pickup note (optional)' ?>"><button class="button button-primary" type="submit"><?= $nextStatus === 'delivered' ? 'Mark delivered ✓' : 'Start delivery ↗' ?></button></form><?php else: ?><span class="delivery-complete"><?= $order['status'] === 'cancelled' ? 'Order cancelled' : '✓ Delivery complete' ?></span><?php endif; ?></div></article><?php endforeach; ?></div><?php else: ?><div class="empty-state"><div class="empty-orb">↗</div><p class="eyebrow">CLEAR ROUTE</p><h2>No delivery jobs are assigned yet.</h2><p>New assignments from the store administrator will appear right here.</p></div><?php endif; ?></div>
    </section>
    <?php
}
