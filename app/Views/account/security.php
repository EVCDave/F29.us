<?php
$labelAction = static function (string $action): string {
    return match ($action) {
        'password_changed'         => 'Password changed',
        'password_reset_requested' => 'Password reset requested',
        'password_reset_completed' => 'Password reset completed',
        'email_change_requested'   => 'Email change requested',
        'email_change_completed'   => 'Email change completed',
        'email_verified'           => 'Email address verified',
        default                    => ucwords(str_replace('_', ' ', $action)),
    };
};
?>
<h1>Account Security</h1>

<?php if ($flash): ?>
<div class="flash flash-<?= View::e($flash['type']) ?>"><?= View::e($flash['text']) ?></div>
<?php endif; ?>

<!-- ── Security overview ──────────────────────────────────────────────────── -->
<div class="card mw-560">
    <h2 class="mb-3">Security Overview</h2>
    <table class="info-table info-table-w160 mb-0">
        <tbody>
            <tr>
                <td>Account email</td>
                <td class="fw-medium"><?= View::e($user['email']) ?></td>
            </tr>
            <tr>
                <td>Email verified</td>
                <td>
                    <?php if (!empty($user['email_verified_at'])): ?>
                        <span class="text-success fw-medium">Yes</span>
                        <span class="text-faint text-sm ml-2"><?= View::e(substr($user['email_verified_at'], 0, 16)) ?> UTC</span>
                    <?php elseif ((int)($user['email_verification_required'] ?? 0) === 1): ?>
                        <span class="text-danger">No — <a href="/account/verify-email" class="text-danger">verify now</a></span>
                    <?php else: ?>
                        <span class="text-muted">No</span>
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <td>Password last changed</td>
                <td>
                    <?php if (!empty($user['password_changed_at'])): ?>
                        <?= View::e(substr($user['password_changed_at'], 0, 16)) ?> UTC
                    <?php else: ?>
                        <span class="text-faint">Not recorded</span>
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <td>Last login</td>
                <td>
                    <?php if (!empty($user['last_login_at'])): ?>
                        <?= View::e(substr($user['last_login_at'], 0, 16)) ?> UTC
                    <?php else: ?>
                        <span class="text-faint">Not recorded</span>
                    <?php endif; ?>
                </td>
            </tr>
        </tbody>
    </table>
</div>

<!-- ── Current session ────────────────────────────────────────────────────── -->
<div class="card mw-560">
    <h2 class="mb-3">Current Session</h2>
    <table class="info-table info-table-w160 mb-0">
        <tbody>
            <tr>
                <td>Session started</td>
                <td>
                    <?php if ($sessionStartedAt): ?>
                        <?= View::e(substr($sessionStartedAt, 0, 16)) ?> UTC
                    <?php else: ?>
                        <span class="text-faint">Not recorded</span>
                    <?php endif; ?>
                </td>
            </tr>
        </tbody>
    </table>
    <p class="text-82 text-muted mt-3 mb-0">
        To end this session, <a href="#" data-submit-form="logout-form">sign out</a>.
    </p>
    <form id="logout-form" method="post" action="/logout" class="d-none">
        <?= CsrfService::field() ?>
    </form>
</div>

<!-- ── Recent security events ────────────────────────────────────────────── -->
<div class="card mw-700">
    <h2 class="mb-3">Recent Security Events</h2>
    <?php if (empty($securityEvents)): ?>
        <p class="text-faint text-base mb-0">No security events recorded yet.</p>
    <?php else: ?>
    <table class="text-88 mb-0">
        <thead>
            <tr>
                <th class="col-170">When (UTC)</th>
                <th>Event</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($securityEvents as $event): ?>
            <tr>
                <td class="text-muted"><?= View::e(substr($event['created_at'], 0, 16)) ?></td>
                <td><?= View::e($labelAction($event['action'])) ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>

<!-- ── Recent login attempts ─────────────────────────────────────────────── -->
<div class="card mw-700">
    <h2 class="mb-3">Recent Login Attempts</h2>
    <?php if (empty($loginAttempts)): ?>
        <p class="text-faint text-base mb-0">No login attempts recorded.</p>
    <?php else: ?>
    <table class="text-88 mb-0">
        <thead>
            <tr>
                <th class="col-170">When (UTC)</th>
                <th>Result</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($loginAttempts as $attempt): ?>
            <tr>
                <td class="text-muted"><?= View::e(substr($attempt['attempted_at'], 0, 16)) ?></td>
                <td>
                    <?php if ((int) $attempt['success_flag'] === 1): ?>
                        <span class="text-success">Successful</span>
                    <?php else: ?>
                        <span class="text-danger">Failed</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>

<p class="text-sm text-muted">
    To update your password, visit <a href="/account/settings">Account Settings</a>.
</p>
