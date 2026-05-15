<?php $lastUpdated = 'May 15, 2026'; ?>
<div class="mw-720">

<h1>Privacy Policy</h1>

<div class="card-warn mb-6 text-sm">
    <strong>Draft notice:</strong> This document is a placeholder and has not been reviewed by legal counsel.
    It should be reviewed and updated before wider public launch.
</div>

<p class="text-sm text-muted mb-7">Last updated: <?= View::e($lastUpdated) ?></p>

<h2>1. Overview</h2>
<p>This Privacy Policy describes what data f29.us Dynamic QR collects, how it is used, and how it is
protected. We aim to collect only what is necessary to operate the Service.</p>

<h2>2. Data We Collect</h2>

<h3>Account data</h3>
<ul class="ul-content">
    <li>Email address — used for login and account identification</li>
    <li>Password — stored as a bcrypt hash; your raw password is never stored or logged</li>
    <li>Account status, role, and timestamps (created, last login)</li>
    <li>Optional profile fields you choose to provide: first name, last name, display name, company name, phone number, and timezone</li>
</ul>

<h3>QR code and short-link data</h3>
<ul class="ul-content">
    <li>QR code names you assign</li>
    <li>Short-link slugs (including custom slugs you choose)</li>
    <li>Destination URLs you set and their history</li>
    <li>QR styling settings: custom foreground and background colors, error correction level</li>
    <li>Uploaded QR logo files used for branded QR codes (stored server-side, associated with the QR style)</li>
</ul>

<h3>Scan analytics</h3>
<p>When a visitor scans one of your QR codes, we log:</p>
<ul class="ul-content">
    <li>Timestamp of the scan</li>
    <li>IP address — stored as an HMAC-SHA256 hash, not in plain text (see section 4)</li>
    <li>User agent string (browser/device identifier, truncated to 1000 characters)</li>
    <li>Referer URL (truncated to 2000 characters)</li>
    <li>Inferred device type (mobile, tablet, desktop, or unknown)</li>
    <li>Bot flag (whether the scan appears to be automated traffic)</li>
    <li>Geographic fields (country, region, city) — currently stored as empty; geolocation is not yet implemented</li>
</ul>

<h3>Subscription and administrative data</h3>
<ul class="ul-content">
    <li>Plan assignments and billing cycle records</li>
    <li>Subscription change requests and their status</li>
    <li>Admin audit log entries (records of significant actions within the admin area)</li>
    <li>Login attempt records (used for throttling; retained for 90 days)</li>
</ul>

<h2>3. How We Use Your Data</h2>
<ul class="ul-content">
    <li><strong>Account operation</strong> — authentication, session management, plan enforcement</li>
    <li><strong>Redirect service</strong> — resolving short links to destination URLs</li>
    <li><strong>Analytics</strong> — providing you with scan statistics on your own QR codes</li>
    <li><strong>Abuse prevention</strong> — detecting and blocking misuse via login throttling, moderation, and domain blocklists</li>
    <li><strong>Transactional email</strong> — sending verification, password reset, account security, subscription, and moderation notices</li>
    <li><strong>Operational troubleshooting</strong> — diagnosing errors via server-side logs</li>
</ul>

<h2>4. IP Address Handling</h2>
<p>IP addresses recorded during QR code scans are hashed using HMAC-SHA256 with a server-side secret key
before storage. The plain IP address is not stored. The hash cannot be reversed to recover the original IP
without the secret key. This approach allows abuse detection (identifying repeated scans from the same
source) without storing identifiable IP addresses in plain text.</p>
<p>Login attempt records also use HMAC-hashed IPs for the same purpose.</p>

<h2>5. Cookies and Sessions</h2>
<p>We use a single session cookie named <code>f29_sess</code> to maintain your authenticated session.
The cookie is HTTP-only, uses <code>SameSite=Lax</code>, and is set to expire when you close your browser
(session lifetime). We do not use tracking cookies or third-party advertising cookies.</p>
<p>No persistent cookies are set for unauthenticated visitors viewing public pages or following QR code
redirects.</p>

<h2>6. Third Parties</h2>
<p>We do not sell, rent, or share your personal data with third-party advertisers or data brokers.
We do not currently use third-party analytics, tracking pixels, or advertising networks.</p>
<p>Application data is stored on infrastructure we control or administer. Transactional email (verification, password reset, account security, subscription, and moderation notices) may be processed through a configured mail server or email service provider.</p>

<h2>7. Data Retention</h2>
<p>Account data is retained while your account is active. Scan analytics are retained indefinitely but
visibility within the app is limited by your plan's analytics retention window. Login attempt records
are pruned after 90 days via a scheduled cleanup job.</p>
<p>Uploaded QR logo files are retained while associated with a QR style. When a logo is replaced, removed, or the style is reset, the old logo file is deleted where practical.</p>
<p>Self-service account deletion is not currently implemented. Contact us if you wish to request deletion
of your account and associated data.</p>

<h2>8. Children's Privacy</h2>
<p>The Service is not intended for children under the age of 13. We do not knowingly collect personal information from children under 13. If you believe a child under 13 has provided us with personal information, please <a href="/contact">contact us</a> and we will take steps to remove that information.</p>

<h2>9. Your Rights and Contact</h2>
<p>If you have questions about data we hold on you, or wish to request corrections or deletion, please
<a href="/contact">contact us</a>.</p>

<h2>10. Changes to This Policy</h2>
<p>We may update this policy from time to time. We will update the date at the top of this page when
changes are made.</p>

</div>
