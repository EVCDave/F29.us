<h1>Account Settings</h1>
<p class="text-base text-muted-3 mt-neg-2 mb-5">
    <a href="/account/security">View account security &rarr;</a>
</p>

<?php if ($flash): ?>
<div class="flash flash-<?= View::e($flash['type']) ?>"><?= View::e($flash['text']) ?></div>
<?php endif; ?>

<!-- ── Account info ────────────────────────────────────────────────────────── -->
<div class="card mw-520">
    <h2 class="mb-3">Account Info</h2>
    <table class="info-table mb-0">
        <tbody>
            <tr>
                <td>Email</td>
                <td class="fw-medium"><?= View::e($user['email']) ?></td>
            </tr>
            <?php $fullName = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')); ?>
            <?php if ($fullName !== ''): ?>
            <tr>
                <td>Name</td>
                <td><?= View::e($fullName) ?></td>
            </tr>
            <?php endif; ?>
            <?php if (!empty($user['display_name'])): ?>
            <tr>
                <td>Display name</td>
                <td><?= View::e($user['display_name']) ?></td>
            </tr>
            <?php endif; ?>
            <?php if (!empty($user['company_name'])): ?>
            <tr>
                <td>Company</td>
                <td><?= View::e($user['company_name']) ?></td>
            </tr>
            <?php endif; ?>
            <tr>
                <td>Role</td>
                <td><?= View::e(ucfirst($user['role'] ?? 'user')) ?></td>
            </tr>
            <tr>
                <td>Member since</td>
                <td><?= View::e(substr($user['created_at'] ?? '', 0, 10)) ?></td>
            </tr>
            <?php if ($user['last_login_at']): ?>
            <tr>
                <td>Last login</td>
                <td><?= View::e(substr($user['last_login_at'], 0, 16)) ?> UTC</td>
            </tr>
            <?php endif; ?>
            <tr>
                <td>Email verified</td>
                <td>
                    <?php if (!empty($user['email_verified_at'])): ?>
                        <span class="text-success fw-medium">Verified</span>
                    <?php else: ?>
                        <span class="text-danger">Not verified</span>
                        &mdash;
                        <a href="/account/verify-email" class="text-88">Resend verification email</a>
                    <?php endif; ?>
                </td>
            </tr>
        </tbody>
    </table>
</div>

<!-- ── Profile ────────────────────────────────────────────────────────────── -->
<?php
$pv = $flash['profile'] ?? null;
$fv = static fn(string $key): string => View::e($pv ? ($pv[$key] ?? '') : ($user[$key] ?? ''));
?>
<div class="card mw-520">
    <h2 class="mb-0">Profile</h2>
    <p class="text-sm text-muted mb-4">All fields are optional.</p>

    <form method="post" action="/account/settings/profile">
        <?= CsrfService::field() ?>

        <div class="form-grid-2">
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
                <small class="text-muted fw-normal"> — shown in the app; falls back to first + last or email</small>
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

        <div class="form-grid-2">
            <div class="form-group">
                <label for="phone">Phone</label>
                <input type="tel" id="phone" name="phone"
                       value="<?= $fv('phone') ?>"
                       maxlength="50" autocomplete="tel">
            </div>
            <div class="form-group">
                <label for="timezone">Timezone
                    <small class="text-muted fw-normal"> — e.g. America/Chicago</small>
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
<div class="card mw-520">
    <h2 class="mb-0">Update Email Address</h2>
    <p class="text-sm text-muted mb-4">Enter your current password to confirm. A verification link will be sent to the new address — your email will not change until you click it.</p>

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
<div class="card card-last mw-520">
    <h2 class="mb-0">Change Password</h2>
    <p class="text-sm text-muted mb-4">Minimum 8 characters. Must differ from your current password.</p>

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
