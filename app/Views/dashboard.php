<h1>Dashboard</h1>
<p>Welcome back, <strong><?= View::e($user['email'] ?? '') ?></strong>.</p>

<p class="notice">QR code features are not yet implemented.</p>

<p>
    <a href="/qr/create" class="btn">Create a QR Code</a>
</p>

<p style="margin-top:1.5rem">
    <a href="/qr">View My QR Codes</a>
</p>
