<h1>Admin</h1>
<p style="color:#666;margin-bottom:1.5rem">Internal administration area.</p>

<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:1rem;max-width:560px;margin-bottom:2.5rem">
    <div style="background:#fff;border:1px solid #e5e7eb;border-radius:6px;padding:1.25rem">
        <div style="font-size:1.75rem;font-weight:700"><?= (int) $totalUsers ?></div>
        <div style="color:#666;font-size:0.85rem;margin-top:0.2rem">Total Users</div>
    </div>
    <div style="background:#fff;border:1px solid #e5e7eb;border-radius:6px;padding:1.25rem">
        <div style="font-size:1.75rem;font-weight:700"><?= (int) $totalQr ?></div>
        <div style="color:#666;font-size:0.85rem;margin-top:0.2rem">Total QR Codes</div>
    </div>
    <div style="background:#fff;border:1px solid #e5e7eb;border-radius:6px;padding:1.25rem">
        <div style="font-size:1.75rem;font-weight:700"><?= (int) $totalPlans ?></div>
        <div style="color:#666;font-size:0.85rem;margin-top:0.2rem">Plans</div>
    </div>
</div>

<h2 style="margin-bottom:0.75rem">Tools</h2>
<div class="actions-group">
    <a href="/admin/users" class="btn btn-secondary">User Management</a>
    <a href="/admin/plans" class="btn btn-secondary">Plan Catalog</a>
</div>
