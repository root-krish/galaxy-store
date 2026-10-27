<?php
declare(strict_types=1);

function admin_form_fields(string $action, string $tab): void
{
    echo csrf_field() . '<input type="hidden" name="action" value="' . h($action) . '"><input type="hidden" name="return_page" value="admin"><input type="hidden" name="return_tab" value="' . h($tab) . '">';
}

function category_visual(array $category): string
{
    if (!empty($category['image_path']) && ImageUploads::isValidName($category['image_path'])) {
        return '<img src="' . h(url('media', ['image' => $category['image_path']])) . '" alt="" loading="lazy">';
    }
    $asset = equipment_asset($category['icon']);
    return $asset ? '<img class="product-illustration" src="' . h($asset) . '" alt="" loading="lazy">' : h($category['icon']);
}

function render_categories_admin(): void
{
    $category = store()->category((int) ($_GET['edit_category'] ?? 0));
    ?>
    <div class="workspace-content-head"><div><p class="eyebrow">CATEGORIES &amp; GEAR</p><h2><?= $category ? 'Edit a category.' : 'Make room for a new sport.' ?></h2><p>Create a category, choose its look, then add products to it.</p></div><a class="button button-ghost" href="<?= h(url('admin', ['tab' => 'categories'])) ?>">+ New category</a></div>
    <div class="admin-main-grid lower-grid">
        <section class="admin-card"><form method="post" enctype="multipart/form-data" class="stacked-form category-editor-form">
            <?php admin_form_fields('save_category', 'categories'); ?><input type="hidden" name="id" value="<?= (int) ($category['id'] ?? 0) ?>">
            <label>Category name<input name="name" required minlength="2" maxlength="100" value="<?= h($category['name'] ?? '') ?>" placeholder="e.g. Swimming"></label>
            <label>Icon or emoji<input name="icon" required maxlength="16" value="<?= h($category['icon'] ?? '✦') ?>" list="gear-icons"></label>
            <datalist id="gear-icons"><?php foreach (['⚽', '🏏', '🏀', '🏸', '🏊', '🎾', '🥊', '🚴', '🛹', '✦'] as $icon): ?><option value="<?= h($icon) ?>"><?php endforeach; ?></datalist>
            <label>Tile colour<select name="color"><?php foreach (['lime' => 'Lime', 'violet' => 'Violet', 'orange' => 'Orange', 'blue' => 'Blue', 'pink' => 'Pink'] as $value => $name): ?><option value="<?= h($value) ?>" <?= ($category['color'] ?? 'lime') === $value ? 'selected' : '' ?>><?= h($name) ?></option><?php endforeach; ?></select></label>
            <?php if ($category): ?><span class="category-icon category-current"><?= category_visual($category) ?></span><?php endif; ?>
            <?php render_photo_input('category_photo', 'Category photo (optional)', !empty($category['image_path'])); ?>
            <button class="button button-primary" type="submit">Save category</button>
        </form></section>
        <section class="admin-card"><p class="eyebrow">YOUR COLLECTIONS</p><h3>Organise your equipment.</h3><div class="category-admin-list">
            <?php foreach (store()->categories() as $item): ?>
                <article><span class="category-icon category-<?= h($item['color']) ?>"><?= category_visual($item) ?></span><div><strong><?= h($item['name']) ?></strong><small><?= count(store()->products(null, (int) $item['id'])) ?> products</small><a class="text-link" href="<?= h(url('admin', ['tab' => 'categories', 'edit_category' => $item['id']])) ?>">Edit category</a></div><a class="button button-small button-ghost" href="<?= h(url('admin', ['tab' => 'products', 'category_id' => $item['id']])) ?>">+ Add gear</a></article>
            <?php endforeach; ?>
        </div></section>
    </div>
    <?php
}

function render_administrators_admin(): void
{
    ?>
    <div class="workspace-content-head"><div><p class="eyebrow">ADMINISTRATORS</p><h2>Build your store team.</h2><p>New administrators have full access to products, orders, accounts, finance, and website settings.</p></div><a class="text-link" href="<?= h(url('profile')) ?>">Edit my profile →</a></div>
    <div class="admin-main-grid lower-grid"><section class="admin-card"><h3>Create an administrator</h3><form method="post" class="stacked-form admin-account-form">
        <?php admin_form_fields('create_admin', 'admins'); render_contact_fields([]); ?>
        <label>Initial password<input type="password" name="password" required minlength="8" maxlength="72" autocomplete="new-password"></label><button class="button button-primary" type="submit">Create administrator</button>
    </form></section><section class="admin-card"><p class="eyebrow">STORE ADMINISTRATORS</p><h3>People with full access.</h3>
        <?php foreach (store()->administrators() as $admin): ?><div class="profile-card"><span class="avatar"><?= avatar_content($admin) ?></span><div><strong><?= h($admin['name']) ?></strong><p><?= h($admin['email']) ?><br><?= h($admin['phone']) ?></p></div></div><?php endforeach; ?>
    </section></div>
    <?php
}

function render_partner_account(array $partner): void
{
    $deleted = !empty($partner['deleted_at']);
    $state = $deleted ? 'Deleted' : ($partner['is_active'] ? 'Active' : 'Blocked');
    ?>
    <article class="partner-account">
        <div class="profile-card"><span class="avatar"><?= avatar_content($partner) ?></span><div><strong><?= h($partner['name']) ?></strong><p><?= h($partner['email']) ?><br><?= h($partner['phone']) ?></p><?php if (!$deleted): ?><a class="text-link" href="<?= h(url('admin', ['tab' => 'partners', 'edit_partner' => $partner['id']])) ?>">Edit details →</a><?php endif; ?></div><span class="status-pill <?= $partner['is_active'] ? 'success' : 'danger' ?>"><?= h($state) ?></span></div>
        <?php if (!empty($partner['account_events'])): ?><details class="account-history"><summary>Account history</summary><ol><?php foreach (array_reverse($partner['account_events']) as $event): ?><li><strong><?= h(ucfirst($event['action'])) ?></strong> · <?= h(format_date($event['at'])) ?><p><?= h($event['reason']) ?></p><small>By <?= h($event['actor']) ?></small></li><?php endforeach; ?></ol></details><?php endif; ?>
        <?php if (!$deleted): ?><details class="partner-account-controls"><summary>Manage account access</summary><form method="post" class="stacked-form partner-status-form" data-reason-form>
            <?php admin_form_fields('manage_delivery_partner', 'partners'); ?><input type="hidden" name="partner_id" value="<?= (int) $partner['id'] ?>">
            <label>Account action<select name="operation"><option value="<?= $partner['is_active'] ? 'block' : 'restore' ?>"><?= $partner['is_active'] ? 'Block access' : 'Restore access' ?></option><option value="delete">Delete account permanently</option></select></label>
            <label>Reason for this action<textarea name="reason" required minlength="5" maxlength="500" rows="3" placeholder="Explain why you are changing this account’s access"></textarea></label><p class="quiet-copy">Blocking or deleting ends access immediately and returns active jobs for reassignment. Deletion cannot be restored.</p><button class="button button-small button-dark" type="submit">Apply account action</button>
        </form></details><?php endif; ?>
    </article>
    <?php
}

function render_extended_settings(array $settings): void
{
    ?>
    <fieldset class="settings-group"><legend>Homepage &amp; announcement</legend>
        <label>Announcement text<input name="announcement_text" maxlength="160" value="<?= h($settings['announcement_text']) ?>" placeholder="Leave blank to hide the announcement"></label>
        <label>Homepage heading<input name="hero_title" required minlength="2" maxlength="120" value="<?= h($settings['hero_title']) ?>"></label>
        <label>Homepage description<textarea name="hero_description" required minlength="2" maxlength="300" rows="3"><?= h($settings['hero_description']) ?></textarea></label>
        <label>Shop button text<input name="hero_button_text" required minlength="2" maxlength="40" value="<?= h($settings['hero_button_text']) ?>"></label>
        <label>Footer description<textarea name="footer_description" maxlength="300" rows="3"><?= h($settings['footer_description']) ?></textarea></label>
    </fieldset>
    <fieldset class="settings-group"><legend>Fonts, layout &amp; sections</legend>
        <label>Secondary colour<input type="color" name="secondary_color" value="<?= h($settings['secondary_color']) ?>"></label>
        <label>Heading font<select name="heading_font"><?php foreach (['classic' => 'Classic serif', 'modern' => 'Modern sans serif', 'rounded' => 'Friendly sans serif'] as $value => $name): ?><option value="<?= h($value) ?>" <?= $settings['heading_font'] === $value ? 'selected' : '' ?>><?= h($name) ?></option><?php endforeach; ?></select></label>
        <label>Product card shape<select name="card_style"><option value="rounded" <?= $settings['card_style'] === 'rounded' ? 'selected' : '' ?>>Rounded corners</option><option value="square" <?= $settings['card_style'] === 'square' ? 'selected' : '' ?>>Square corners</option></select></label>
        <label>Products per row on desktop<select name="catalogue_columns"><?php foreach (['3', '4'] as $value): ?><option value="<?= h($value) ?>" <?= $settings['catalogue_columns'] === $value ? 'selected' : '' ?>><?= h($value) ?> columns</option><?php endforeach; ?></select></label>
        <?php foreach (['show_categories' => 'Show categories', 'show_featured' => 'Show featured products', 'show_benefits' => 'Show service benefits', 'show_story' => 'Show our story', 'show_quote' => 'Show sports quote'] as $key => $label): ?><input type="hidden" name="<?= h($key) ?>" value="0"><label class="checkbox-label"><input type="checkbox" name="<?= h($key) ?>" value="1" <?= $settings[$key] === '1' ? 'checked' : '' ?>><span><?= h($label) ?></span></label><?php endforeach; ?>
    </fieldset>
    <fieldset class="settings-group"><legend>Public contact details</legend><label>Support email<input type="email" name="support_email" maxlength="190" value="<?= h($settings['support_email']) ?>"></label><label>Support phone<input type="tel" name="support_phone" maxlength="30" value="<?= h($settings['support_phone']) ?>"></label><p class="quiet-copy">These details appear in the footer. Leave blank to hide them.</p></fieldset>
    <?php
}

function render_order_command(array $orders, array $partners): void
{
    echo '<form method="post">' . csrf_field() . '<input type="hidden" name="action" value="export_orders"><button class="button button-ghost" type="submit">Export orders (CSV / Excel)</button></form>';
    $search = trim((string) ($_GET['order_q'] ?? ''));
    $status = (string) ($_GET['order_status'] ?? 'all');
    $filters = ['all' => 'All orders', 'waiting' => 'To prepare', 'packed' => 'Ready to assign', 'assigned' => 'Assigned', 'out_for_delivery' => 'On the road', 'delivered' => 'Delivered', 'cancelled' => 'Cancelled'];
    if (!isset($filters[$status])) $status = 'all';
    $matches = static fn(array $order, string $filter): bool => $filter === 'all' || ($filter === 'waiting' ? in_array($order['status'], ['placed', 'confirmed'], true) : $order['status'] === $filter);
    $visible = array_filter($orders, static function (array $order) use ($search, $status, $matches): bool {
        $haystack = $order['number'] . ' ' . $order['customer_name'] . ' ' . implode(' ', $order['address']) . ' ' . implode(' ', array_column($order['lines'], 'name'));
        return $matches($order, $status) && ($search === '' || str_contains(mb_strtolower($haystack), mb_strtolower($search)));
    });
    ?>
    <div class="workspace-content-head"><div><p class="eyebrow">ORDER COMMAND</p><h2>Every order. One clear next step.</h2><p>Prepare, assign, and follow every delivery from here.</p></div><span class="date-chip"><?= count($orders) ?> orders</span></div>
    <nav class="order-filter-tabs" aria-label="Order status filters"><?php foreach ($filters as $key => $label): $count = count(array_filter($orders, static fn(array $order): bool => $matches($order, $key))); ?><a class="<?= $key === $status ? 'active' : '' ?>" <?= $key === $status ? 'aria-current="page"' : '' ?> href="<?= h(url('admin', ['tab' => 'orders', 'order_status' => $key, 'order_q' => $search])) ?>"><?= h($label) ?><b><?= $count ?></b></a><?php endforeach; ?></nav>
    <form method="get" class="order-search-form"><input type="hidden" name="page" value="admin"><input type="hidden" name="tab" value="orders"><input type="hidden" name="order_status" value="<?= h($status) ?>"><label>Find an order<input type="search" name="order_q" value="<?= h($search) ?>" placeholder="Order number, customer, phone, city, or item"></label><button class="button button-dark" type="submit">Search orders</button><?php if ($search !== '' || $status !== 'all'): ?><a class="text-link" href="<?= h(url('admin', ['tab' => 'orders'])) ?>">Clear filters</a><?php endif; ?></form>
    <p class="order-result-count" role="status"><?= count($visible) ?> of <?= count($orders) ?> orders shown</p>
    <div class="order-command-list">
        <?php foreach ($visible as $order): $closed = in_array($order['status'], ['delivered', 'cancelled'], true); $assigned = $order['delivery_partner_id'] ? store()->user((int) $order['delivery_partner_id']) : null; ?>
            <article class="order-command-card" data-order-id="<?= (int) $order['id'] ?>">
                <header class="order-card-heading"><div><a class="order-number" href="<?= h(url('invoice', ['id' => $order['id'], 'track' => 1])) ?>"><?= h($order['number']) ?> ↗</a><small><?= h(format_date($order['created_at'], 'd M Y · h:i A')) ?></small></div><span class="status-pill <?= h(status_tone($order['status'])) ?>"><?= h(status_label($order['status'])) ?></span><div class="order-card-total"><strong><?= money((float) $order['grand_total']) ?></strong><small><?= h(strtoupper($order['payment_method'])) ?> · <?= h(status_label($order['payment_status'])) ?></small></div></header>
                <div class="order-card-body"><div class="order-customer"><span class="order-mini-avatar"><?= h(mb_strtoupper(mb_substr($order['customer_name'], 0, 1))) ?></span><div><strong><?= h($order['address']['recipient']) ?></strong><p><?= h($order['address']['phone']) ?><br><?= h($order['address']['line1']) ?>, <?= h($order['address']['city']) ?> — <?= h($order['address']['postal_code']) ?></p></div></div><details class="order-items-details"><summary><?= array_sum(array_column($order['lines'], 'quantity')) ?> unit<?= array_sum(array_column($order['lines'], 'quantity')) === 1 ? '' : 's' ?> · <?= count($order['lines']) ?> item<?= count($order['lines']) === 1 ? '' : 's' ?></summary><ul><?php foreach ($order['lines'] as $line): ?><li><?= (int) $line['quantity'] ?> × <?= h($line['name']) ?> <strong><?= money((float) $line['line_total']) ?></strong></li><?php endforeach; ?></ul></details></div>
                <div class="order-card-workflow"><section><p class="eyebrow">DELIVERY</p>
                    <?php if (!$closed && in_array($order['status'], ['placed', 'confirmed', 'packed', 'assigned'], true)): ?>
                        <form method="post" class="order-assign-form"><?php admin_form_fields('assign_delivery', 'orders'); ?><input type="hidden" name="order_id" value="<?= (int) $order['id'] ?>"><label>Delivery partner<select name="delivery_partner_id" required><option value="">Choose an available partner</option><?php foreach ($partners as $partner): if ((int) $partner['id'] === (int) $order['user_id']) continue; ?><option value="<?= (int) $partner['id'] ?>" <?= (int) $order['delivery_partner_id'] === (int) $partner['id'] ? 'selected' : '' ?>><?= h($partner['name']) ?></option><?php endforeach; ?></select></label><button class="button button-small button-primary" type="submit"><?= $assigned ? 'Reassign' : 'Assign delivery' ?></button></form>
                        <?php if (!$partners): ?><p class="quiet-copy">Create or restore a partner in Delivery team to assign this order.</p><?php endif; ?>
                    <?php else: ?><p class="quiet-copy"><?= $assigned ? h($assigned['name']) : 'No delivery partner' ?><?= $order['status'] === 'out_for_delivery' ? ' · Delivery in progress' : '' ?></p><?php endif; ?>
                </section><section class="order-next-action"><p class="eyebrow"><?= $closed ? 'ORDER RECORD' : 'NEXT ACTION' ?></p><?php render_order_actions($order); ?></section></div>
            </article>
        <?php endforeach; ?>
        <?php if (!$visible): ?><div class="empty-state"><div class="empty-orb">⌕</div><h3>No orders match these filters.</h3><p>Try another customer, order number, or status.</p><a class="button button-ghost" href="<?= h(url('admin', ['tab' => 'orders'])) ?>">Show all orders</a></div><?php endif; ?>
    </div>
    <?php
}

function render_finance_page(array $user): void
{
    $period = is_string($_GET['finance_period'] ?? null) ? $_GET['finance_period'] : 'month';
    $date = is_string($_GET['finance_date'] ?? null) ? $_GET['finance_date'] : date('Y-m-d');
    try { $report = store()->financeReport($period, $date, $user); }
    catch (RuntimeException $error) {
        echo '<p class="finance-error" role="alert">' . h($error->getMessage()) . '</p>';
        $period = 'month'; $date = date('Y-m-d');
        $report = store()->financeReport($period, $date, $user);
    }
    $totals = $report['totals'];
    $series = ['sales' => 'Net sales', 'income' => 'External income', 'spending' => 'Item costs + expenses', 'profit' => 'Net profit'];
    $buckets = array_values($report['buckets']);
    foreach ($buckets as &$bucket) $bucket['spending'] = $bucket['cost'] + $bucket['expenses'];
    unset($bucket);
    $values = [0];
    foreach ($buckets as $bucket) foreach (array_keys($series) as $key) $values[] = $bucket[$key];
    $min = min($values); $max = max($values); $range = max(1, $max - $min);
    $y = static fn(float $value): float => round(200 - ($value - $min) / $range * 160, 2);
    $x = static fn(int $index): float => round(90 + $index * 680 / max(1, count($buckets) - 1), 2);
    ?>
    <div class="workspace-content-head"><div><p class="eyebrow">FINANCE DESK</p><h2>Financial history.</h2><p>Sales after discounts, excluding GST and delivery. Cancelled orders are excluded. Profit uses the item costs saved with each order.</p></div><span class="date-chip"><?= h($report['label']) ?></span></div>
    <form method="get" action="/admin" class="finance-filters admin-card">
        <input type="hidden" name="tab" value="finance">
        <label>View by<select name="finance_period"><?php foreach (['day' => 'Day', 'month' => 'Month', 'year' => 'Year'] as $value => $label): ?><option value="<?= $value ?>" <?= $period === $value ? 'selected' : '' ?>><?= $label ?></option><?php endforeach; ?></select></label>
        <label>Date to view<input type="date" name="finance_date" min="1900-01-01" max="9998-12-31" required value="<?= h($date) ?>"></label>
        <button class="button button-primary" type="submit">View financial details</button>
        <p>Choose any date in the month or year you want to view.</p>
    </form>
    <nav class="finance-navigation" aria-label="Financial period">
        <?php foreach (['previous' => '← Previous ', 'next' => 'Next '] as $key => $label): if ($report[$key] !== null): ?><a class="button button-ghost" href="<?= h(url('admin', ['tab' => 'finance', 'finance_period' => $period, 'finance_date' => $report[$key]])) ?>"><?= h($label . $period) ?></a><?php endif; endforeach; ?>
        <a href="<?= h(url('admin', ['tab' => 'finance'])) ?>">Current month</a>
    </nav>
    <div class="finance-summary finance-with-income">
        <?php foreach (['sales' => 'Net sales', 'income' => 'External income', 'cost' => 'Historical item costs', 'expenses' => 'Operating expenses', 'profit' => 'Net profit'] as $key => $label): ?><article class="<?= $key === 'profit' ? 'profit' : '' ?>"><small><?= h($label) ?></small><strong><?= money($totals[$key]) ?></strong></article><?php endforeach; ?>
    </div>
    <section class="admin-card finance-chart-card">
        <h3>Financial graph · <?= h($report['label']) ?></h3>
        <p><?= (int) $totals['orders'] ?> orders in this period. <?= $period === 'day' ? 'Orders appear by hour. Income and expenses have dates only, so they appear at 00:00.' : 'Select a row below to explore its financial details.' ?></p>
        <div class="finance-chart-scroll"><svg class="finance-chart" viewBox="0 0 800 250" role="img" aria-labelledby="finance-chart-title finance-chart-description">
            <title id="finance-chart-title">Financial graph for <?= h($report['label']) ?></title><desc id="finance-chart-description">Net sales, external income, item costs plus expenses, and net profit in INR. Exact values and period links are in the table below.</desc>
            <?php foreach ([$min, ($min + $max) / 2, $max] as $tick): ?><line x1="90" y1="<?= $y($tick) ?>" x2="770" y2="<?= $y($tick) ?>" class="finance-gridline"/><text x="82" y="<?= $y($tick) + 4 ?>" text-anchor="end"><?= h(number_format($tick, 0)) ?></text><?php endforeach; ?>
            <line x1="90" y1="<?= $y(0) ?>" x2="770" y2="<?= $y(0) ?>" class="finance-zero"/>
            <?php foreach ($series as $key => $label): $points = []; foreach ($buckets as $index => $bucket) $points[] = $x($index) . ',' . $y($bucket[$key]); ?><polyline points="<?= h(implode(' ', $points)) ?>" class="finance-line finance-<?= $key ?>"><title><?= h($label) ?></title></polyline><?php endforeach; ?>
            <?php foreach ($buckets as $index => $bucket): if ($index % (int) ceil(count($buckets) / 8) === 0 || $index === count($buckets) - 1): ?><text x="<?= $x($index) ?>" y="225" text-anchor="middle"><?= h($bucket['label']) ?></text><?php endif; endforeach; ?>
        </svg></div>
        <div class="finance-legend"><?php foreach ($series as $key => $label): ?><span><i class="finance-<?= $key ?>"></i><?= h($label) ?></span><?php endforeach; ?></div>
        <details><summary>View exact amounts and explore dates</summary><div class="table-scroll"><table class="admin-table"><caption>Financial breakdown in INR · <?= h($report['label']) ?></caption><thead><tr><th scope="col">Period</th><th scope="col">Orders</th><th scope="col">Net sales</th><th scope="col">External income</th><th scope="col">Item costs</th><th scope="col">Expenses</th><th scope="col">Net profit</th></tr></thead><tbody>
        <?php foreach ($buckets as $bucket): ?><tr><th scope="row"><?php if ($period !== 'day'): ?><a href="<?= h(url('admin', ['tab' => 'finance', 'finance_period' => $period === 'year' ? 'month' : 'day', 'finance_date' => $bucket['date']])) ?>"><?= h($bucket['label']) ?></a><?php else: ?><?= h($bucket['label']) ?><?php endif; ?></th><td><?= $bucket['orders'] ?></td><?php foreach (['sales', 'income', 'cost', 'expenses', 'profit'] as $key): ?><td><?= money($bucket[$key]) ?></td><?php endforeach; ?></tr><?php endforeach; ?>
        </tbody></table></div></details>
    </section>
    <section class="admin-card"><h3>Orders · <?= h($report['label']) ?></h3><div class="table-scroll"><table class="admin-table"><thead><tr><th>Order</th><th>Date</th><th>Customer</th><th>Status</th><th>Net sales</th></tr></thead><tbody><?php foreach ($report['orders'] as $order): ?><tr><td><a href="<?= h(url('invoice', ['id' => $order['id']])) ?>"><?= h($order['number']) ?></a></td><td><?= h(format_date($order['created_at'])) ?></td><td><?= h($order['customer_name']) ?></td><td><?= h(status_label($order['status'])) ?></td><td><?= money($order['subtotal'] - $order['discount']) ?></td></tr><?php endforeach; ?></tbody></table></div><?php if (!$report['orders']): ?><p>No orders in this period.</p><?php endif; ?></section>
    <div class="admin-main-grid lower-grid"><section class="admin-card"><h3>Record an expense</h3><form method="post" class="stacked-form"><?php admin_form_fields('add_expense', 'finance'); finance_return_fields($period, $date); ?><label>Date<input type="date" name="date" required value="<?= h($date) ?>"></label><label>Category<input name="category" required></label><label>Description<input name="description" required></label><label>Amount<input type="number" min="0.01" step="0.01" name="amount" required></label><button class="button button-primary" type="submit">Add expense</button></form></section><section class="admin-card"><h3>Expense ledger · <?= h($report['label']) ?></h3><div class="expense-list"><?php foreach ($report['expenses'] as $expense): ?><div><span class="expense-icon">−</span><span><strong><?= h($expense['category']) ?></strong><small><?= h($expense['description']) ?> · <?= h(format_date($expense['date'], 'd M Y')) ?></small></span><em>−<?= money($expense['amount']) ?></em></div><?php endforeach; ?><?php if (!$report['expenses']): ?><p>No expenses in this period.</p><?php endif; ?></div></section></div>
    <?php render_external_income($report['incomes'], $period, $date);
}

function finance_return_fields(string $period, string $date): void
{
    echo '<input type="hidden" name="finance_period" value="' . h($period) . '"><input type="hidden" name="finance_date" value="' . h($date) . '">';
}
