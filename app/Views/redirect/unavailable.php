<div style="text-align:center;padding:4rem 1rem">
    <h1 style="font-size:2rem;margin-bottom:0.75rem">Link Unavailable</h1>

    <?php if (($status ?? '') === 'paused'): ?>
    <p style="font-size:1.05rem;color:#555;margin-bottom:0.5rem">
        This short link has been paused by its owner.
    </p>
    <p style="color:#888;font-size:0.9rem">
        It may become available again in the future.
    </p>
    <?php else: ?>
    <p style="font-size:1.05rem;color:#555;margin-bottom:0.5rem">
        This short link is no longer available.
    </p>
    <p style="color:#888;font-size:0.9rem">
        If you expected this to work, contact whoever shared the link with you.
    </p>
    <?php endif; ?>

    <p style="margin-top:2.5rem">
        <a href="/" class="btn btn-secondary">Go to f29.us</a>
    </p>
</div>
