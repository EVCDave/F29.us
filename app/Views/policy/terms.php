<?php $lastUpdated = 'May 17, 2026'; ?>
<div class="mw-720">

<h1>Terms of Service</h1>

<?php /*
<div class="card-warn mb-6 text-sm">
    <strong>Draft notice:</strong> This document is a placeholder and has not been reviewed by legal counsel.
    It should be reviewed and updated before wider public launch.
</div>
*/ ?>

<p class="text-sm text-muted mb-7">Last updated: <?= View::e($lastUpdated) ?></p>

<h2>1. Acceptance of Terms</h2>
<p>By accessing or using <?= htmlspecialchars(getenv('APP_NAME') ?: 'f29.us', ENT_QUOTES, 'UTF-8') ?> ("the Service"), you agree to be bound by these Terms of Service.
If you do not agree, do not use the Service.</p>

<h2>2. Description of Service</h2>
<p><?= htmlspecialchars(getenv('APP_NAME') ?: 'f29.us', ENT_QUOTES, 'UTF-8') ?> provides dynamic QR code generation and short-link management. Registered users can
create QR codes that redirect to a destination URL of their choosing and update that destination at any time
without reprinting the QR code.</p>

<h2>3. Account Registration</h2>
<p>To create QR codes you must register an account with a valid email address and password. You are responsible
for maintaining the security of your credentials and for all activity that occurs under your account.</p>
<p>You agree to provide accurate information during registration and to keep it up to date. You may not share
your account with others or create accounts for the purpose of evading service restrictions.</p>
<p>The login page offers an optional &ldquo;Remember me for 30 days&rdquo; feature that keeps you signed in on the current browser after your session ends. If you use this option on a shared or public device, you are responsible for logging out when you are finished. See the <a href="/privacy">Privacy Policy</a> for details on what is stored.</p>

<h2>4. QR Codes and Short Links</h2>
<p>You may create QR codes subject to the limits of your plan. Each QR code is backed by a short link
(a slug) that resolves to a destination URL. You may change the destination of an existing QR code at
any time, subject to plan entitlements and the restrictions below.</p>
<p>Slugs are allocated on a first-come basis. A custom slug, once assigned, cannot be transferred to another
user.</p>
<p>Archived QR codes do not redirect and do not count against your active QR code limit. Slugs, scan
analytics, audit history, and destination history may be retained for archived codes. Self-service permanent
deletion of archived QR codes is not currently available.</p>

<h2>5. Your Responsibility for Destination URLs</h2>
<p>You are solely responsible for the destination URLs you set. You must not direct QR codes or short links
to content that violates these Terms or the <a href="/acceptable-use">Acceptable Use Policy</a>.</p>
<p><?= htmlspecialchars(getenv('APP_NAME') ?: 'f29.us', ENT_QUOTES, 'UTF-8') ?> does not pre-screen destination URLs. However, we reserve the right to disable any link at our
discretion if we believe it violates these Terms or applicable law.</p>

<h2>6. Uploaded Logos and Branding Assets</h2>
<p>Eligible plan users may upload logo or image files to be embedded in generated QR codes. By uploading
a file you represent that you have the rights necessary to use it for this purpose.</p>
<ul class="ul-content">
    <li>You are solely responsible for any logos or branding assets you upload.</li>
    <li>Uploaded assets must not infringe any intellectual property right, including copyright and trademark.</li>
    <li>Uploaded assets must not violate applicable law or the rights of any third party.</li>
</ul>
<p>We reserve the right to remove uploaded assets that violate these Terms.</p>

<h2>7. Prohibited Uses</h2>
<p>Please read the <a href="/acceptable-use">Acceptable Use Policy</a> for a full list of prohibited uses.
In summary, you may not use the Service for phishing, malware distribution, spam, harassment, deceptive
redirects, impersonation, or any illegal purpose.</p>

<h2>8. Moderation and Account Actions</h2>
<p>We may, at our sole discretion:</p>
<ul class="ul-content">
    <li>disable any short link that violates these Terms or the Acceptable Use Policy</li>
    <li>suspend or terminate accounts engaged in repeated or serious violations</li>
    <li>block destination domains associated with abuse</li>
</ul>
<p>We will make reasonable efforts to notify affected users where practical, but are not obligated to do so
in cases of urgent moderation action.</p>

<h2>9. Plans, Billing, and Subscriptions</h2>
<p>The Service offers multiple plans with different feature entitlements. At this time, billing and payment
processing are not implemented. Plan assignments are made manually. This section will be updated when
automated billing is introduced.</p>

<h2>10. Service Availability</h2>
<p>We aim to keep the Service available, but we make no guarantee of uninterrupted or error-free operation.
The Service may be unavailable due to maintenance, technical issues, or circumstances beyond our control.
We are not liable for losses resulting from downtime or data unavailability.</p>

<h2>11. Limitation of Liability</h2>
<p>To the fullest extent permitted by applicable law, <?= htmlspecialchars(getenv('APP_NAME') ?: 'f29.us', ENT_QUOTES, 'UTF-8') ?> is not liable for any indirect, incidental,
special, consequential, or punitive damages arising out of or relating to your use of the Service. Our
total liability for any claim arising from the Service is limited to the amount you paid us in the six
months prior to the claim, if any.</p>
<p>The Service is provided "as is" without warranty of any kind, express or implied.</p>

<h2>12. Governing Law</h2>
<p>These Terms are governed by the laws of the State of Colorado, without regard to conflict of law
principles. This section will be reviewed by legal counsel before wider public launch.</p>

<h2>13. Changes to These Terms</h2>
<p>We may update these Terms from time to time. Continued use of the Service after changes are posted
constitutes acceptance of the revised Terms. We will update the date at the top of this page when changes
are made.</p>

<h2>14. Contact</h2>
<p>Questions about these Terms? <a href="/contact">Contact us</a>.</p>

</div>
