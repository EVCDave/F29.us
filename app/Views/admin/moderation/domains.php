<div class="page-header">
    <h1>Blocked Domains</h1>
    <a href="/admin/moderation/links" class="back-link">&larr; Moderated Links</a>
</div>

<?php if ($flash): ?>
<div class="flash flash-<?= View::e($flash['type']) ?>"><?= View::e($flash['text']) ?></div>
<?php endif; ?>

<!-- ── Add domain form ─────────────────────────────────────────────────────── -->
<div class="card mw-520">
    <h2 class="mb-2">Block a Domain</h2>
    <p class="text-sm text-muted mb-4">
        Blocking a domain prevents users from creating or editing QR destinations to that domain
        (and all its subdomains). Enter a bare domain — e.g. <code>example.com</code>.
    </p>
    <form method="post" action="/admin/moderation/domains">
        <?= CsrfService::field() ?>
        <div class="d-flex gap-3 flex-wrap align-end">
            <div class="form-group mb-0 flex-1 min-w-160">
                <label for="domain">Domain</label>
                <input type="text" id="domain" name="domain"
                       placeholder="example.com"
                       class="mw-100pct" required>
            </div>
            <div class="form-group mb-0 flex-1 min-w-160">
                <label for="reason">Reason <span class="text-faint fw-normal">(optional)</span></label>
                <input type="text" id="reason" name="reason"
                       placeholder="e.g. Phishing"
                       maxlength="255"
                       class="mw-100pct">
            </div>
            <button type="submit" class="btn">Block Domain</button>
        </div>
    </form>
</div>

<!-- ── Domain table ───────────────────────────────────────────────────────── -->
<h2 class="mb-3">Blocklist (<?= count($domains) ?>)</h2>
<?php if (empty($domains)): ?>
<p class="text-muted-2">No domains are currently blocked.</p>
<?php else: ?>
<table class="text-88">
    <thead>
        <tr>
            <th>Domain</th>
            <th>Reason</th>
            <th class="col-80 text-center">Active</th>
            <th class="col-130">Added</th>
            <th class="col-130">Added by</th>
            <th class="col-80"></th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($domains as $d): ?>
    <tr class="<?= $d['is_active'] ? '' : 'opacity-55' ?>">
        <td><code><?= View::e($d['domain']) ?></code></td>
        <td class="text-muted"><?= View::e($d['reason'] ?? '—') ?></td>
        <td class="text-center">
            <?php if ($d['is_active']): ?>
            <span class="text-success fw-bold">Yes</span>
            <?php else: ?>
            <span class="text-faint">No</span>
            <?php endif; ?>
        </td>
        <td class="text-muted text-82"><?= View::e(substr($d['created_at'], 0, 10)) ?></td>
        <td class="text-muted text-82"><?= View::e($d['created_by_email'] ?? '—') ?></td>
        <td>
            <form method="post" action="/admin/moderation/domains/<?= (int) $d['id'] ?>/toggle"
                  class="form-inline"
                  data-confirm="<?= View::e($d['is_active'] ? 'Deactivate this domain block?' : 'Reactivate this domain block?') ?>">
                <?= CsrfService::field() ?>
                <button type="submit" class="btn btn-secondary btn-xs">
                    <?= $d['is_active'] ? 'Deactivate' : 'Reactivate' ?>
                </button>
            </form>
        </td>
    </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<p class="text-82 text-dim mt-1">
    Inactive entries remain in the list but do not block destinations.
    Blocking a domain also blocks all its subdomains.
</p>
<?php endif; ?>
