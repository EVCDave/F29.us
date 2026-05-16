<?php $homeUser = AuthService::currentUser(); ?>

<!-- ── Hero ─────────────────────────────────────────────────────────────── -->
<section class="home-hero">
    <h1 class="home-hero-title">QR codes you can change after you print them.</h1>
    <p class="home-hero-sub">
        Create dynamic QR codes with editable destinations, scan analytics, custom styling,
        and downloadable PNG or SVG files. Need a simple one-time code? Use the built-in
        static QR generator for Wi-Fi, email, vCards, URLs, and text.
    </p>
    <div class="home-hero-actions">
        <?php if ($homeUser): ?>
        <a href="/qr/create" class="btn">Create a Dynamic QR Code</a>
        <a href="/qr/static" class="btn btn-secondary">Try the Static QR Generator</a>
        <?php else: ?>
        <a href="/register" class="btn">Get started free</a>
        <a href="/qr/create" class="btn btn-secondary">Create a Dynamic QR Code</a>
        <a href="/qr/static" class="btn btn-secondary">Try the Static QR Generator</a>
        <?php endif; ?>
    </div>
    <p class="home-hero-tertiary">
        <a href="/pricing">View Plans</a> &middot;
        <a href="/help">How it works</a>
        <?php if (!$homeUser): ?>
        &middot; <a href="/login">Login</a>
        <?php endif; ?>
    </p>
</section>

<!-- ── Why dynamic ───────────────────────────────────────────────────────── -->
<section class="home-section">
    <h2>Why use a dynamic QR code?</h2>
    <p class="mw-720">
        A dynamic QR code points to an f29.us short link. You can change where that link
        redirects later, so printed materials keep working even when your destination changes.
    </p>
    <ul class="home-benefits">
        <li>Change the destination URL without reprinting the QR code.</li>
        <li>Pause, resume, archive, and restore links.</li>
        <li>Preserve destination history and restore previous URLs.</li>
        <li>Track scan activity and campaign performance.</li>
        <li>Use custom slugs on supported plans.</li>
    </ul>
</section>

<!-- ── Feature cards ────────────────────────────────────────────────────── -->
<section class="home-section">
    <h2>Everything you need to manage QR codes</h2>
    <div class="feature-grid">
        <div class="feature-card">
            <h3>Dynamic QR Management</h3>
            <p>
                Create QR codes that point to managed short links. Edit destinations, pause links,
                archive old campaigns, and restore previous destinations.
            </p>
        </div>
        <div class="feature-card">
            <h3>Static QR Generator</h3>
            <p>
                Generate static QR codes for Wi-Fi credentials, email links, vCards, URLs, and
                plain text. Static codes are not saved, tracked, or counted against your QR limit.
            </p>
        </div>
        <div class="feature-card">
            <h3>Styling</h3>
            <p>
                Customize colors, transparent backgrounds, module shapes, and logos on supported
                plans. Finder patterns stay square for scan reliability.
            </p>
        </div>
        <div class="feature-card">
            <h3>Downloads</h3>
            <p>
                Download PNG files in plan-based sizes up to 4096&nbsp;px, or use SVG for scalable
                vector output on supported plans.
            </p>
        </div>
        <div class="feature-card">
            <h3>Analytics</h3>
            <p>
                See scan totals, date ranges, device breakdowns, top referrers, and bot-filtered
                activity for dynamic QR codes.
            </p>
        </div>
        <div class="feature-card">
            <h3>Security and Abuse Controls</h3>
            <p>
                Reserved slugs, blocked domains, admin moderation, and abuse reporting help keep
                the platform safer.
            </p>
        </div>
    </div>
</section>

<!-- ── Static vs Dynamic comparison ─────────────────────────────────────── -->
<section class="home-section">
    <h2>Static or dynamic? Use the right QR code for the job.</h2>
    <div class="scroll-x">
        <table class="comparison-table">
            <thead>
                <tr>
                    <th>Feature</th>
                    <th>Static QR</th>
                    <th>Dynamic QR</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Destination editable later</td>
                    <td>No</td>
                    <td>Yes</td>
                </tr>
                <tr>
                    <td>Stored in your account</td>
                    <td>No</td>
                    <td>Yes</td>
                </tr>
                <tr>
                    <td>Scan analytics</td>
                    <td>No</td>
                    <td>Yes</td>
                </tr>
                <tr>
                    <td>Counts against QR limit</td>
                    <td>No</td>
                    <td>Yes, unless archived</td>
                </tr>
                <tr>
                    <td>Best for</td>
                    <td>Wi-Fi, vCards, email, plain text</td>
                    <td>Printed campaigns, signs, flyers, business cards</td>
                </tr>
                <tr>
                    <td>Redirect controlled by f29.us</td>
                    <td>No</td>
                    <td>Yes</td>
                </tr>
            </tbody>
        </table>
    </div>
    <p class="text-2xs text-muted-2 mt-2">
        Static QR codes encode the final content directly into the image. Dynamic QR codes encode
        an f29.us short link that can be managed after printing.
    </p>
</section>

<!-- ── Styling and downloads ────────────────────────────────────────────── -->
<section class="home-section">
    <h2>Make QR codes that fit your brand.</h2>
    <ul class="home-benefits">
        <li>Foreground and background colors with contrast validation.</li>
        <li>Transparent backgrounds for layered designs.</li>
        <li>Module styles: classic squares, gapped squares, or circles.</li>
        <li>Logo upload on supported plans for dynamic QR codes.</li>
        <li>PNG export at 512, 1024, 2048, or 4096&nbsp;px (size depends on plan).</li>
        <li>SVG vector download on supported plans.</li>
    </ul>
    <p class="text-2xs text-muted-2">
        Always test styled QR codes before printing. Strong contrast and clear finder patterns
        improve scan reliability.
    </p>
</section>

<!-- ── Analytics ────────────────────────────────────────────────────────── -->
<section class="home-section">
    <h2>Understand how your QR codes are used.</h2>
    <ul class="home-benefits">
        <li>Total scans and daily counts for any date range.</li>
        <li>Device-type breakdown (mobile, tablet, desktop, bot).</li>
        <li>Top referrers.</li>
        <li>Optional bot filtering.</li>
        <li>Plan-based analytics retention windows.</li>
    </ul>
    <p class="text-2xs text-muted-2">
        Analytics apply to dynamic QR codes. Static QR codes are not tracked.
    </p>
</section>

<!-- ── Pricing CTA ──────────────────────────────────────────────────────── -->
<section class="cta-panel">
    <h2>Start free. Upgrade when you need more.</h2>
    <p class="mw-720">
        Free accounts can create dynamic QR codes within the free limit and generate basic static
        QR codes. Paid plans unlock higher limits, styling, SVG export, larger PNG downloads, logo
        upload, and expanded analytics.
    </p>
    <p>
        <a href="/pricing" class="btn">View Plans and Pricing</a>
    </p>
</section>

<!-- ── Final CTA ────────────────────────────────────────────────────────── -->
<section class="home-section">
    <h2>Ready to create your next QR code?</h2>
    <div class="home-hero-actions">
        <a href="/qr/create" class="btn">Create Dynamic QR Code</a>
        <a href="/qr/static" class="btn btn-secondary">Create Static QR Code</a>
    </div>
    <?php if (!$homeUser): ?>
    <p class="text-2xs text-muted-2 mt-2">
        Don't have an account yet? <a href="/register">Sign up free</a> &mdash;
        no payment required to get started.
    </p>
    <?php endif; ?>
</section>
