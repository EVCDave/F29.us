<h1>Help Center</h1>
<p class="mb-6">
    Learn how f29.us handles dynamic QR codes, static QR codes, styling, downloads,
    analytics, subscriptions, and account security.
</p>

<div class="help-layout">
    <aside class="help-sidebar">
        <nav aria-label="Help table of contents">
            <ul class="help-toc">
                <li><a href="#overview">Overview</a></li>
                <li><a href="#dynamic-qr-codes">Dynamic QR Codes</a></li>
                <li><a href="#static-qr-codes">Static QR Codes</a></li>
                <li><a href="#qr-styling">QR Styling</a></li>
                <li><a href="#downloads">Downloads</a></li>
                <li><a href="#analytics">Analytics</a></li>
                <li><a href="#plans-and-entitlements">Plans and Entitlements</a></li>
                <li><a href="#account-settings">Account Settings</a></li>
                <li><a href="#billing-and-subscriptions">Billing and Subscriptions</a></li>
                <li><a href="#moderation-and-abuse">Moderation and Abuse</a></li>
                <li><a href="#security-and-privacy">Security and Privacy</a></li>
                <li><a href="#faq">Frequently Asked Questions</a></li>
            </ul>
        </nav>
    </aside>

    <main class="help-content">

        <section id="overview">
            <h2>Overview</h2>
            <p>
                f29.us creates and manages QR codes for the web. There are two flavors:
            </p>
            <ul>
                <li><strong>Dynamic QR codes</strong> point to a managed short URL hosted on f29.us. The destination behind that short URL can be changed at any time without reprinting the QR image.</li>
                <li><strong>Static QR codes</strong> encode the final payload (text, Wi-Fi, email, or vCard) directly into the image. Once printed they are permanent — f29 cannot change them later.</li>
            </ul>
            <p>
                Some features depend on your plan. <a href="/pricing">View current plans and pricing</a> for details.
            </p>
        </section>

        <section id="dynamic-qr-codes">
            <h2>Dynamic QR Codes</h2>
            <p>
                A dynamic QR code encodes an f29 short link such as <code>https://f29.us/abc123</code>.
                When somebody scans it, f29 redirects them to whatever destination you have currently configured.
                You can update that destination at any time, and every existing printed copy will follow the change.
            </p>
            <p>You can:</p>
            <ul>
                <li>Edit the destination URL after the QR is in the wild.</li>
                <li>Pause and resume a link without losing its history.</li>
                <li>Archive a link to stop redirects and free up your active-QR quota.</li>
                <li>Restore an archived link if capacity is available.</li>
                <li>See a full destination history and restore a previous destination.</li>
                <li>Collect scan analytics (subject to your plan's retention window).</li>
            </ul>
            <p><strong>Best for:</strong></p>
            <ul>
                <li>Marketing campaigns</li>
                <li>Printed flyers</li>
                <li>Business cards</li>
                <li>Signs</li>
                <li>Any QR code where the destination may change later</li>
            </ul>
            <p><a href="/qr/create">Create a dynamic QR code</a> &rarr;</p>
        </section>

        <section id="static-qr-codes">
            <h2>Static QR Codes</h2>
            <p>
                Static QR codes bake the payload directly into the QR image. They are
                <strong>not saved to your account</strong>, do not collect scan analytics, and do not
                consume your active-QR quota. Once downloaded, the code is permanent — there is no
                way to change it afterward.
            </p>
            <p>Supported templates:</p>
            <ul>
                <li><strong>Text / URL</strong> — any URL or short piece of plain text.</li>
                <li><strong>Wi-Fi</strong> — let visitors join your network by scanning (supports WPA, WEP, and open networks).</li>
                <li><strong>Email</strong> — open the user's mail app with a pre-filled recipient, subject, and body.</li>
                <li><strong>vCard</strong> — a digital business card with name, company, phone, email, and website.</li>
            </ul>
            <p>
                Use static QR codes when the destination will never change — Wi-Fi credentials,
                contact cards on a business card, a one-time email link, or short plain text.
            </p>
            <p><a href="/qr/static">Open the static QR generator</a> &rarr;</p>
        </section>

        <section id="qr-styling">
            <h2>QR Styling</h2>
            <p>Both dynamic and static QR codes share the same styling controls, gated by plan.</p>
            <h3>Colors</h3>
            <p>
                You can pick a foreground (dot) color and a background color. Strong contrast between
                them is required for reliable scanning — f29 enforces a minimum WCAG contrast ratio
                and rejects identical fg/bg pairs.
            </p>
            <h3>Transparent background</h3>
            <p>
                Both PNG and SVG output can render a transparent background, so the QR code can sit
                on top of a colored or patterned layout. Place transparent QR codes on a light,
                high-contrast surface and always test before printing.
            </p>
            <h3>Module styles</h3>
            <p>The shape of the dark modules can be:</p>
            <ul>
                <li><strong>Classic squares</strong> — the most compatible default.</li>
                <li><strong>Gapped squares</strong> — smaller centered squares for a modern, spaced look.</li>
                <li><strong>Circles</strong> — rounded dot style.</li>
            </ul>
            <p>
                The three <strong>finder patterns</strong> (the large square eyes in the corners) always
                remain classic full squares regardless of the chosen module style. This is intentional —
                scanners rely on those eyes to locate and orient the code.
            </p>
            <h3>Logo</h3>
            <p>
                On supported plans, QR codes can have a logo image composited into the center.
                When a logo is enabled the error-correction level is raised automatically so the code
                stays scannable. Static QR logos are not currently available — they need temporary
                upload handling that is still in design.
            </p>
            <h3>Scan reliability tips</h3>
            <ul>
                <li>Keep contrast high — dark dots on a light background scan most reliably.</li>
                <li>Avoid very light foregrounds, busy backgrounds, or oversized logos.</li>
                <li>Test the printed or rendered code at typical scanning distance before deployment.</li>
            </ul>
        </section>

        <section id="downloads">
            <h2>Downloads</h2>
            <h3>PNG</h3>
            <p>
                PNG downloads are raster (pixel) images. You can pick the output size from a fixed
                list based on your plan:
            </p>
            <ul>
                <li>512 px</li>
                <li>1024 px</li>
                <li>2048 px</li>
                <li>4096 px</li>
            </ul>
            <p>
                Output is rendered at the exact requested pixel dimensions. PNG filenames include
                the size so multiple downloads do not collide, for example
                <code>f29-qr-{name}-{slug}-1024px.png</code> for a dynamic QR, or
                <code>f29-static-qr-{type}-{timestamp}-1024px.png</code> for a static QR.
            </p>
            <h3>SVG</h3>
            <p>
                SVG is a vector format — it scales cleanly to any size with no quality loss. Use SVG
                for print artwork, signage, or wherever the QR code will be resized. SVG export is
                available on plans that include <code>can_export_svg</code>.
            </p>
        </section>

        <section id="analytics">
            <h2>Analytics</h2>
            <p>
                Every redirect from a dynamic QR's short link is logged so you can see how the code is
                being scanned. Analytics include:
            </p>
            <ul>
                <li>Total scans for a date range</li>
                <li>Daily scan counts</li>
                <li>Device-type breakdown</li>
                <li>Top referrers</li>
                <li>Optional bot filtering</li>
            </ul>
            <p>
                Your plan controls how far back analytics can be queried. Analytics export to CSV is
                available on plans that include <code>can_export_analytics</code>.
            </p>
            <p>
                Static QR codes are <strong>not</strong> tracked by f29 — scans of static QR codes happen
                entirely between the scanner and the encoded payload.
            </p>
        </section>

        <section id="plans-and-entitlements">
            <h2>Plans and Entitlements</h2>
            <p>Your plan controls limits and feature access, including:</p>
            <ul>
                <li>Maximum number of active QR codes</li>
                <li>Whether you can pick a custom short slug</li>
                <li>Whether you can edit a QR code's destination after creating it</li>
                <li>PNG and SVG export</li>
                <li>Analytics retention and CSV export</li>
                <li>Custom colors, transparent background, and module styles</li>
                <li>Logo upload for dynamic QR codes</li>
                <li>Maximum PNG download size</li>
            </ul>
            <p><a href="/pricing">View current plans and pricing</a> for the current per-plan values.</p>
        </section>

        <section id="account-settings">
            <h2>Account Settings</h2>
            <p>Manage your account at <a href="/account/settings">Account Settings</a>.</p>
            <ul>
                <li><strong>Profile</strong> — display name and other profile fields.</li>
                <li><strong>Email</strong> — change the email address on the account. Requires your current password and a re-verification step on the new address.</li>
                <li><strong>Password</strong> — change your password. Requires your current password.</li>
                <li><strong>Email verification</strong> — new accounts and email changes send a verification link; some features stay limited until the address is verified.</li>
                <li><strong>Password reset</strong> — if you're locked out, use <a href="/forgot-password">Forgot password</a> to receive a one-time reset link.</li>
                <li><strong>Remember me for 30 days</strong> — the login page includes an optional <em>"Remember me for 30 days"</em> checkbox. When selected, f29.us can restore your login after your browser session ends. Logging out clears the remembered login for that browser; logging out on one device does not sign you out elsewhere. If you're using a shared computer, leave the checkbox unchecked.</li>
                <li><strong>Security</strong> — review session and security-relevant settings on <a href="/account/security">Account Security</a>.</li>
            </ul>
        </section>

        <section id="billing-and-subscriptions">
            <h2>Billing and Subscriptions</h2>
            <p>
                Paid subscriptions are processed through <strong>Stripe Checkout</strong> when Stripe is
                enabled on the deployment. The price you see during checkout is the price you pay.
            </p>
            <p>
                After a successful checkout, your subscription becomes active once f29 receives the
                Stripe webhook confirming payment. The browser success page alone does not activate
                paid access — the webhook is the source of truth. This usually takes a few seconds.
            </p>
            <p>
                You can cancel a Stripe subscription from <a href="/account/subscription">your
                subscription page</a>. Cancellations take effect at the end of the current billing
                period — you keep paid-plan access until that period ends.
            </p>
        </section>

        <section id="moderation-and-abuse">
            <h2>Moderation and Abuse</h2>
            <p>
                f29 maintains a blocked-domain list and may disable short links that violate the
                <a href="/acceptable-use">Acceptable Use Policy</a>. Disabled links stop redirecting and
                their public scan page shows a generic unavailable message.
            </p>
            <p>
                If you encounter a malicious or abusive f29.us short link, please report it at
                <a href="/abuse">/abuse</a>. Include the short URL and a brief description of the
                problem.
            </p>
        </section>

        <section id="security-and-privacy">
            <h2>Security and Privacy</h2>
            <ul>
                <li>Static QR codes are <strong>not stored</strong> — generating one does not write
                    anything to your account or the database.</li>
                <li>Dynamic QR scan analytics are limited to operational data needed to render the
                    scan dashboards (timestamp, device type, referrer, hashed IP context). Sensitive
                    fields are hashed or omitted.</li>
                <li>Login throttling and scan-context bookkeeping use hashed IP addresses, not
                    plaintext IPs.</li>
                <li>All authenticated POST routes are protected by CSRF tokens.</li>
            </ul>
            <p>The following policy pages provide more detail:</p>
            <ul>
                <li><a href="/terms">Terms of Service</a></li>
                <li><a href="/privacy">Privacy Policy</a></li>
                <li><a href="/acceptable-use">Acceptable Use Policy</a></li>
                <li><a href="/abuse">Report Abuse</a></li>
                <li><a href="/contact">Contact</a></li>
            </ul>
        </section>

        <section id="faq">
            <h2>Frequently Asked Questions</h2>

            <h3>Can I change a QR code after printing it?</h3>
            <p>
                Only <strong>dynamic</strong> QR codes can be changed after printing. They encode an f29
                short link whose destination can be updated at any time. Static QR codes bake the
                payload into the image and cannot be changed after download.
            </p>

            <h3>Do static QR codes collect analytics?</h3>
            <p>No. Static QR codes are not stored or tracked by f29.</p>

            <h3>Do archived QR codes count against my limit?</h3>
            <p>
                No. Archived QR codes are kept for history but do not count against your plan's
                active-QR limit. You can restore them later if capacity allows.
            </p>

            <h3>What is the difference between PNG and SVG?</h3>
            <p>
                PNG is a pixel (raster) image at a fixed size. SVG is a vector format that scales
                cleanly to any size with no quality loss. Use SVG for print artwork; use PNG for
                quick web embeds or anywhere a raster image is required.
            </p>

            <h3>Why does my subscription not update immediately after checkout?</h3>
            <p>
                Paid access activates when f29 receives the Stripe webhook confirming payment — not
                when the browser returns to the success page. This is usually a few seconds. If your
                subscription page still shows the old plan after a minute or two, refresh and check
                <a href="/account/subscription">your subscription</a>.
            </p>

            <h3>Can I use a logo in my QR code?</h3>
            <p>
                Logo upload is available on supported plans for <strong>dynamic</strong> QR codes.
                Logo support for static QR codes is not currently implemented — it requires temporary
                upload handling that is still being designed.
            </p>
        </section>

    </main>
</div>
