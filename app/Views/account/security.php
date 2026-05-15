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

<!-- ── Security overview ──────────────────────────────────────────────────── -->
<div style="background:#fff;border:1px solid #e5e7eb;border-radius:6px;padding:1.25rem 1.5rem;margin-bottom:2rem;max-width:560px">
    <h2 style="margin-bottom:0.75rem">Security Overview</h2>
    <table style="margin:0;font-size:0.9rem">
        <tbody>
            <tr>
                <td style="color:#6b7280;padding:0.3rem 1rem 0.3rem 0;border:none;width:160px">Account email</td>
                <td style="border:none;font-weight:500"><?= View::e($user['email']) ?></td>
            </tr>
            <tr>
                <td style="color:#6b7280;padding:0.3rem 1rem 0.3rem 0;border:none">Email verified</td>
                <td style="border:none">
                    <?php if (!empty($user['email_verified_at'])): ?>
                        <span style="color:#166534;font-weight:500">Yes</span>
                        <span style="color:#9ca3af;font-size:0.85rem;margin-left:0.4rem"><?= View::e(substr($user['email_verified_at'], 0, 16)) ?> UTC</span>
                    <?php elseif ((int)($user['email_verification_required'] ?? 0) === 1): ?>
                        <span style="color:#991b1b">No — <a href="/account/verify-email" style="color:#991b1b">verify now</a></span>
                    <?php else: ?>
                        <span style="color:#6b7280">No</span>
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <td style="color:#6b7280;padding:0.3rem 1rem 0.3rem 0;border:none">Password last changed</td>
                <td style="border:none">
                    <?php if (!empty($user['password_changed_at'])): ?>
                        <?= View::e(substr($user['password_changed_at'], 0, 16)) ?> UTC
                    <?php else: ?>
                        <span style="color:#9ca3af">Not recorded</span>
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <td style="color:#6b7280;padding:0.3rem 1rem 0.3rem 0;border:none">Last login</td>
                <td style="border:none">
                    <?php if (!empty($user['last_login_at'])): ?>
                        <?= View::e(substr($user['last_login_at'], 0, 16)) ?> UTC
                    <?php else: ?>
                        <span style="color:#9ca3af">Not recorded</span>
                    <?php endif; ?>
                </td>
            </tr>
        </tbody>
    </table>
</div>

<!-- ── Current session ────────────────────────────────────────────────────── -->
<div style="background:#fff;border:1px solid #e5e7eb;border-radius:6px;padding:1.25rem 1.5rem;margin-bottom:2rem;max-width:560px">
    <h2 style="margin-bottom:0.75rem">Current Session</h2>
    <table style="margin:0;font-size:0.9rem">
        <tbody>
            <tr>
                <td style="color:#6b7280;padding:0.3rem 1rem 0.3rem 0;border:none;width:160px">Session started</td>
                <td style="border:none">
                    <?php if ($sessionStartedAt): ?>
                        <?= View::e(substr($sessionStartedAt, 0, 16)) ?> UTC
                    <?php else: ?>
                        <span style="color:#9ca3af">Not recorded</span>
                    <?php endif; ?>
                </td>
            </tr>
        </tbody>
    </table>
    <p style="font-size:0.82rem;color:#6b7280;margin-top:0.75rem;margin-bottom:0">
        To end this session, <a href="#" onclick="document.getElementById('logout-form').submit();return false">sign out</a>.
    </p>
    <form id="logout-form" method="post" action="/logout" style="display:none">
        <?= CsrfService::field() ?>
    </form>
</div>

<!-- ── Recent security events ────────────────────────────────────────────── -->
<div style="background:#fff;border:1px solid #e5e7eb;border-radius:6px;padding:1.25rem 1.5rem;margin-bottom:2rem;max-width:700px">
    <h2 style="margin-bottom:0.75rem">Recent Security Events</h2>
    <?php if (empty($securityEvents)): ?>
        <p style="color:#9ca3af;font-size:0.9rem;margin:0">No security events recorded yet.</p>
    <?php else: ?>
    <table style="font-size:0.875rem;width:100%">
        <thead>
            <tr>
                <th style="width:170px">When (UTC)</th>
                <th>Event</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($securityEvents as $event): ?>
            <tr>
                <td style="color:#6b7280"><?= View::e(substr($event['created_at'], 0, 16)) ?></td>
                <td><?= View::e($labelAction($event['action'])) ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>

<!-- ── Recent login attempts ─────────────────────────────────────────────── -->
<div style="background:#fff;border:1px solid #e5e7eb;border-radius:6px;padding:1.25rem 1.5rem;margin-bottom:2rem;max-width:700px">
    <h2 style="margin-bottom:0.75rem">Recent Login Attempts</h2>
    <?php if (empty($loginAttempts)): ?>
        <p style="color:#9ca3af;font-size:0.9rem;margin:0">No login attempts recorded.</p>
    <?php else: ?>
    <table style="font-size:0.875rem;width:100%">
        <thead>
            <tr>
                <th style="width:170px">When (UTC)</th>
                <th>Result</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($loginAttempts as $attempt): ?>
            <tr>
                <td style="color:#6b7280"><?= View::e(substr($attempt['attempted_at'], 0, 16)) ?></td>
                <td>
                    <?php if ((int) $attempt['success_flag'] === 1): ?>
                        <span style="color:#166534">Successful</span>
                    <?php else: ?>
                        <span style="color:#991b1b">Failed</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>

<p style="font-size:0.85rem;color:#6b7280">
    To update your password, visit <a href="/account/settings">Account Settings</a>.
</p>
