<?php
$id            = (int) $message['id'];
$categoryLabel = $categories[(string) $message['category']] ?? (string) $message['category'];
?>
<div class="page-header page-header-lg">
    <h1>Contact Message #<?= $id ?></h1>
    <a href="/admin/contact-messages" class="back-link">&larr; Contact Messages</a>
</div>

<?php if ($flash): ?>
<div class="flash flash-<?= View::e($flash['type']) ?> mb-4"><?= View::e($flash['text']) ?></div>
<?php endif; ?>

<?php if (((string) $message['category']) === 'abuse'): ?>
<div class="card-warn mw-720 mb-4">
    <p class="fw-medium mb-1">This message is an abuse report.</p>
    <p class="text-88 mb-1">
        Review the reported URL and destination carefully before taking moderation action.
    </p>
    <p class="text-2xs text-muted-2 mb-0">
        Related admin pages:
        <a href="/admin/moderation/links">Moderated Links</a> &middot;
        <a href="/admin/moderation/domains">Blocked Domains</a>
    </p>
</div>
<?php endif; ?>

<table class="mw-720 mb-6">
    <tr>
        <th class="col-140">Status</th>
        <td>
            <span class="status-<?= View::e((string) $message['status']) ?>">
                <?= View::e(ucfirst((string) $message['status'])) ?>
            </span>
            <?php if (!empty($message['handled_at'])): ?>
            <span class="text-2xs text-muted-2 ml-2">
                handled <?= View::e((string) $message['handled_at']) ?> UTC
                <?php if (!empty($message['handled_by_email'])): ?>
                by <?= View::e((string) $message['handled_by_email']) ?>
                <?php endif; ?>
            </span>
            <?php endif; ?>
        </td>
    </tr>
    <tr>
        <th>Category</th>
        <td><?= View::e($categoryLabel) ?></td>
    </tr>
    <tr>
        <th>Subject</th>
        <td class="word-break"><?= View::e((string) $message['subject']) ?></td>
    </tr>
    <tr>
        <th>Name</th>
        <td><?= View::e((string) $message['name']) ?></td>
    </tr>
    <tr>
        <th>Email</th>
        <td>
            <a href="mailto:<?= View::e((string) $message['email']) ?>"><?= View::e((string) $message['email']) ?></a>
        </td>
    </tr>
    <tr>
        <th>Account</th>
        <td>
            <?php if ($message['user_id'] !== null): ?>
            user #<?= (int) $message['user_id'] ?>
            <?php if (!empty($message['user_email'])): ?>
            &mdash; <?= View::e((string) $message['user_email']) ?>
            <?php endif; ?>
            <a href="/admin/users/<?= (int) $message['user_id'] ?>" class="ml-2 text-82">View user &nearr;</a>
            <?php else: ?>
            <span class="text-muted-2">(submitter was not logged in)</span>
            <?php endif; ?>
        </td>
    </tr>
    <tr>
        <th>Submitted</th>
        <td class="text-muted"><?= View::e((string) $message['created_at']) ?> UTC</td>
    </tr>
    <tr>
        <th>User agent</th>
        <td class="text-2xs text-muted-2 word-break">
            <?= $message['user_agent'] !== null ? View::e((string) $message['user_agent']) : '<span class="text-faint">(not provided)</span>' ?>
        </td>
    </tr>
    <tr>
        <th>IP hash</th>
        <td class="text-2xs text-muted-2 word-break">
            <?= $message['ip_hash'] !== null ? View::e((string) $message['ip_hash']) : '<span class="text-faint">(not provided)</span>' ?>
        </td>
    </tr>
</table>

<h2 class="mb-3">Message</h2>
<div class="card mw-720">
    <pre class="word-break contact-message-body"><?= View::e((string) $message['message']) ?></pre>
</div>

<h2 class="mb-3">Status</h2>
<div class="actions-group mb-6">
    <?php foreach (['new' => 'Reopen as new', 'reviewed' => 'Mark reviewed', 'closed' => 'Mark closed'] as $st => $label): ?>
    <?php if ($message['status'] !== $st): ?>
    <form method="post" action="/admin/contact-messages/<?= $id ?>/status" class="form-inline">
        <?= CsrfService::field() ?>
        <input type="hidden" name="status" value="<?= View::e($st) ?>">
        <button type="submit" class="btn btn-secondary"><?= View::e($label) ?></button>
    </form>
    <?php endif; ?>
    <?php endforeach; ?>
</div>

<h2 class="mb-3">Internal note</h2>
<p class="text-2xs text-muted-2 mb-2">
    Visible to admins only. Never emailed to the submitter and never shown publicly.
</p>
<form method="post" action="/admin/contact-messages/<?= $id ?>/note" class="mw-720">
    <?= CsrfService::field() ?>
    <div class="form-group">
        <label for="admin_note" class="sr-only">Internal note</label>
        <textarea id="admin_note" name="admin_note" rows="5" maxlength="5000"><?= View::e((string) ($message['admin_note'] ?? '')) ?></textarea>
        <p class="text-2xs text-muted-2 mt-1">Up to 5000 characters.</p>
    </div>
    <button type="submit" class="btn">Save note</button>
</form>
