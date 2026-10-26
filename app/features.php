<?php
declare(strict_types=1);

function avatar_content(array $user): string
{
    $image = $user['profile_image'] ?? null;
    if (is_string($image) && ImageUploads::isValidName($image)) {
        return '<img src="' . h(url('media', ['image' => $image])) . '" alt="' . h($user['name'] . ' profile photo') . '">';
    }
    return h(mb_strtoupper(mb_substr($user['name'], 0, 1)));
}

function product_visual(array $product): string
{
    $image = $product['image_path'] ?? null;
    if (is_string($image) && ImageUploads::isValidName($image)) {
        return '<img class="product-photo" src="' . h(url('media', ['image' => $image])) . '" alt="' . h($product['name']) . '" loading="lazy">';
    }
    return h($product['visual']);
}

function render_photo_input(string $field, string $label, bool $hasPhoto): void
{
    ?>
    <div class="photo-controls">
        <label><?= h($label) ?><input type="file" name="<?= h($field) ?>" accept="image/jpeg,image/png,image/webp" data-photo-input></label>
        <p class="quiet-copy">JPG, PNG, or WebP up to 2 MB and 4096 × 4096 pixels. Leave empty to keep the current photo or default.</p>
        <img class="photo-preview" data-photo-preview alt="Selected photo preview" hidden>
        <?php if ($hasPhoto): ?>
            <label class="checkbox-label"><input type="checkbox" name="default_photo" value="1"><span>Use the default instead of the current photo</span></label>
        <?php endif; ?>
    </div>
    <?php
}

function render_profile_page(array $user): void
{
    ?>
    <section class="page-intro compact-intro"><p class="eyebrow">YOUR PROFILE</p><h1>Make it yours.</h1><p>Your photo appears in your account and workspace.</p></section>
    <section class="content-section profile-settings">
        <div class="profile-card"><span class="avatar avatar-large"><?= avatar_content($user) ?></span><div><strong><?= h($user['name']) ?></strong><p><?= h($user['email']) ?><br><?= h($user['phone']) ?></p></div><span class="profile-safe"><?= h(ucfirst($user['role'])) ?></span></div>
        <?php if ($user['role'] === 'customer'): ?>
            <form method="post" class="stacked-form contact-settings-form">
                <?= csrf_field() ?><input type="hidden" name="action" value="update_customer_profile"><input type="hidden" name="return_page" value="profile">
                <h2>Contact details</h2>
                <?php render_contact_fields($user); ?>
                <button class="button button-primary" type="submit">Save contact details</button>
            </form>
        <?php elseif ($user['role'] === 'delivery'): ?>
            <p class="profile-contact-note">To change your name, email, phone number, or account password, contact the store administrator. You can update your photo below.</p>
        <?php endif; ?>
        <form method="post" enctype="multipart/form-data" class="stacked-form photo-settings-form">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="update_profile_photo">
            <input type="hidden" name="return_page" value="profile">
            <?php render_photo_input('profile_photo', 'Profile photo', !empty($user['profile_image'])); ?>
            <button class="button button-primary" type="submit">Save profile photo</button>
        </form>
        <a class="text-link" href="<?= h(url($user['role'] === 'admin' ? 'admin' : ($user['role'] === 'delivery' ? 'delivery' : 'account'))) ?>">Back to your workspace →</a>
    </section>
    <?php
}

function render_contact_fields(array $user): void
{
    ?>
    <label>Full name<input name="name" required minlength="2" maxlength="120" autocomplete="name" value="<?= h($user['name'] ?? '') ?>"></label>
    <label>Email address<input type="email" name="email" required maxlength="190" autocomplete="email" value="<?= h($user['email'] ?? '') ?>"></label>
    <label>Phone number<input type="tel" name="phone" required minlength="7" maxlength="30" autocomplete="tel" value="<?= h($user['phone'] ?? '') ?>"></label>
    <?php
}

function render_delivery_partners_admin(array $partners): void
{
    $editing = isset($_GET['edit_partner']) ? store()->user((int) $_GET['edit_partner']) : null;
    $editing = $editing && $editing['role'] === 'delivery' ? $editing : null;
    ?>
    <div class="workspace-content-head"><div><p class="eyebrow">DELIVERY TEAM</p><h2><?= $editing ? 'Edit partner details.' : 'Create a partner account.' ?></h2><p>Only administrators can create delivery accounts or change partner contact details.</p></div><a class="button button-ghost" href="<?= h(url('admin', ['tab' => 'partners'])) ?>">+ New partner</a></div>
    <div class="admin-main-grid lower-grid">
        <section class="admin-card">
            <form method="post" class="stacked-form partner-editor-form">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="<?= $editing ? 'update_delivery_partner' : 'create_delivery_partner' ?>">
                <input type="hidden" name="partner_id" value="<?= (int) ($editing['id'] ?? 0) ?>">
                <input type="hidden" name="return_page" value="admin"><input type="hidden" name="return_tab" value="partners">
                <?php render_contact_fields($editing ?? []); ?>
                <label><?= $editing ? 'New password (optional)' : 'Initial password' ?><input type="password" name="password" <?= $editing ? '' : 'required' ?> minlength="8" maxlength="72" autocomplete="new-password"></label>
                <?php if ($editing): ?><p class="quiet-copy">Leave the password empty to keep the current password.</p><?php endif; ?>
                <button class="button button-primary" type="submit"><?= $editing ? 'Save partner details' : 'Create delivery partner' ?></button>
            </form>
        </section>
        <section class="admin-card"><p class="eyebrow">ACTIVE PARTNERS</p><h3>Your delivery team.</h3>
            <div class="partner-list">
                <?php foreach ($partners as $partner): ?>
                    <div class="profile-card"><span class="avatar"><?= avatar_content($partner) ?></span><div><strong><?= h($partner['name']) ?></strong><p><?= h($partner['email']) ?><br><?= h($partner['phone']) ?></p><a class="text-link" href="<?= h(url('admin', ['tab' => 'partners', 'edit_partner' => $partner['id']])) ?>">Edit details →</a></div></div>
                <?php endforeach; ?>
                <?php if (!$partners): ?><p class="quiet-copy">No delivery partners yet.</p><?php endif; ?>
            </div>
        </section>
    </div>
    <?php
}

function render_external_income(): void
{
    ?>
    <div class="admin-main-grid lower-grid">
        <section class="admin-card">
            <p class="eyebrow">EXTERNAL INCOME</p><h3>Add profit from other sources.</h3>
            <form method="post" class="stacked-form">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="add_income">
                <input type="hidden" name="return_page" value="admin">
                <input type="hidden" name="return_tab" value="finance">
                <label>Income date<input type="date" name="date" required value="<?= date('Y-m-d') ?>"></label>
                <label>Income category<input name="category" required placeholder="e.g. Sponsorship"></label>
                <label>Income description<input name="description" required placeholder="Where did this income come from?"></label>
                <label>Income amount<input type="number" name="amount" min="0.01" step="0.01" required placeholder="0.00"></label>
                <button class="button button-primary" type="submit">Add external income</button>
            </form>
        </section>
        <section class="admin-card"><p class="eyebrow">OTHER EARNINGS</p><h3>External income ledger.</h3>
            <div class="expense-list">
                <?php foreach (store()->incomes() as $income): ?>
                    <div><span class="expense-icon">+</span><span><strong><?= h($income['category']) ?></strong><small><?= h($income['description']) ?> · <?= h(format_date($income['date'], 'd M Y')) ?></small></span><em class="income-amount">+<?= money((float) $income['amount']) ?></em></div>
                <?php endforeach; ?>
                <?php if (!store()->incomes()): ?><p class="quiet-copy">No external income recorded yet.</p><?php endif; ?>
            </div>
        </section>
    </div>
    <?php
}


function brand_markup(): string
{
    $settings = shop_settings();
    if (!empty($settings['shop_logo']) && ImageUploads::isValidName($settings['shop_logo'])) {
        $mark = '<span class="brand-mark brand-mark-custom"><img src="' . h(url('media', ['image' => $settings['shop_logo']])) . '" alt=""></span>';
    } else {
        $initial = $settings['shop_name'] === SG_NAME ? 'G' : mb_strtoupper(mb_substr($settings['shop_name'], 0, 1));
        $mark = '<span class="brand-mark"><span>' . h($initial) . '</span></span>';
    }
    return $mark . '<span class="brand-text" title="' . h($settings['shop_name']) . '">' . h($settings['shop_name']) . '</span>';
}

function cart_icon(): string
{
    return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M3 3h2l2.4 11.4a2 2 0 0 0 2 1.6h8.9a2 2 0 0 0 2-1.6L22 7H6"/><circle cx="10" cy="21" r="1"/><circle cx="19" cy="21" r="1"/></svg>';
}

function render_branding_settings(): void
{
    $settings = shop_settings();
    ?>
    <div class="workspace-content-head"><div><p class="eyebrow">STORE SETTINGS</p><h2>Your shop, your identity.</h2><p>Update the name, logo, and colours used across your website and receipts.</p></div></div>
    <div class="admin-main-grid lower-grid">
        <section class="admin-card">
            <form method="post" enctype="multipart/form-data" class="stacked-form branding-form" data-branding-form>
                <?= csrf_field() ?><input type="hidden" name="action" value="save_branding"><input type="hidden" name="return_page" value="admin"><input type="hidden" name="return_tab" value="settings">
                <label>Shop name<input name="shop_name" required minlength="2" maxlength="60" value="<?= h($settings['shop_name']) ?>" data-brand-name></label>
                <div class="color-settings">
                    <label>Accent colour<input type="color" name="accent_color" value="<?= h($settings['accent_color']) ?>" data-brand-color="--lime"></label>
                    <label>Page background<input type="color" name="background_color" value="<?= h($settings['background_color']) ?>" data-brand-color="--paper"></label>
                    <label>Text and dark panels<input type="color" name="text_color" value="<?= h($settings['text_color']) ?>" data-brand-color="--ink"></label>
                </div>
                <p class="quiet-copy">Use a light page background and dark text. Button text adjusts to your accent colour.</p>
                <?php render_photo_input('shop_logo_file', 'Shop logo', !empty($settings['shop_logo'])); ?>
                <button class="button button-primary" type="submit">Save store settings</button>
            </form>
            <form method="post" class="branding-reset-form"><?= csrf_field() ?><input type="hidden" name="action" value="reset_branding"><input type="hidden" name="return_page" value="admin"><input type="hidden" name="return_tab" value="settings"><button class="text-button" type="submit">Restore default name, logo, and colours</button></form>
        </section>
        <section class="admin-card">
            <p class="eyebrow">PREVIEW</p><h3>Try your colours.</h3>
            <div class="branding-preview" data-brand-preview><div class="brand"><?= brand_markup() ?></div><h2 data-brand-name-preview><?= h($settings['shop_name']) ?></h2><p>Equipment for your next game.</p><span class="branding-preview-button">Shop the collection →</span></div>
        </section>
    </div>
    <?php
}

function render_order_actions(array $order): void
{
    ?>
    <div class="row-actions"><a class="table-action" href="<?= h(url('invoice', ['id' => $order['id'], 'track' => 1])) ?>">Track</a>
    <?php if (!in_array($order['status'], ['delivered', 'cancelled'], true)): $canPack = in_array($order['status'], ['placed', 'confirmed'], true); ?>
        <form method="post" class="order-action-form" data-order-action-form>
            <?= csrf_field() ?><input type="hidden" name="action" value="update_order_status"><input type="hidden" name="return_page" value="admin"><input type="hidden" name="return_tab" value="orders"><input type="hidden" name="order_id" value="<?= (int) $order['id'] ?>">
            <select name="status" aria-label="Order action" data-order-status><?php if ($canPack): ?><option value="packed">Pack order</option><?php endif; ?><option value="cancelled">Cancel order</option></select>
            <label data-cancel-reason>Reason for cancellation<textarea name="note" maxlength="500" rows="3" <?= $canPack ? '' : 'required' ?> placeholder="Explain why this order is being cancelled" aria-label="Reason for cancellation"></textarea></label>
            <button class="table-action" type="submit">Update order</button>
        </form>
    <?php elseif ($order['status'] === 'cancelled' && !empty($order['cancellation_reason'])): ?><p class="cancellation-reason"><?= h($order['cancellation_reason']) ?></p><?php endif; ?>
    </div>
    <?php
}
