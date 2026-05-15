'use strict';

// ── Copy-to-clipboard button ───────────────────────────────────────────────
// Usage: <button data-copy-target="element-id" class="copy-btn">Copy</button>
document.querySelectorAll('[data-copy-target]').forEach(function (btn) {
    btn.addEventListener('click', function () {
        var el = document.getElementById(btn.dataset.copyTarget);
        if (!el) return;
        navigator.clipboard.writeText(el.textContent.trim()).then(function () {
            var orig = btn.textContent;
            btn.textContent = 'Copied!';
            setTimeout(function () { btn.textContent = orig; }, 1800);
        });
    });
});

// ── Confirm-before-submit ──────────────────────────────────────────────────
// Usage: <form data-confirm="Are you sure?"> or <button data-confirm="..." form="...">
document.querySelectorAll('form[data-confirm]').forEach(function (form) {
    form.addEventListener('submit', function (e) {
        if (!confirm(form.dataset.confirm)) {
            e.preventDefault();
        }
    });
});

// ── Analytics bar chart ────────────────────────────────────────────────────
// Usage: <div class="bar" data-bar-pct="75"></div>
document.querySelectorAll('[data-bar-pct]').forEach(function (el) {
    el.style.width = Math.max(2, parseInt(el.dataset.barPct, 10)) + '%';
});

// ── Submit-form link ───────────────────────────────────────────────────────
// Usage: <a href="#" data-submit-form="form-id">label</a>
document.querySelectorAll('a[data-submit-form]').forEach(function (a) {
    a.addEventListener('click', function (e) {
        e.preventDefault();
        var form = document.getElementById(a.dataset.submitForm);
        if (form) form.submit();
    });
});
