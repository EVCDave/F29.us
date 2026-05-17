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

// ── Static QR form progressive enhancement ────────────────────────────────
// Hides non-selected template sections and plan-unavailable style/logo
// sections after JS loads. Server-side validation and entitlement checks
// remain authoritative — this is presentation only.
//
// Required markup (rendered by app/Views/qr/static.php):
//   <form data-static-qr-form>
//     <input type="radio" name="type" value="text|wifi|email|vcard" data-static-template-radio>
//     <fieldset data-static-template-section="text|wifi|email|vcard">…</fieldset>
//     <div     data-static-style-section data-static-style-available="0|1">…</div>
//     <fieldset data-static-logo-section  data-static-logo-available="0|1">…</fieldset>
//   </form>
(function initStaticQrForm() {
    var root = document.querySelector('[data-static-qr-form]');
    if (!root) return;

    root.classList.add('js-enabled');

    var radios       = Array.prototype.slice.call(root.querySelectorAll('[data-static-template-radio]'));
    var sections     = Array.prototype.slice.call(root.querySelectorAll('[data-static-template-section]'));
    var styleSection = root.querySelector('[data-static-style-section]');
    var logoSection  = root.querySelector('[data-static-logo-section]');

    function selectedType() {
        for (var i = 0; i < radios.length; i++) {
            if (radios[i].checked) return radios[i].value;
        }
        return 'text';
    }

    function updateVisibility() {
        var type = selectedType();
        for (var i = 0; i < sections.length; i++) {
            sections[i].hidden = sections[i].getAttribute('data-static-template-section') !== type;
        }
        if (styleSection) {
            styleSection.hidden = styleSection.getAttribute('data-static-style-available') !== '1';
        }
        if (logoSection) {
            logoSection.hidden = logoSection.getAttribute('data-static-logo-available') !== '1';
        }
    }

    radios.forEach(function (radio) {
        radio.addEventListener('change', updateVisibility);
    });

    updateVisibility();
})();
