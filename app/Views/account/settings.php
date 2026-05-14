<h1>Account Settings</h1>

<?php if ($flash): ?>
<div style="
    background: <?= $flash['type'] === 'success' ? '#f0fdf4' : ($flash['type'] === 'error' ? '#fef2f2' : '#eff6ff') ?>;
    border: 1px solid <?= $flash['type'] === 'success' ? '#86efac' : ($flash['type'] === 'error' ? '#fca5a5' : '#93c5fd') ?>;
    color: <?= $flash['type'] === 'success' ? '#166534' : ($flash['type'] === 'error' ? '#991b1b' : '#1e40af') ?>;
    border-radius: 4px;
    padding: 0.7rem 1rem;
    margin-bottom: 1.5rem;
    font-size: 0.9rem;
"><?= View::e($flash['text']) ?></div>
<?php endif; ?>

<!-- ── Account info ────────────────────────────────────────────────────────── -->
<div style="background:#fff;border:1px solid #e5e7eb;border-radius:6px;padding:1.25rem 1.5rem;margin-bottom:2rem;max-width:520px">
    <h2 style="margin-bottom:0.75rem">Account Info</h2>
    <table style="margin:0;font-size:0.9rem">
        <tbody>
            <tr>
                <td style="color:#6b7280;padding:0.3rem 1rem 0.3rem 0;border:none;width:130px">Email</td>
                <td style="border:none;font-weight:500"><?= View::e($user['email']) ?></td>
            </tr>
            <?php $fullName = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')); ?>
            <?php if ($fullName !== ''): ?>
            <tr>
                <td style="color:#6b7280;padding:0.3rem 1rem 0.3rem 0;border:none">Name</td>
                <td style="border:none"><?= View::e($fullName) ?></td>
            </tr>
            <?php endif; ?>
            <?php if (!empty($user['display_name'])): ?>
            <tr>
                <td style="color:#6b7280;padding:0.3rem 1rem 0.3rem 0;border:none">Display name</td>
                <td style="border:none"><?= View::e($user['display_name']) ?></td>
            </tr>
            <?php endif; ?>
            <?php if (!empty($user['company_name'])): ?>
            <tr>
                <td style="color:#6b7280;padding:0.3rem 1rem 0.3rem 0;border:none">Company</td>
                <td style="border:none"><?= View::e($user['company_name']) ?></td>
            </tr>
            <?php endif; ?>
            <tr>
                <td style="color:#6b7280;padding:0.3rem 1rem 0.3rem 0;border:none">Role</td>
                <td style="border:none"><?= View::e(ucfirst($user['role'] ?? 'user')) ?></td>
            </tr>
            <tr>
                <td style="color:#6b7280;padding:0.3rem 1rem 0.3rem 0;border:none">Member since</td>
                <td style="border:none"><?= View::e(substr($user['created_at'] ?? '', 0, 10)) ?></td>
            </tr>
            <?php if ($user['last_login_at']): ?>
            <tr>
                <td style="color:#6b7280;padding:0.3rem 1rem 0.3rem 0;border:none">Last login</td>
                <td style="border:none"><?= View::e(substr($user['last_login_at'], 0, 16)) ?> UTC</td>
            </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- ── Profile ────────────────────────────────────────────────────────────── -->
<?php
$pv = $flash['profile'] ?? null;
$fv = static fn(string $key): string => View::e($pv ? ($pv[$key] ?? '') : ($user[$key] ?? ''));
?>
<div style="background:#fff;border:1px solid #e5e7eb;border-radius:6px;padding:1.25rem 1.5rem;margin-bottom:2rem;max-width:520px">
    <h2 style="margin-bottom:0.1rem">Profile</h2>
    <p style="font-size:0.85rem;color:#6b7280;margin-bottom:1rem">All fields are optional.</p>

    <form method="post" action="/account/settings/profile">
        <?= CsrfService::field() ?>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:0 1rem">
            <div class="form-group">
                <label for="first_name">First name</label>
                <input type="text" id="first_name" name="first_name"
                       value="<?= $fv('first_name') ?>"
                       maxlength="100" autocomplete="given-name">
            </div>
            <div class="form-group">
                <label for="last_name">Last name</label>
                <input type="text" id="last_name" name="last_name"
                       value="<?= $fv('last_name') ?>"
                       maxlength="100" autocomplete="family-name">
            </div>
        </div>

        <div class="form-group">
            <label for="display_name">Display name
                <small style="color:#6b7280;font-weight:400"> — shown in the app; falls back to first + last or email</small>
            </label>
            <input type="text" id="display_name" name="display_name"
                   value="<?= $fv('display_name') ?>"
                   maxlength="150" autocomplete="nickname">
        </div>

        <div class="form-group">
            <label for="company_name">Company</label>
            <input type="text" id="company_name" name="company_name"
                   value="<?= $fv('company_name') ?>"
                   maxlength="150" autocomplete="organization">
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:0 1rem">
            <div class="form-group">
                <label for="phone">Phone</label>
                <input type="tel" id="phone" name="phone"
                       value="<?= $fv('phone') ?>"
                       maxlength="50" autocomplete="tel">
            </div>
            <div class="form-group">
                <label for="timezone">Timezone
                    <small style="color:#6b7280;font-weight:400"> — e.g. America/Chicago</small>
                </label>
                <input type="text" id="timezone" name="timezone"
                       value="<?= $fv('timezone') ?>"
                       maxlength="100">
            </div>
        </div>

        <button type="submit" class="btn">Update Profile</button>
    </form>
</div>

<!-- ── Update email ───────────────────────────────────────────────────────── -->
<div style="background:#fff;border:1px solid #e5e7eb;border-radius:6px;padding:1.25rem 1.5rem;margin-bottom:2rem;max-width:520px">
    <h2 style="margin-bottom:0.1rem">Update Email Address</h2>
    <p style="font-size:0.85rem;color:#6b7280;margin-bottom:1rem">Enter your current password to confirm the change.</p>

    <form method="post" action="/account/settings/email">
        <?= CsrfService::field() ?>

        <div class="form-group">
            <label for="new_email">New email address</label>
            <input type="email" id="new_email" name="new_email"
                   value="<?= View::e($flash['email'] ?? '') ?>"
                   autocomplete="email" required>
        </div>

        <div class="form-group">
            <label for="email_current_password">Current password</label>
            <input type="password" id="email_current_password" name="current_password"
                   autocomplete="current-password" required>
        </div>

        <button type="submit" class="btn">Update Email</button>
    </form>
</div>

<!-- ── Change password ────────────────────────────────────────────────────── -->
<div style="background:#fff;border:1px solid #e5e7eb;border-radius:6px;padding:1.25rem 1.5rem;max-width:520px">
    <h2 style="margin-bottom:0.1rem">Change Password</h2>
    <p style="font-size:0.85rem;color:#6b7280;margin-bottom:1rem">Minimum 8 characters. Must differ from your current password.</p>

    <form method="post" action="/account/settings/password">
        <?= CsrfService::field() ?>

        <div class="form-group">
            <label for="current_password">Current password</label>
            <input type="password" id="current_password" name="current_password"
                   autocomplete="current-password" required>
        </div>

        <div class="form-group">
            <label for="new_password">New password</label>
            <input type="password" id="new_password" name="new_password"
                   autocomplete="new-password" required minlength="8">
        </div>

        <div class="form-group">
            <label for="confirm_password">Confirm new password</label>
            <input type="password" id="confirm_password" name="confirm_password"
                   autocomplete="new-password" required minlength="8">
        </div>

        <button type="submit" class="btn">Change Password</button>
    </form>
</div>
