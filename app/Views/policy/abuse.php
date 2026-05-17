<div class="mw-680">

<h1>Report Abuse</h1>

<p class="text-105 mb-4">
    Use this form to report f29.us links or QR codes that may be malicious, deceptive,
    illegal, or otherwise abusive. For general questions or account help, please use
    the <a href="/contact">Contact page</a>.
</p>

<?php if (!empty($submitted)): ?>
<div class="card-success mb-6">
    <p class="fw-medium mb-1">Thanks &mdash; your abuse report has been submitted.</p>
    <p class="text-88 mb-0">
        We&rsquo;ll review it and take appropriate action if it violates our policies.
    </p>
</div>
<?php endif; ?>

<?php if (!empty($errors)): ?>
<ul class="errors">
    <?php foreach ($errors as $e): ?>
    <li><?= View::e($e) ?></li>
    <?php endforeach; ?>
</ul>
<?php endif; ?>

<h2>What to Report</h2>
<p>Reports are most useful when they involve:</p>
<ul class="ul-content">
    <li>Phishing pages or credential-harvesting sites</li>
    <li>Malware downloads or drive-by exploits</li>
    <li>Spam campaigns</li>
    <li>Deceptive redirects impersonating known services</li>
    <li>Illegal content</li>
    <li>Harassment or targeted abuse</li>
</ul>

<h2>Submit a Report</h2>
<form method="post" action="/abuse" class="mb-6">
    <?= CsrfService::field() ?>
    <input type="hidden" name="form_started_at" value="<?= (int) ($formStartedAt ?? time()) ?>">

    <div class="hp-field" aria-hidden="true">
        <label for="abuse-website">Website (leave blank)</label>
        <input type="text" id="abuse-website" name="website" tabindex="-1" autocomplete="off" value="">
    </div>

    <div class="form-group">
        <label for="abuse-name">Your name</label>
        <input
            type="text"
            id="abuse-name"
            name="name"
            maxlength="200"
            value="<?= View::e((string) ($input['name'] ?? '')) ?>"
            required
        >
    </div>

    <div class="form-group">
        <label for="abuse-email">Your email</label>
        <input
            type="email"
            id="abuse-email"
            name="email"
            maxlength="255"
            value="<?= View::e((string) ($input['email'] ?? '')) ?>"
            autocomplete="email"
            required
        >
        <p class="text-2xs text-muted-2 mt-1">
            We may not respond to every report, but we use this address if we need follow-up details.
        </p>
    </div>

    <div class="form-group">
        <label for="abuse-reported-url">Reported f29 link or QR URL</label>
        <input
            type="url"
            id="abuse-reported-url"
            name="reported_url"
            maxlength="2048"
            value="<?= View::e((string) ($input['reported_url'] ?? '')) ?>"
            placeholder="https://f29.us/xyz"
            required
        >
        <p class="text-2xs text-muted-2 mt-1">
            Paste the f29.us short link, QR URL, or page where you found the suspicious QR code.
        </p>
    </div>

    <div class="form-group">
        <label for="abuse-destination-url">Destination URL <span class="text-muted-2">(optional)</span></label>
        <input
            type="url"
            id="abuse-destination-url"
            name="destination_url"
            maxlength="2048"
            value="<?= View::e((string) ($input['destination_url'] ?? '')) ?>"
            placeholder="https://example.com"
        >
        <p class="text-2xs text-muted-2 mt-1">
            If you know where the QR code redirects, include that URL too.
        </p>
    </div>

    <div class="form-group">
        <label for="abuse-type">Abuse type</label>
        <?php $selectedType = (string) ($input['abuse_type'] ?? ''); ?>
        <select id="abuse-type" name="abuse_type" required>
            <option value="" <?= $selectedType === '' ? 'selected' : '' ?>>Choose an abuse type&hellip;</option>
            <?php foreach ($abuseTypes as $value => $label): ?>
            <option value="<?= View::e((string) $value) ?>"
                    <?= $selectedType === $value ? 'selected' : '' ?>>
                <?= View::e((string) $label) ?>
            </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="form-group">
        <label for="abuse-message">Description / evidence</label>
        <textarea
            id="abuse-message"
            name="message"
            rows="6"
            maxlength="5000"
            required><?= View::e((string) ($input['message'] ?? '')) ?></textarea>
        <p class="text-2xs text-muted-2 mt-1">
            Describe what happened and why you believe the link or QR code is abusive.
            Up to 5000 characters.
        </p>
    </div>

    <div class="form-group">
        <button type="submit" class="btn">Submit Abuse Report</button>
    </div>
</form>

<h2>What to Expect</h2>
<p>We aim to review reports promptly. If a link is confirmed to violate our
<a href="/acceptable-use">Acceptable Use Policy</a>, we will disable it. We may not be able to
respond individually to every report, but all reports are reviewed.</p>
<p>Automated scanning is not currently implemented. Our review process is manual.</p>

<h2>Alternate Contact</h2>
<p>If you prefer to email instead of using the form, send your report to:
<strong><a href="mailto:<?= View::e($abuseEmail) ?>"><?= View::e($abuseEmail) ?></a></strong></p>

<p class="mt-8 text-sm text-muted">
    For general support questions, see the <a href="/contact">contact page</a>.<br>
    For privacy questions, see our <a href="/privacy">Privacy Policy</a>.
</p>

</div>
