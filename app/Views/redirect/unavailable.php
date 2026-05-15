<div class="page-center">
    <h1>Link Unavailable</h1>

    <?php if (($status ?? '') === 'paused'): ?>
    <p class="page-lead">
        This short link has been paused by its owner.
    </p>
    <p class="text-base text-muted-2">
        It may become available again in the future.
    </p>
    <?php elseif (($status ?? '') === 'archived'): ?>
    <p class="page-lead">
        This short link has been archived by its owner.
    </p>
    <p class="text-base text-muted-2">
        If you expected this to work, contact whoever shared the link with you.
    </p>
    <?php elseif (($status ?? '') === 'disabled'): ?>
    <p class="page-lead">
        This short link is no longer available.
    </p>
    <p class="text-base text-muted-2">
        If you expected this to work, contact support.
    </p>
    <?php else: ?>
    <p class="page-lead">
        This short link is no longer available.
    </p>
    <p class="text-base text-muted-2">
        If you expected this to work, contact whoever shared the link with you.
    </p>
    <?php endif; ?>

    <p class="mt-10">
        <a href="/" class="btn btn-secondary">Go to f29.us</a>
    </p>
</div>
