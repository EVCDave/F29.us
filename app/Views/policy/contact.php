<div class="mw-680">

<h1>Contact</h1>

<p class="mb-4">
    Have a question or need help with f29.us? Send us a message using the form below.
    We&rsquo;ll use the email address you provide to respond.
</p>

<div class="card-warn mb-6">
    <p class="fw-medium mb-1">Reporting an abusive QR code?</p>
    <p class="text-88 mb-0">
        Need to report a malicious, deceptive, or abusive QR code? Please use the
        <a href="/abuse">Report Abuse</a> page so we can review it through the correct process.
    </p>
</div>

<?php if (!empty($submitted)): ?>
<div class="card-success mb-6">
    <p class="fw-medium mb-1">Thanks &mdash; your message has been sent.</p>
    <p class="text-88 mb-0">
        We&rsquo;ll use the email address you provided to respond if a reply is needed.
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

<form method="post" action="/contact" class="mb-8">
    <?= CsrfService::field() ?>
    <input type="hidden" name="form_started_at" value="<?= (int) ($formStartedAt ?? time()) ?>">

    <div class="hp-field" aria-hidden="true">
        <label for="website">Website (leave blank)</label>
        <input type="text" id="website" name="website" tabindex="-1" autocomplete="off" value="">
    </div>

    <div class="form-group">
        <label for="contact-name">Your name</label>
        <input
            type="text"
            id="contact-name"
            name="name"
            maxlength="200"
            value="<?= View::e((string) ($input['name'] ?? '')) ?>"
            required
        >
    </div>

    <div class="form-group">
        <label for="contact-email">Your email</label>
        <input
            type="email"
            id="contact-email"
            name="email"
            maxlength="255"
            value="<?= View::e((string) ($input['email'] ?? '')) ?>"
            autocomplete="email"
            required
        >
    </div>

    <div class="form-group">
        <label for="contact-category">Category</label>
        <?php $selectedCategory = (string) ($input['category'] ?? 'general'); ?>
        <select id="contact-category" name="category" required>
            <?php foreach ($categories as $value => $label): ?>
            <option value="<?= View::e((string) $value) ?>"
                    <?= $selectedCategory === $value ? 'selected' : '' ?>>
                <?= View::e((string) $label) ?>
            </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="form-group">
        <label for="contact-subject">Subject</label>
        <input
            type="text"
            id="contact-subject"
            name="subject"
            maxlength="200"
            value="<?= View::e((string) ($input['subject'] ?? '')) ?>"
            required
        >
    </div>

    <div class="form-group">
        <label for="contact-message">Message</label>
        <textarea
            id="contact-message"
            name="message"
            rows="6"
            maxlength="5000"
            required><?= View::e((string) ($input['message'] ?? '')) ?></textarea>
        <p class="text-2xs text-muted-2 mt-1">Up to 5000 characters.</p>
    </div>

    <div class="form-group">
        <button type="submit" class="btn">Send Message</button>
    </div>
</form>

<h2 class="mb-3">Other ways to reach us</h2>

<h3>General Support</h3>
<p>For questions about your account, plan, or how the service works:</p>
<p><strong><a href="mailto:<?= View::e($supportEmail) ?>"><?= View::e($supportEmail) ?></a></strong></p>

<h3>Abuse Reports</h3>
<p>To report a f29.us short link being used for phishing, malware, spam, or other harmful activity:</p>
<p><strong><a href="mailto:<?= View::e($abuseEmail) ?>"><?= View::e($abuseEmail) ?></a></strong></p>
<p>See the <a href="/abuse">abuse reporting page</a> for details on what to include.</p>

<h3>Privacy Questions</h3>
<p>For questions about data we hold, corrections, or deletion requests:</p>
<p><strong><a href="mailto:<?= View::e($privacyEmail) ?>"><?= View::e($privacyEmail) ?></a></strong></p>
<p>See our <a href="/privacy">Privacy Policy</a> for more information about what data we collect and how it is used.</p>

<h3>Policy Questions</h3>
<p>For questions about our <a href="/terms">Terms of Service</a> or <a href="/acceptable-use">Acceptable Use Policy</a>,
contact us at the general support address above.</p>

</div>
