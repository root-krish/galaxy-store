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
    <meta name="theme-color" content="#08131d">
    <title><?= h($title) ?> · <?= h(SG_NAME) ?></title>
    <link rel="stylesheet" href="assets/css/app.css">
</head>
<body>
<div class="site-shell">
    <div class="announcement">
        <span class="announce-dot"></span>
        Free express delivery above ₹999 <span class="announcement-separator">·</span> Use <strong>GALAXY10</strong> for 10% off
        <span class="announcement-right">Secure checkout <span aria-hidden="true">✦</span> Live order tracking</span>
    </div>
    <header class="site-header">
        <div class="nav-wrap">
            <a class="brand" href="<?= h(url('home')) ?>" aria-label="Sports Galaxy home">
                <span class="brand-mark"><span>G</span></span>
                <span class="brand-text">SPORTS <b>GALAXY</b></span>
            </a>
            <button class="menu-toggle" type="button" aria-label="Toggle navigation" aria-expanded="false" data-menu-toggle>
                <span></span><span></span><span></span>
            </button>
            <nav class="main-nav" data-main-nav>
                <a class="<?= $page === 'home' ? 'active' : '' ?>" href="<?= h(url('home')) ?>">Home</a>
                <a class="<?= $page === 'shop' ? 'active' : '' ?>" href="<?= h(url('shop')) ?>">Shop</a>
                <a href="#why-galaxy">Why Galaxy</a>
                <?php if ($user && $user['role'] === 'customer'): ?>
                    <a class="<?= $page === 'account' ? 'active' : '' ?>" href="<?= h(url('account')) ?>">My orders</a>
                <?php endif; ?>
            </nav>
            <div class="nav-actions">
                <a class="cart-link <?= $page === 'cart' ? 'active' : '' ?>" href="<?= h(url('cart')) ?>" aria-label="Shopping cart">
                    <span class="cart-icon" aria-hidden="true">⌑</span>
                    <span class="cart-label">Cart</span>
                    <span class="cart-count"><?= cart_count() ?></span>
                </a>
                <?php if ($user): ?>
                    <?php $workspace = $user['role'] === 'admin' ? 'admin' : ($user['role'] === 'delivery' ? 'delivery' : 'account'); ?>
                    <a class="user-link" href="<?= h(url($workspace)) ?>">
                        <span class="avatar"><?= h(mb_strtoupper(mb_substr($user['name'], 0, 1))) ?></span>
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
                    <span class="brand-mark"><span>G</span></span>
                    <span class="brand-text">SPORTS <b>GALAXY</b></span>
                </a>
                <p>Curated gear for the way you play. Built for every first serve, final sprint, and weekend win.</p>
                <div class="social-row"><span>◎</span><span>◌</span><span>◈</span></div>
            </div>
            <div>
                <h3>Explore</h3>
                <a href="<?= h(url('shop')) ?>">All equipment</a>
                <a href="<?= h(url('shop', ['category' => 1])) ?>">Team sports</a>
                <a href="<?= h(url('shop', ['category' => 3])) ?>">Fitness</a>
            </div>
            <div>
                <h3>Service</h3>
                <a href="<?= h(url('cart')) ?>">Your cart</a>
                <a href="<?= h(url('login')) ?>">Order tracking</a>
                <a href="#why-galaxy">Delivery promise</a>
            </div>
            <div class="newsletter">
                <h3>Playbook drops</h3>
                <p>New gear, fresh offers, no noise.</p>
                <form action="<?= h(url('home')) ?>" method="get" onsubmit="return false;">
                    <input type="email" aria-label="Email address" placeholder="you@example.com">
                    <button aria-label="Subscribe" type="submit">→</button>
                </form>
            </div>
        </div>
        <div class="footer-bottom">
            <span>© <?= date('Y') ?> Sports Galaxy</span>
            <span>Made for the love of the game <b>✦</b></span>
            <span>Demo payment &amp; local data mode</span>
        </div>
    </footer>
</div>
<script src="assets/js/app.js"></script>
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
        <span class="availability unavailable">Restocking <?= h(format_date($product['restock_date'], 'd M')) ?></span>
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
    <article class="product-card <?= $compact ? 'product-card-compact' : '' ?> <?= $stockClass ?>" data-product-card data-name="<?= h(mb_strtolower($product['name'] . ' ' . $product['brand'] . ' ' . $category)) ?>" data-category="<?= (int) $product['category_id'] ?>">
        <div class="product-art art-<?= (int) $product['category_id'] ?>">
            <div class="art-orbit orbit-one"></div><div class="art-orbit orbit-two"></div>
            <span class="product-emoji" aria-hidden="true"><?= h($product['visual']) ?></span>
            <?php if ($product['featured']): ?><span class="featured-tag">GALAXY PICK</span><?php endif; ?>
            <?php if ($product['stock'] <= 0): ?><span class="soldout-tag">RESTOCKING</span><?php endif; ?>
            <span class="art-sku"><?= h($product['sku']) ?></span>
        </div>
        <div class="product-details">
            <div class="product-meta"><span><?= h($category) ?></span><span class="meta-dot">·</span><span><?= h($product['brand']) ?></span></div>
            <h3><?= h($product['name']) ?></h3>
            <?php if (!$compact): ?><p><?= h($product['description']) ?></p><?php endif; ?>
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
            <h1>Find your <em>game.</em><br>Own your <span>orbit.</span></h1>
            <p>Thoughtfully selected sport essentials, delivered with the kind of speed that keeps your momentum moving.</p>
            <div class="hero-actions">
                <a class="button button-primary button-large" href="<?= h(url('shop')) ?>">Shop the collection <span>→</span></a>
                <a class="button button-ghost button-large" href="#featured-products"><span class="play-mark">▶</span> See what’s new</a>
            </div>
            <div class="hero-proof">
                <div class="avatar-group"><span>⚽</span><span>🏏</span><span>🏸</span></div>
                <p><strong>4.9 / 5 from players</strong><br>Gear that earns a place in your kit.</p>
            </div>
        </div>
        <div class="hero-visual" aria-label="Sports gear orbit illustration">
            <div class="hero-grid"></div>
            <div class="planet planet-main"><span>⚽</span></div>
            <div class="planet planet-small planet-one">🏏</div>
            <div class="planet planet-small planet-two">🏸</div>
            <div class="planet planet-small planet-three">🏀</div>
            <div class="orbit orbit-large"></div><div class="orbit orbit-mid"></div><div class="orbit orbit-small"></div>
            <div class="hero-stat stat-one"><strong>48h</strong><span>dispatch</span></div>
            <div class="hero-stat stat-two"><strong>12k+</strong><span>players equipped</span></div>
            <div class="constellation c-one">✦</div><div class="constellation c-two">✦</div><div class="constellation c-three">·</div>
        </div>
        <div class="hero-scroll">SCROLL TO EXPLORE <span>↓</span></div>
    </section>

    <section class="benefit-strip">
        <div><span class="benefit-icon">✦</span><p><strong>Authentic equipment</strong><small>From trusted sport brands</small></p></div>
        <div><span class="benefit-icon">↗</span><p><strong>Quick dispatch</strong><small>Orders packed in 48 hours</small></p></div>
        <div><span class="benefit-icon">◈</span><p><strong>Secure payments</strong><small>COD, card and UPI options</small></p></div>
        <div><span class="benefit-icon">⌁</span><p><strong>Always in the loop</strong><small>Track every delivery step</small></p></div>
    </section>

    <section class="content-section categories-section">
        <?php render_section_heading('PICK YOUR PLAYGROUND', 'Whatever moves you.', 'A compact collection of serious equipment for everyday players.', url('shop'), 'Explore all gear'); ?>
        <div class="category-grid">
            <?php foreach ($categories as $category): ?>
                <a class="category-card category-<?= h($category['color']) ?>" href="<?= h(url('shop', ['category' => $category['id']])) ?>">
                    <span class="category-icon"><?= h($category['icon']) ?></span>
                    <span><strong><?= h($category['name']) ?></strong><small>Shop now <i>→</i></small></span>
                    <span class="category-spark">✦</span>
                </a>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="content-section featured-section" id="featured-products">
        <?php render_section_heading('CURATED FOR THE SEASON', 'The current line-up.', 'Reliable favourites, fresh kit, and no filler.', url('shop'), 'View the full store'); ?>
        <div class="product-grid">
            <?php foreach ($featured as $product): render_product_card($product); endforeach; ?>
        </div>
    </section>

    <section class="story-panel" id="why-galaxy">
        <div class="story-content">
            <p class="eyebrow">THE GALAXY STANDARD</p>
            <h2>Less time hunting for kit.<br><em>More time playing.</em></h2>
            <p>We made Sports Galaxy feel like your trusted local sports shop—just faster, clearer, and open whenever your next match calls.</p>
            <a class="text-link text-link-light" href="<?= h(url('shop')) ?>">Meet your next essential <span>→</span></a>
        </div>
        <div class="story-stats">
            <div><strong>5</strong><span>sports<br>categories</span></div>
            <div><strong>48h</strong><span>dispatch<br>promise</span></div>
            <div><strong>100%</strong><span>stock<br>visibility</span></div>
        </div>
        <div class="story-orb"><span>✦</span></div>
    </section>

    <section class="quote-strip">
        <span class="quote-mark">“</span>
        <p>Sport isn’t just what you play. It’s how you show up.</p>
        <span class="quote-star">✦</span>
    </section>
    <?php
}

function render_shop(): void
{
    $search = trim((string) ($_GET['q'] ?? ''));
    $categoryId = (int) ($_GET['category'] ?? 0);
    $products = store()->products($search, $categoryId ?: null);
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
            <span>⌕</span><input type="search" name="q" value="<?= h($search) ?>" placeholder="Search footballs, bats, fitness..." autocomplete="off" data-product-search>
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
        <div class="results-meta"><span><strong data-result-count><?= count($products) ?></strong> pieces of equipment</span><span>Updated stock <i class="live-dot"></i></span></div>
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
    $estimatedTax = round($cart['subtotal'] * 0.05, 2);
    $shipping = $cart['subtotal'] >= 999 ? 0 : 79;
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
                                <div class="cart-product-art art-<?= (int) $product['category_id'] ?>"><?= h($product['visual']) ?></div>
                                <div class="cart-product-copy">
                                    <span><?= h(category_name((int) $product['category_id'])) ?> · <?= h($product['brand']) ?></span>
                                    <h3><?= h($product['name']) ?></h3>
                                    <?php render_availability($product); ?>
                                </div>
                                <div class="quantity-control">
                                    <button type="button" data-quantity-down aria-label="Decrease quantity">−</button>
                                    <input type="number" min="1" max="<?= max(1, (int) $product['stock']) ?>" name="quantity[<?= (int) $product['id'] ?>]" value="<?= (int) $item['quantity'] ?>" data-quantity-input>
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
                <?php if ($user && $user['role'] === 'customer'): ?>
                    <a class="button button-primary button-full" href="<?= h(url('checkout')) ?>">Secure checkout <span>→</span></a>
                <?php elseif (!$user): ?>
                    <a class="button button-primary button-full" href="<?= h(url('login', ['next' => 'checkout'])) ?>">Sign in to check out <span>→</span></a>
                    <p class="summary-note">Browsing is open to everyone. A customer account keeps orders and invoices secure.</p>
                <?php else: ?>
                    <p class="summary-note">Please use a customer account to place an order. Admin and delivery accounts are protected workspaces.</p>
                <?php endif; ?>
                <div class="summary-assurance"><span>◈</span><p><strong>Safe and simple</strong><br>Choose COD, UPI, or card at checkout.</p></div>
            </aside>
        <?php else: ?>
            <div class="empty-state cart-empty">
                <div class="empty-orb">⌑</div><p class="eyebrow">YOUR LOCKER IS OPEN</p><h2>Nothing in your cart yet.</h2><p>Find the gear that makes you want to get back out there.</p><a class="button button-primary" href="<?= h(url('shop')) ?>">Explore equipment <span>→</span></a>
            </div>
        <?php endif; ?>
    </section>
    <?php
}

function render_checkout_page(array $user): void
{
    $cart = store()->cartDetails(cart());
    if ($cart['items'] === []) {
        flash('warning', 'Add something to your cart before checking out.');
        redirect('cart');
    }
    ?>
    <section class="page-intro compact-intro checkout-intro">
        <div><p class="eyebrow">SECURE CHECKOUT</p><h1>One last lap.</h1><p>Payment options are simulated safely for this project demo.</p></div>
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
                <div class="form-block-heading"><span class="form-step">02</span><div><h2>How would you like to pay?</h2><p>No real charge is made in this demo.</p></div></div>
                <div class="payment-options">
                    <label class="payment-choice"><input type="radio" name="payment_method" value="cod" checked><span class="payment-icon">₹</span><span><strong>Cash on delivery</strong><small>Pay when the order arrives</small></span><i></i></label>
                    <label class="payment-choice"><input type="radio" name="payment_method" value="upi"><span class="payment-icon">◌</span><span><strong>UPI</strong><small>Instant demo confirmation</small></span><i></i></label>
                    <label class="payment-choice"><input type="radio" name="payment_method" value="card"><span class="payment-icon">▣</span><span><strong>Credit / debit card</strong><small>Secure simulated payment</small></span><i></i></label>
                </div>
            </section>
            <section class="checkout-block coupon-block">
                <div class="form-block-heading"><span class="form-step">03</span><div><h2>Have a playmaker code?</h2><p>Try <strong>GALAXY10</strong> for 10% off this order.</p></div></div>
                <label class="coupon-input"><input name="coupon" placeholder="Offer code"><span>10% OFF</span></label>
            </section>
            <button class="button button-primary button-large checkout-submit" type="submit">Place secure order <span>→</span></button>
        </form>
        <aside class="checkout-summary">
            <p class="summary-label">YOUR ORDER</p>
            <?php foreach ($cart['items'] as $item): ?>
                <div class="checkout-line"><span class="mini-product-art art-<?= (int) $item['product']['category_id'] ?>"><?= h($item['product']['visual']) ?><b><?= (int) $item['quantity'] ?></b></span><span><strong><?= h($item['product']['name']) ?></strong><small><?= h($item['product']['brand']) ?></small></span><em><?= money((float) $item['line_total']) ?></em></div>
            <?php endforeach; ?>
            <div class="summary-divider"></div>
            <div class="summary-row"><span>Subtotal</span><strong><?= money((float) $cart['subtotal']) ?></strong></div>
            <div class="summary-row"><span>Delivery</span><strong><?= $cart['subtotal'] >= 999 ? 'Free' : '₹79.00' ?></strong></div>
            <div class="summary-row"><span>GST (estimated)</span><strong><?= money(round($cart['subtotal'] * 0.05, 2)) ?></strong></div>
            <div class="summary-total"><span>Total before offer</span><strong><?= money($cart['subtotal'] + ($cart['subtotal'] >= 999 ? 0 : 79) + round($cart['subtotal'] * 0.05, 2)) ?></strong></div>
            <div class="order-trust"><span>◈</span><p><strong>Inventory protected</strong><br>We verify every item before confirmation.</p></div>
        </aside>
    </section>
    <?php
}

function render_login_page(): void
{
    $next = preg_replace('/[^a-z_]/', '', (string) ($_GET['next'] ?? 'account')) ?: 'account';
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
                    <input type="hidden" name="action" value="login">
                    <input type="hidden" name="next" value="<?= h($next) ?>">
                    <label>Email address<input type="email" name="email" required autocomplete="email" placeholder="you@example.com"></label>
                    <label>Password<input type="password" name="password" required autocomplete="current-password" placeholder="••••••••"></label>
                    <button class="button button-primary button-full" type="submit">Sign in <span>→</span></button>
                </form>
                <p class="auth-switch">New to Sports Galaxy? <a href="<?= h(url('register')) ?>">Create an account</a></p>
                <div class="demo-accounts">
                    <p><span>◌</span> Demo access</p>
                    <div><button type="button" data-demo-login data-email="customer@sportsgalaxy.local" data-password="Customer@123">Customer</button><button type="button" data-demo-login data-email="admin@sportsgalaxy.local" data-password="Admin@123">Admin</button><button type="button" data-demo-login data-email="delivery@sportsgalaxy.local" data-password="Delivery@123">Delivery</button></div>
                </div>
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
                    <input type="hidden" name="action" value="register">
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
        <div class="account-welcome"><span class="avatar avatar-large"><?= h(mb_strtoupper(mb_substr($user['name'], 0, 1))) ?></span><div><p class="eyebrow">YOUR PLAYER PROFILE</p><h1>Hey, <?= h(explode(' ', $user['name'])[0]) ?>.</h1><p>Everything you need for your next game, in one clear view.</p></div></div>
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
                                <div class="order-product-previews"><?php foreach (array_slice($order['lines'], 0, 4) as $line): ?><span><?= h($line['visual']) ?></span><?php endforeach; ?><p><?= count($order['lines']) ?> item<?= count($order['lines']) === 1 ? '' : 's' ?> · <?= money((float) $order['grand_total']) ?></p></div>
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
                <div class="profile-card"><span class="avatar avatar-large"><?= h(mb_strtoupper(mb_substr($user['name'], 0, 1))) ?></span><div><strong><?= h($user['name']) ?></strong><p><?= h($user['email']) ?><br><?= h($user['phone']) ?></p></div><span class="profile-safe">◈ Account secured</span></div>
            </section>
        </div>
    </section>
    <?php
}

function render_invoice_page(array $order, bool $track = false): void
{
    ?>
    <section class="invoice-page <?= $track ? 'tracking-mode' : '' ?>">
        <div class="invoice-actions content-section">
            <a class="back-link" href="<?= h(current_user()['role'] === 'customer' ? url('account') : url(current_user()['role'] === 'admin' ? 'admin' : 'delivery')) ?>">← Back to workspace</a>
            <div><button class="button button-ghost print-button" type="button" onclick="window.print()">Print receipt <span>↗</span></button><?php if (!$track): ?><a class="button button-primary" href="<?= h(url('invoice', ['id' => $order['id'], 'track' => 1])) ?>">Track order <span>→</span></a><?php endif; ?></div>
        </div>
        <div class="invoice-layout content-section">
            <article class="invoice-card">
                <div class="invoice-brand"><a class="brand" href="<?= h(url('home')) ?>"><span class="brand-mark"><span>G</span></span><span class="brand-text">SPORTS <b>GALAXY</b></span></a><div><span>ORDER RECEIPT</span><strong><?= h($order['number']) ?></strong></div></div>
                <div class="invoice-meta"><div><span>Issued to</span><strong><?= h($order['address']['recipient']) ?></strong><p><?= h($order['address']['line1']) ?><br><?= h($order['address']['city']) ?> · <?= h($order['address']['postal_code']) ?><br><?= h($order['address']['phone']) ?></p></div><div><span>Order details</span><strong><?= h(format_date($order['created_at'], 'd M Y')) ?></strong><p>Tracking: <?= h($order['tracking_code']) ?><br>Payment: <?= h(strtoupper($order['payment_method'])) ?><br><b class="invoice-payment <?= h(status_tone($order['payment_status'])) ?>"><?= h(status_label($order['payment_status'])) ?></b></p></div></div>
                <div class="invoice-lines"><div class="invoice-line-head"><span>ITEM</span><span>QTY</span><span>TOTAL</span></div><?php foreach ($order['lines'] as $line): ?><div class="invoice-line"><span><b class="invoice-item-visual"><?= h($line['visual']) ?></b><span><strong><?= h($line['name']) ?></strong><small><?= h($line['sku']) ?> · <?= money((float) $line['unit_price']) ?></small></span></span><span>×<?= (int) $line['quantity'] ?></span><strong><?= money((float) $line['line_total']) ?></strong></div><?php endforeach; ?></div>
                <div class="invoice-totals"><div><span>Subtotal</span><strong><?= money((float) $order['subtotal']) ?></strong></div><?php if ($order['discount'] > 0): ?><div class="invoice-discount"><span><?= h($order['discount_name']) ?></span><strong>−<?= money((float) $order['discount']) ?></strong></div><?php endif; ?><div><span>GST (<?= h((string) $order['tax_rate']) ?>%)</span><strong><?= money((float) $order['tax']) ?></strong></div><div><span>Delivery</span><strong><?= (float) $order['shipping'] === 0.0 ? 'Free' : money((float) $order['shipping']) ?></strong></div><div class="invoice-grand"><span>Grand total</span><strong><?= money((float) $order['grand_total']) ?></strong></div></div>
                <div class="invoice-footer"><span>Thank you for playing with us. ✦</span><span>Tax invoice · Demo project record</span></div>
            </article>
            <aside class="tracking-panel">
                <p class="eyebrow"><?= $track ? 'LIVE DELIVERY VIEW' : 'ORDER STATUS' ?></p>
                <div class="tracking-head"><div><h2><?= h(status_label($order['status'])) ?></h2><p><?= h($order['status'] === 'delivered' ? 'This order is complete. Enjoy every minute of it.' : 'Tracking code: ' . $order['tracking_code']) ?></p></div><span class="tracking-status-icon"><?= $order['status'] === 'delivered' ? '✓' : '↗' ?></span></div>
                <ol class="timeline">
                    <?php foreach ($order['delivery_events'] as $index => $event): ?>
                        <li class="<?= $index === array_key_last($order['delivery_events']) ? 'current' : '' ?>"><i></i><div><strong><?= h(status_label($event['status'])) ?></strong><p><?= h($event['note']) ?></p><small><?= h(format_date($event['at'])) ?> · <?= h($event['actor']) ?></small></div></li>
                    <?php endforeach; ?>
                </ol>
                <?php if ($order['delivery_partner_id']): $partner = store()->user((int) $order['delivery_partner_id']); ?>
                    <div class="partner-card"><span class="partner-avatar"><?= h(mb_strtoupper(mb_substr($partner['name'], 0, 1))) ?></span><div><small>YOUR DELIVERY PARTNER</small><strong><?= h($partner['name']) ?></strong><p>Order and address details are protected in this workspace.</p></div></div>
                <?php endif; ?>
            </aside>
        </div>
    </section>
    <?php
}

function render_admin_page(array $user): void
{
    $tab = $_GET['tab'] ?? 'dashboard';
    $allowedTabs = ['dashboard', 'products', 'inventory', 'orders', 'finance'];
    $tab = in_array($tab, $allowedTabs, true) ? $tab : 'dashboard';
    $stats = store()->dashboardStats();
    $orders = store()->allOrders();
    $products = store()->products();
    $partners = store()->deliveryPartners();
    $editingProduct = isset($_GET['edit']) ? store()->product((int) $_GET['edit']) : null;
    ?>
    <section class="workspace-header admin-workspace-header">
        <div><div class="workspace-kicker"><span>CONTROL ROOM</span><i></i><small>ADMIN ACCESS</small></div><h1>Good morning, <?= h(explode(' ', $user['name'])[0]) ?>.</h1><p>Your shop is moving. Here’s the signal from Sports Galaxy today.</p></div>
        <div class="workspace-header-actions"><span class="live-indicator"><i></i> Live store data</span><a class="button button-dark" href="<?= h(url('shop')) ?>">View storefront ↗</a></div>
    </section>
    <section class="workspace-shell">
        <aside class="workspace-nav">
            <div class="workspace-user"><span class="avatar avatar-large"><?= h(mb_strtoupper(mb_substr($user['name'], 0, 1))) ?></span><div><strong><?= h($user['name']) ?></strong><small>Store administrator</small></div></div>
            <nav>
                <a class="<?= $tab === 'dashboard' ? 'active' : '' ?>" href="<?= h(url('admin', ['tab' => 'dashboard'])) ?>"><span>▦</span> Overview</a>
                <a class="<?= $tab === 'products' ? 'active' : '' ?>" href="<?= h(url('admin', ['tab' => 'products'])) ?>"><span>◈</span> Products</a>
                <a class="<?= $tab === 'inventory' ? 'active' : '' ?>" href="<?= h(url('admin', ['tab' => 'inventory'])) ?>"><span>⌑</span> Inventory <?php if ($stats['low_stock']): ?><b><?= count($stats['low_stock']) ?></b><?php endif; ?></a>
                <a class="<?= $tab === 'orders' ? 'active' : '' ?>" href="<?= h(url('admin', ['tab' => 'orders'])) ?>"><span>↗</span> Orders</a>
                <a class="<?= $tab === 'finance' ? 'active' : '' ?>" href="<?= h(url('admin', ['tab' => 'finance'])) ?>"><span>◒</span> Finance</a>
            </nav>
            <div class="workspace-nav-help"><span>✦</span><p><strong>Daily summary ready</strong><br><?= count($stats['low_stock']) ?> item<?= count($stats['low_stock']) === 1 ? '' : 's' ?> need inventory attention.</p></div>
            <a class="workspace-store-link" href="<?= h(url('home')) ?>">← Back to storefront</a>
        </aside>
        <div class="workspace-content">
            <?php if ($tab === 'dashboard'): ?>
                <div class="workspace-content-head"><div><p class="eyebrow">TODAY'S PULSE</p><h2>The dashboard at a glance.</h2></div><span class="date-chip"><?= date('D, d M Y') ?></span></div>
                <div class="metric-grid">
                    <article class="metric-card accent-lime"><span class="metric-icon">₹</span><div><small>Today’s revenue</small><strong><?= money((float) $stats['daily_revenue']) ?></strong><em>+12.4% vs last week</em></div></article>
                    <article class="metric-card accent-violet"><span class="metric-icon">⌑</span><div><small>Today’s transactions</small><strong><?= (int) $stats['daily_orders'] ?></strong><em><?= count($orders) ?> orders in demo history</em></div></article>
                    <article class="metric-card accent-cyan"><span class="metric-icon">◒</span><div><small>Monthly net profit</small><strong><?= money((float) $stats['month_profit']) ?></strong><em>After product cost &amp; expenses</em></div></article>
                    <article class="metric-card accent-orange"><span class="metric-icon">!</span><div><small>Low-stock alerts</small><strong><?= count($stats['low_stock']) ?></strong><em>Below individual threshold</em></div></article>
                </div>
                <div class="admin-main-grid">
                    <section class="admin-card revenue-card"><div class="admin-card-head"><div><p class="eyebrow">REVENUE SIGNAL</p><h3>Sales, seven day orbit.</h3></div><button class="chart-period">This week⌄</button></div><div class="bar-chart" aria-label="Weekly sales bar chart"><?php $bars = [28, 43, 34, 64, 48, 79, 55]; foreach ($bars as $i => $height): ?><div class="<?= $i === 5 ? 'highlight' : '' ?>"><i style="height: <?= $height ?>%"></i><span><?= ['M','T','W','T','F','S','S'][$i] ?></span></div><?php endforeach; ?></div><div class="chart-legend"><span><i class="legend-lime"></i> Revenue</span><strong><?= money((float) $stats['month_revenue']) ?> this month</strong></div></section>
                    <section class="admin-card top-products-card"><div class="admin-card-head"><div><p class="eyebrow">POPULAR NOW</p><h3>Top-selling equipment.</h3></div><a href="<?= h(url('admin', ['tab' => 'products'])) ?>">Manage →</a></div><ol class="rank-list"><?php $rank = 1; foreach ($stats['top_products'] ?: ['No sales yet' => 0] as $name => $quantity): ?><li><b><?= $rank++ ?></b><span><?= h($name) ?></span><strong><?= (int) $quantity ?> sold</strong></li><?php endforeach; ?></ol></section>
                </div>
                <div class="admin-main-grid lower-grid">
                    <section class="admin-card alert-card"><div class="admin-card-head"><div><p class="eyebrow">INVENTORY WATCH</p><h3>Items asking for a refill.</h3></div><a href="<?= h(url('admin', ['tab' => 'inventory'])) ?>">Open inventory →</a></div><?php if ($stats['low_stock']): ?><div class="low-stock-list"><?php foreach (array_slice($stats['low_stock'], 0, 4) as $product): ?><div><span class="mini-product-art art-<?= (int) $product['category_id'] ?>"><?= h($product['visual']) ?></span><span><strong><?= h($product['name']) ?></strong><small><?= (int) $product['stock'] ?> / <?= (int) $product['min_stock'] ?> units · <?= $product['stock'] === 0 ? 'restocking ' . format_date($product['restock_date'], 'd M') : 'threshold breached' ?></small></span><a href="<?= h(url('admin', ['tab' => 'inventory'])) ?>">Adjust</a></div><?php endforeach; ?></div><?php else: ?><p class="quiet-copy">All products are currently above their minimum stock level.</p><?php endif; ?></section>
                    <section class="admin-card recent-orders-card"><div class="admin-card-head"><div><p class="eyebrow">LATEST ACTIVITY</p><h3>Recent orders.</h3></div><a href="<?= h(url('admin', ['tab' => 'orders'])) ?>">View all →</a></div><div class="mini-order-list"><?php foreach (array_slice($orders, 0, 4) as $order): ?><div><span class="order-mini-avatar"><?= h(mb_strtoupper(mb_substr($order['customer_name'], 0, 1))) ?></span><span><strong><?= h($order['customer_name']) ?></strong><small><?= h($order['number']) ?></small></span><em><?= money((float) $order['grand_total']) ?></em><i class="status-dot <?= h(status_tone($order['status'])) ?>"></i></div><?php endforeach; ?></div></section>
                </div>
            <?php elseif ($tab === 'products'): ?>
                <div class="workspace-content-head"><div><p class="eyebrow">PRODUCT CATALOG</p><h2><?= $editingProduct ? 'Fine-tune an item.' : 'Add to the equipment room.' ?></h2></div><a class="button button-ghost" href="<?= h(url('admin', ['tab' => 'products'])) ?>">+ New product</a></div>
                <section class="admin-editor-card">
                    <form method="post" class="product-editor-form">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="save_product"><input type="hidden" name="return_page" value="admin"><input type="hidden" name="return_tab" value="products"><input type="hidden" name="id" value="<?= (int) ($editingProduct['id'] ?? 0) ?>">
                        <div class="form-grid product-form-grid">
                            <label>Product name<input required name="name" value="<?= h($editingProduct['name'] ?? '') ?>" placeholder="e.g. Pro training football"></label>
                            <label>SKU<input required name="sku" value="<?= h($editingProduct['sku'] ?? '') ?>" placeholder="SG-FB-001"></label>
                            <label>Brand<input required name="brand" value="<?= h($editingProduct['brand'] ?? '') ?>" placeholder="Brand name"></label>
                            <label>Category<select name="category_id"><?php foreach (store()->categories() as $category): ?><option value="<?= (int) $category['id'] ?>" <?= (int) ($editingProduct['category_id'] ?? 1) === (int) $category['id'] ? 'selected' : '' ?>><?= h($category['name']) ?></option><?php endforeach; ?></select></label>
                            <label>Selling price<input required type="number" min="0" step="0.01" name="price" value="<?= h($editingProduct['price'] ?? '') ?>" placeholder="0.00"></label>
                            <label>Unit cost<input required type="number" min="0" step="0.01" name="cost" value="<?= h($editingProduct['cost'] ?? '') ?>" placeholder="0.00"></label>
                            <label>Current stock<input required type="number" min="0" name="stock" value="<?= h($editingProduct['stock'] ?? '0') ?>"></label>
                            <label>Low-stock threshold<input required type="number" min="1" name="min_stock" value="<?= h($editingProduct['min_stock'] ?? '5') ?>"></label>
                            <label>Expected restock<input type="date" name="restock_date" value="<?= h($editingProduct['restock_date'] ?? '') ?>"></label>
                            <label>Product icon<select name="visual"><?php foreach (['⚽','🏏','🏀','🏸','🎾','🏃','🧘','🏋️','🥊','🚴'] as $visual): ?><option value="<?= h($visual) ?>" <?= ($editingProduct['visual'] ?? '⚽') === $visual ? 'selected' : '' ?>><?= h($visual) ?> Gear</option><?php endforeach; ?></select></label>
                            <label class="wide">Description<textarea required name="description" rows="2" placeholder="A short helpful product description"><?= h($editingProduct['description'] ?? '') ?></textarea></label>
                        </div>
                        <label class="checkbox-label"><input type="checkbox" name="featured" <?= !empty($editingProduct['featured']) ? 'checked' : '' ?>> <span>Feature this product on the storefront</span></label>
                        <button class="button button-primary" type="submit"><?= $editingProduct ? 'Save changes' : 'Add product' ?> <span>→</span></button>
                    </form>
                </section>
                <section class="admin-card catalog-table-card"><div class="admin-card-head"><div><p class="eyebrow">ALL EQUIPMENT</p><h3><?= count($products) ?> active products.</h3></div><span class="table-hint">Click edit to modify</span></div><div class="table-scroll"><table class="admin-table"><thead><tr><th>Product</th><th>SKU</th><th>Price</th><th>Stock</th><th>Status</th><th></th></tr></thead><tbody><?php foreach ($products as $product): ?><tr><td><span class="table-product"><i class="mini-product-art art-<?= (int) $product['category_id'] ?>"><?= h($product['visual']) ?></i><span><strong><?= h($product['name']) ?></strong><small><?= h($product['brand']) ?> · <?= h(category_name((int) $product['category_id'])) ?></small></span></span></td><td><?= h($product['sku']) ?></td><td><?= money((float) $product['price']) ?></td><td><?= (int) $product['stock'] ?> <small>/ min <?= (int) $product['min_stock'] ?></small></td><td><?php render_availability($product); ?></td><td><a class="table-action" href="<?= h(url('admin', ['tab' => 'products', 'edit' => $product['id']])) ?>">Edit</a></td></tr><?php endforeach; ?></tbody></table></div></section>
            <?php elseif ($tab === 'inventory'): ?>
                <div class="workspace-content-head"><div><p class="eyebrow">STOCK CONTROL</p><h2>Keep every shelf in orbit.</h2></div><span class="inventory-key"><i></i> < 5 = attention</span></div>
                <section class="admin-card inventory-table-card"><div class="admin-card-head"><div><p class="eyebrow">LIVE INVENTORY</p><h3>Receive stock or record an adjustment.</h3></div><span class="table-hint">Each change is logged</span></div><div class="inventory-list"><?php foreach ($products as $product): ?><article class="inventory-row"><div class="inventory-product"><span class="mini-product-art art-<?= (int) $product['category_id'] ?>"><?= h($product['visual']) ?></span><div><strong><?= h($product['name']) ?></strong><small><?= h($product['sku']) ?> · Reorder at <?= (int) $product['min_stock'] ?></small></div></div><div class="stock-meter-wrap"><div class="stock-number <?= $product['stock'] < $product['min_stock'] ? 'low' : '' ?>"><?= (int) $product['stock'] ?><small>units</small></div><div class="stock-meter"><i style="width: <?= min(100, max(6, ($product['stock'] / max(1, $product['min_stock'] * 3)) * 100)) ?>%"></i></div></div><form method="post" class="inventory-adjust-form"><?= csrf_field() ?><input type="hidden" name="action" value="adjust_stock"><input type="hidden" name="return_page" value="admin"><input type="hidden" name="return_tab" value="inventory"><input type="hidden" name="product_id" value="<?= (int) $product['id'] ?>"><input type="number" name="delta" value="1" min="-<?= (int) $product['stock'] ?>" max="999" aria-label="Quantity change for <?= h($product['name']) ?>"><input name="note" placeholder="Reason (optional)" aria-label="Adjustment note"><button class="button button-small button-dark" type="submit">Apply</button></form></article><?php endforeach; ?></div></section>
            <?php elseif ($tab === 'orders'): ?>
                <div class="workspace-content-head"><div><p class="eyebrow">ORDER COMMAND</p><h2>From checkout to doorstep.</h2></div><span class="date-chip"><?= count($orders) ?> order<?= count($orders) === 1 ? '' : 's' ?></span></div>
                <section class="admin-card orders-management-card"><div class="table-scroll"><table class="admin-table orders-table"><thead><tr><th>Order</th><th>Customer</th><th>Items</th><th>Total</th><th>Delivery</th><th>Status</th><th></th></tr></thead><tbody><?php foreach ($orders as $order): ?><tr><td><strong><?= h($order['number']) ?></strong><small><?= h(format_date($order['created_at'], 'd M, h:i A')) ?></small></td><td><strong><?= h($order['customer_name']) ?></strong><small><?= h($order['address']['city']) ?></small></td><td><?= count($order['lines']) ?> item<?= count($order['lines']) === 1 ? '' : 's' ?></td><td><strong><?= money((float) $order['grand_total']) ?></strong><small><?= h(strtoupper($order['payment_method'])) ?> · <?= h($order['payment_status']) ?></small></td><td><form method="post" class="inline-select-form"><?= csrf_field() ?><input type="hidden" name="action" value="assign_delivery"><input type="hidden" name="return_page" value="admin"><input type="hidden" name="return_tab" value="orders"><input type="hidden" name="order_id" value="<?= (int) $order['id'] ?>"><select name="delivery_partner_id" onchange="this.form.submit()"><option value="">Assign partner</option><?php foreach ($partners as $partner): ?><option value="<?= (int) $partner['id'] ?>" <?= (int) $order['delivery_partner_id'] === (int) $partner['id'] ? 'selected' : '' ?>><?= h(explode(' ', $partner['name'])[0]) ?></option><?php endforeach; ?></select></form></td><td><span class="status-pill <?= h(status_tone($order['status'])) ?>"><?= h(status_label($order['status'])) ?></span></td><td><div class="row-actions"><a class="table-action" href="<?= h(url('invoice', ['id' => $order['id'], 'track' => 1])) ?>">Track</a><?php if (!in_array($order['status'], ['delivered', 'cancelled'], true)): ?><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="update_order_status"><input type="hidden" name="return_page" value="admin"><input type="hidden" name="return_tab" value="orders"><input type="hidden" name="order_id" value="<?= (int) $order['id'] ?>"><input type="hidden" name="status" value="packed"><button class="table-action" type="submit">Pack</button></form><?php endif; ?></div></td></tr><?php endforeach; ?></tbody></table></div></section>
            <?php elseif ($tab === 'finance'): ?>
                <div class="workspace-content-head"><div><p class="eyebrow">FINANCE DESK</p><h2>Numbers with context.</h2></div><span class="date-chip"><?= date('F Y') ?></span></div>
                <div class="finance-summary"><article><small>Monthly sales revenue</small><strong><?= money((float) $stats['month_revenue']) ?></strong></article><span>−</span><article><small>Item cost snapshots</small><strong><?= money((float) $stats['month_cost']) ?></strong></article><span>−</span><article><small>Operating expenses</small><strong><?= money((float) $stats['month_expenses']) ?></strong></article><span>=</span><article class="profit"><small>Net profit</small><strong><?= money((float) $stats['month_profit']) ?></strong></article></div>
                <div class="admin-main-grid lower-grid"><section class="admin-card expense-form-card"><p class="eyebrow">LOG AN EXPENSE</p><h3>Keep profit honest.</h3><form method="post" class="stacked-form"><?= csrf_field() ?><input type="hidden" name="action" value="add_expense"><input type="hidden" name="return_page" value="admin"><input type="hidden" name="return_tab" value="finance"><label>Date<input type="date" name="date" value="<?= date('Y-m-d') ?>"></label><label>Category<input name="category" required placeholder="e.g. Utilities"></label><label>Description<input name="description" required placeholder="What was this for?"></label><label>Amount<input type="number" min="0.01" step="0.01" name="amount" required placeholder="0.00"></label><button class="button button-primary" type="submit">Add expense <span>→</span></button></form></section><section class="admin-card expenses-list-card"><div class="admin-card-head"><div><p class="eyebrow">RECENT OPERATING COSTS</p><h3>Expense ledger.</h3></div></div><div class="expense-list"><?php foreach (store()->expenses() as $expense): ?><div><span class="expense-icon">◒</span><span><strong><?= h($expense['category']) ?></strong><small><?= h($expense['description']) ?> · <?= h(format_date($expense['date'], 'd M')) ?></small></span><em>−<?= money((float) $expense['amount']) ?></em></div><?php endforeach; ?></div></section></div>
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
        <aside class="delivery-side-card"><div class="delivery-avatar"><?= h(mb_strtoupper(mb_substr($user['name'], 0, 1))) ?></div><strong><?= h($user['name']) ?></strong><small>Verified delivery partner</small><div class="delivery-metric"><b><?= count($orders) ?></b><span>assigned<br>orders</span></div><div class="delivery-metric"><b><?= count(array_filter($orders, static fn(array $order): bool => $order['status'] === 'delivered')) ?></b><span>completed<br>drops</span></div><div class="delivery-side-note"><span>◈</span><p>Customer details are only visible for orders assigned to you.</p></div></aside>
        <div class="delivery-content"><div class="delivery-content-head"><div><p class="eyebrow">YOUR QUEUE</p><h2>Make every handoff count.</h2></div><span class="live-indicator"><i></i> Updated live</span></div><?php if ($orders): ?><div class="delivery-order-list"><?php foreach ($orders as $order): $nextStatus = $order['status'] === 'assigned' ? 'out_for_delivery' : ($order['status'] === 'out_for_delivery' ? 'delivered' : null); ?><article class="delivery-order-card <?= $order['status'] === 'delivered' ? 'completed' : '' ?>"><div class="delivery-order-head"><div><span class="order-number"><?= h($order['number']) ?></span><small><?= count($order['lines']) ?> item<?= count($order['lines']) === 1 ? '' : 's' ?> · <?= money((float) $order['grand_total']) ?></small></div><span class="status-pill <?= h(status_tone($order['status'])) ?>"><?= h(status_label($order['status'])) ?></span></div><div class="delivery-address"><span>⌖</span><div><strong><?= h($order['address']['recipient']) ?> · <?= h($order['address']['phone']) ?></strong><p><?= h($order['address']['line1']) ?>, <?= h($order['address']['city']) ?> — <?= h($order['address']['postal_code']) ?></p></div></div><div class="delivery-products"><?php foreach ($order['lines'] as $line): ?><span><?= h($line['visual']) ?> <?= h($line['name']) ?> <b>×<?= (int) $line['quantity'] ?></b></span><?php endforeach; ?></div><div class="delivery-card-actions"><a class="text-button" href="<?= h(url('invoice', ['id' => $order['id'], 'track' => 1])) ?>">Open tracking</a><?php if ($nextStatus): ?><form method="post" class="delivery-update-form"><?= csrf_field() ?><input type="hidden" name="action" value="update_order_status"><input type="hidden" name="return_page" value="delivery"><input type="hidden" name="order_id" value="<?= (int) $order['id'] ?>"><input type="hidden" name="status" value="<?= h($nextStatus) ?>"><input name="note" placeholder="<?= $nextStatus === 'delivered' ? 'Delivery note (optional)' : 'Location / pickup note (optional)' ?>"><button class="button button-primary" type="submit"><?= $nextStatus === 'delivered' ? 'Mark delivered ✓' : 'Start delivery ↗' ?></button></form><?php else: ?><span class="delivery-complete">✓ Delivery complete</span><?php endif; ?></div></article><?php endforeach; ?></div><?php else: ?><div class="empty-state"><div class="empty-orb">↗</div><p class="eyebrow">CLEAR ROUTE</p><h2>No delivery jobs are assigned yet.</h2><p>New assignments from the store administrator will appear right here.</p></div><?php endif; ?></div>
    </section>
    <?php
}
