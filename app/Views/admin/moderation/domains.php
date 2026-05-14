<div style="display:flex;align-items:baseline;justify-content:space-between;flex-wrap:wrap;gap:0.5rem;margin-bottom:1rem">
    <h1>Blocked Domains</h1>
    <a href="/admin/moderation/links" style="color:#666;font-size:0.9rem">&larr; Moderated Links</a>
</div>

<?php if ($flash): ?>
<div style="
    background: <?= $flash['type'] === 'success' ? '#f0fdf4' : ($flash['type'] === 'error' ? '#fef2f2' : '#eff6ff') ?>;
    border: 1px solid <?= $flash['type'] === 'success' ? '#86efac' : ($flash['type'] === 'error' ? '#fca5a5' : '#93c5fd') ?>;
    color: <?= $flash['type'] === 'success' ? '#166534' : ($flash['type'] === 'error' ? '#991b1b' : '#1e40af') ?>;
    border-radius: 4px; padding: 0.7rem 1rem; margin-bottom: 1.25rem; font-size: 0.9rem;
"><?= View::e($flash['text']) ?></div>
<?php endif; ?>

<!-- ── Add domain form ─────────────────────────────────────────────────────── -->
<div style="background:#fff;border:1px solid #e5e7eb;border-radius:6px;padding:1.25rem 1.5rem;max-width:520px;margin-bottom:2rem">
    <h2 style="margin-bottom:0.5rem">Block a Domain</h2>
    <p style="font-size:0.85rem;color:#6b7280;margin-bottom:1rem">
        Blocking a domain prevents users from creating or editing QR destinations to that domain
        (and all its subdomains). Enter a bare domain — e.g. <code>example.com</code>.
    </p>
    <form method="post" action="/admin/moderation/domains">
        <?= CsrfService::field() ?>
        <div style="display:flex;gap:0.75rem;flex-wrap:wrap;align-items:flex-end">
            <div class="form-group" style="margin-bottom:0;flex:1;min-width:160px">
                <label for="domain">Domain</label>
                <input type="text" id="domain" name="domain"
                       placeholder="example.com"
                       style="max-width:100%" required>
            </div>
            <div class="form-group" style="margin-bottom:0;flex:1;min-width:160px">
                <label for="reason">Reason <span style="color:#9ca3af;font-weight:400">(optional)</span></label>
                <input type="text" id="reason" name="reason"
                       placeholder="e.g. Phishing"
                       maxlength="255"
                       style="max-width:100%">
            </div>
            <div style="padding-bottom:0">
                <button type="submit" class="btn">Block Domain</button>
            </div>
        </div>
    </form>
</div>

<!-- ── Domain table ───────────────────────────────────────────────────────── -->
<h2 style="margin-bottom:0.75rem">Blocklist (<?= count($domains) ?>)</h2>
<?php if (empty($domains)): ?>
<p style="color:#888">No domains are currently blocked.</p>
<?php else: ?>
<table style="font-size:0.88rem">
    <thead>
        <tr>
            <th>Domain</th>
            <th>Reason</th>
            <th style="width:80px;text-align:center">Active</th>
            <th style="width:130px">Added</th>
            <th style="width:130px">Added by</th>
            <th style="width:80px"></th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($domains as $d): ?>
    <tr style="<?= $d['is_active'] ? '' : 'opacity:0.55' ?>">
        <td><code><?= View::e($d['domain']) ?></code></td>
        <td style="color:#6b7280"><?= View::e($d['reason'] ?? '—') ?></td>
        <td style="text-align:center">
            <?php if ($d['is_active']): ?>
            <span style="color:#166534;font-weight:600">Yes</span>
            <?php else: ?>
            <span style="color:#9ca3af">No</span>
            <?php endif; ?>
        </td>
        <td style="color:#6b7280;font-size:0.82rem"><?= View::e(substr($d['created_at'], 0, 10)) ?></td>
        <td style="color:#6b7280;font-size:0.82rem"><?= View::e($d['created_by_email'] ?? '—') ?></td>
        <td>
            <form method="post" action="/admin/moderation/domains/<?= (int) $d['id'] ?>/toggle" style="display:inline">
                <?= CsrfService::field() ?>
                <button type="submit"
                        class="btn btn-secondary"
                        style="font-size:0.78rem;padding:0.2rem 0.6rem"
                        onclick="return confirm('<?= $d['is_active'] ? 'Deactivate' : 'Reactivate' ?> this domain block?')">
                    <?= $d['is_active'] ? 'Deactivate' : 'Reactivate' ?>
                </button>
            </form>
        </td>
    </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<p style="font-size:0.8rem;color:#aaa;margin-top:0.25rem">
    Inactive entries remain in the list but do not block destinations.
    Blocking a domain also blocks all its subdomains.
</p>
<?php endif; ?>
