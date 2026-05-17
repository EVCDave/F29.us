<div class="page-header page-header-lg">
    <h1>Contact Messages</h1>
    <a href="/admin" class="back-link">&larr; Admin</a>
</div>

<?php if ($flash): ?>
<div class="flash flash-<?= View::e($flash['type']) ?> mb-4"><?= View::e($flash['text']) ?></div>
<?php endif; ?>

<p class="text-88 mb-4">
    <strong><?= (int) $newCount ?></strong> message<?= $newCount === 1 ? '' : 's' ?> with status <code>new</code>.
</p>

<form method="get" action="/admin/contact-messages" class="filter-form mb-4">
    <div>
        <label for="cm-status" class="filter-label">Status</label>
        <select id="cm-status" name="status">
            <option value=""         <?= $statusFilter === ''         ? 'selected' : '' ?>>All</option>
            <option value="new"      <?= $statusFilter === 'new'      ? 'selected' : '' ?>>New</option>
            <option value="reviewed" <?= $statusFilter === 'reviewed' ? 'selected' : '' ?>>Reviewed</option>
            <option value="closed"   <?= $statusFilter === 'closed'   ? 'selected' : '' ?>>Closed</option>
        </select>
    </div>
    <div>
        <label for="cm-category" class="filter-label">Category</label>
        <select id="cm-category" name="category">
            <option value="" <?= $categoryFilter === '' ? 'selected' : '' ?>>All</option>
            <?php foreach ($categories as $value => $label): ?>
            <option value="<?= View::e((string) $value) ?>" <?= $categoryFilter === $value ? 'selected' : '' ?>>
                <?= View::e((string) $label) ?>
            </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div>
        <label for="cm-q" class="filter-label">Search email / subject</label>
        <input type="text" id="cm-q" name="q" value="<?= View::e($search) ?>" class="mw-240" maxlength="200">
    </div>
    <button type="submit" class="btn btn-secondary">Filter</button>
    <?php if ($statusFilter !== '' || $categoryFilter !== '' || $search !== ''): ?>
    <a href="/admin/contact-messages" class="filter-link">Clear</a>
    <?php endif; ?>
</form>

<?php if (empty($messages)): ?>
<p class="text-muted-2 text-base">No contact messages match the current filters.</p>
<?php else: ?>
<div class="scroll-x">
<table class="text-85">
    <thead>
        <tr>
            <th class="col-65">ID</th>
            <th class="col-140">When (UTC)</th>
            <th class="col-90">Status</th>
            <th class="col-150">Category</th>
            <th class="col-160">Name</th>
            <th class="col-200">Email</th>
            <th>Subject</th>
            <th class="col-80"></th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($messages as $m): ?>
        <?php $isAbuse = ((string) $m['category']) === 'abuse'; ?>
        <tr<?= $isAbuse ? ' class="row-abuse"' : '' ?>>
            <td>#<?= (int) $m['id'] ?></td>
            <td class="text-muted nowrap"><?= View::e((string) $m['created_at']) ?></td>
            <td>
                <span class="status-<?= View::e((string) $m['status']) ?>">
                    <?= View::e(ucfirst((string) $m['status'])) ?>
                </span>
            </td>
            <td>
                <?php if ($isAbuse): ?>
                <span class="badge-abuse">Abuse report</span>
                <?php if (!empty($m['related_short_link_id'])): ?>
                <span class="text-2xs text-muted-2 d-block">
                    linked to <a href="/admin/moderation/links/<?= (int) $m['related_short_link_id'] ?>">link #<?= (int) $m['related_short_link_id'] ?></a>
                </span>
                <?php endif; ?>
                <?php else: ?>
                <?= View::e($categories[(string) $m['category']] ?? (string) $m['category']) ?>
                <?php endif; ?>
            </td>
            <td><?= View::e((string) $m['name']) ?></td>
            <td>
                <?= View::e((string) $m['email']) ?>
                <?php if ($m['user_id'] !== null): ?>
                <span class="text-2xs text-muted-2 d-block">
                    user #<?= (int) $m['user_id'] ?><?php if (!empty($m['user_email'])): ?>
                    &mdash; <?= View::e((string) $m['user_email']) ?><?php endif; ?>
                </span>
                <?php endif; ?>
            </td>
            <td class="word-break"><?= View::e((string) $m['subject']) ?></td>
            <td>
                <a href="/admin/contact-messages/<?= (int) $m['id'] ?>" class="btn btn-secondary btn-xs">View</a>
            </td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>
</div>
<p class="text-2xs text-muted-2 mt-2">Showing up to 100 most recent messages.</p>
<?php endif; ?>
