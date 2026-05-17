<?php
declare(strict_types=1);

class NotificationService
{
    // ── Subscription: request submitted ──────────────────────────────────────

    public static function subscriptionRequestSubmitted(int $requestId): void
    {
        if (!MailerService::isEnabled()) {
            return;
        }

        try {
            $row = self::loadSubscriptionRequest($requestId);
            if (!$row) {
                return;
            }

            $userEmail   = $row['user_email'];
            $planName    = $row['requested_plan_name'];
            $appUrl      = rtrim($_ENV['APP_URL'] ?? 'https://f29.us', '/');

            $subject  = 'Plan change request received - f29.us Dynamic QR';
            $bodyHtml = self::wrap(
                "We received your request to switch to the <strong>" . htmlspecialchars($planName, ENT_QUOTES) . "</strong> plan.<br><br>"
                . "Our team will review it shortly. You will receive an email once a decision has been made.<br><br>"
                . "You can view or cancel your pending request at any time:<br>"
                . "<a href=\"{$appUrl}/account/subscription\">{$appUrl}/account/subscription</a>"
            );

            MailerService::send($userEmail, $userEmail, $subject, $bodyHtml);

            $adminAddress = trim($_ENV['MAIL_ADMIN_ADDRESS'] ?? '');
            if ($adminAddress !== '') {
                $adminSubject  = '[f29.us] New plan change request: ' . $planName;
                $adminBodyHtml = self::wrap(
                    "A new plan change request has been submitted.<br><br>"
                    . "<strong>User:</strong> " . htmlspecialchars($userEmail, ENT_QUOTES) . "<br>"
                    . "<strong>Requested plan:</strong> " . htmlspecialchars($planName, ENT_QUOTES) . "<br><br>"
                    . "<a href=\"{$appUrl}/admin/subscription-requests/{$requestId}\">Review request</a>"
                );
                MailerService::send($adminAddress, 'Admin', $adminSubject, $adminBodyHtml);
            }
        } catch (Throwable $e) {
            error_log('[NotificationService] subscriptionRequestSubmitted #' . $requestId . ': ' . $e->getMessage());
        }
    }

    // ── Subscription: request approved ───────────────────────────────────────

    public static function subscriptionRequestApproved(int $requestId): void
    {
        if (!MailerService::isEnabled()) {
            return;
        }

        try {
            $row = self::loadSubscriptionRequest($requestId);
            if (!$row) {
                return;
            }

            $userEmail = $row['user_email'];
            $planName  = $row['requested_plan_name'];
            $appUrl    = rtrim($_ENV['APP_URL'] ?? 'https://f29.us', '/');

            $subject  = 'Your plan request has been approved - f29.us Dynamic QR';
            $bodyHtml = self::wrap(
                "Good news - your request to switch to the <strong>" . htmlspecialchars($planName, ENT_QUOTES) . "</strong> plan has been approved.<br><br>"
                . "Your account has been updated and you can start using your new plan features immediately.<br><br>"
                . "<a href=\"{$appUrl}/account/subscription\">View your subscription</a>"
            );

            MailerService::send($userEmail, $userEmail, $subject, $bodyHtml);
        } catch (Throwable $e) {
            error_log('[NotificationService] subscriptionRequestApproved #' . $requestId . ': ' . $e->getMessage());
        }
    }

    // ── Subscription: request denied ─────────────────────────────────────────

    public static function subscriptionRequestDenied(int $requestId): void
    {
        if (!MailerService::isEnabled()) {
            return;
        }

        try {
            $row = self::loadSubscriptionRequest($requestId);
            if (!$row) {
                return;
            }

            $userEmail = $row['user_email'];
            $planName  = $row['requested_plan_name'];
            $appUrl    = rtrim($_ENV['APP_URL'] ?? 'https://f29.us', '/');

            $support  = self::supportEmail();
            $subject  = 'Your plan request was not approved - f29.us Dynamic QR';
            $bodyHtml = self::wrap(
                "Unfortunately, your request to switch to the <strong>" . htmlspecialchars($planName, ENT_QUOTES) . "</strong> plan was not approved at this time.<br><br>"
                . "Your current plan and subscription remain unchanged.<br><br>"
                . "If you have questions, please contact us at "
                . "<a href=\"mailto:{$support}\">{$support}</a>."
            );

            MailerService::send($userEmail, $userEmail, $subject, $bodyHtml);
        } catch (Throwable $e) {
            error_log('[NotificationService] subscriptionRequestDenied #' . $requestId . ': ' . $e->getMessage());
        }
    }

    // ── Subscription: request canceled ───────────────────────────────────────

    public static function subscriptionRequestCanceled(int $requestId, bool $byAdmin): void
    {
        if (!MailerService::isEnabled()) {
            return;
        }

        try {
            $row = self::loadSubscriptionRequest($requestId);
            if (!$row) {
                return;
            }

            $userEmail = $row['user_email'];
            $planName  = $row['requested_plan_name'];
            $appUrl    = rtrim($_ENV['APP_URL'] ?? 'https://f29.us', '/');

            $support = self::supportEmail();
            if ($byAdmin) {
                $subject  = 'Your plan request has been canceled - f29.us Dynamic QR';
                $bodyHtml = self::wrap(
                    "Your request to switch to the <strong>" . htmlspecialchars($planName, ENT_QUOTES) . "</strong> plan has been canceled by our team.<br><br>"
                    . "Your current plan and subscription remain unchanged.<br><br>"
                    . "If you have questions, please contact us at "
                    . "<a href=\"mailto:{$support}\">{$support}</a>."
                );
            } else {
                $subject  = 'Plan change request canceled - f29.us Dynamic QR';
                $bodyHtml = self::wrap(
                    "You have canceled your request to switch to the <strong>" . htmlspecialchars($planName, ENT_QUOTES) . "</strong> plan.<br><br>"
                    . "Your current plan and subscription remain unchanged.<br><br>"
                    . "<a href=\"{$appUrl}/account/subscription\">View your subscription</a>"
                );
            }

            MailerService::send($userEmail, $userEmail, $subject, $bodyHtml);
        } catch (Throwable $e) {
            error_log('[NotificationService] subscriptionRequestCanceled #' . $requestId . ': ' . $e->getMessage());
        }
    }

    // ── Subscription: Stripe billing events ─────────────────────────────────

    public static function paymentFailed(int $userSubscriptionId): void
    {
        if (!MailerService::isEnabled()) {
            return;
        }

        try {
            $row = self::loadSubscriptionUser($userSubscriptionId);
            if (!$row) {
                return;
            }

            $userEmail = $row['user_email'];
            $planName  = $row['plan_display_name'];
            $support   = self::supportEmail();

            $subject  = 'Payment failed — action required - f29.us Dynamic QR';
            $bodyHtml = self::wrap(
                "We were unable to process your payment for the <strong>" . self::e($planName) . "</strong> plan.<br><br>"
                . "Your paid features remain active for now, but your subscription may be suspended if payment is not resolved.<br><br>"
                . "Please update your payment method to continue your subscription.<br><br>"
                . "If you have questions, contact us at <a href=\"mailto:" . self::e($support) . "\">" . self::e($support) . "</a>."
            );

            MailerService::send($userEmail, $userEmail, $subject, $bodyHtml);
        } catch (Throwable $e) {
            error_log('[NotificationService] paymentFailed sub #' . $userSubscriptionId . ': ' . $e->getMessage());
        }
    }

    public static function subscriptionCancellationScheduled(int $userSubscriptionId): void
    {
        if (!MailerService::isEnabled()) {
            return;
        }

        try {
            $row = self::loadSubscriptionUser($userSubscriptionId);
            if (!$row) {
                return;
            }

            $userEmail = $row['user_email'];
            $planName  = $row['plan_display_name'];
            $periodEnd = $row['current_period_end'];
            $appUrl    = rtrim($_ENV['APP_URL'] ?? 'https://f29.us', '/');
            $appUrlE   = self::e($appUrl);

            $endFmt = $periodEnd ? date('F j, Y', strtotime($periodEnd)) : 'the end of your billing period';

            $subject  = 'Your subscription has been scheduled to cancel - f29.us Dynamic QR';
            $bodyHtml = self::wrap(
                "Your <strong>" . self::e($planName) . "</strong> subscription has been scheduled to cancel.<br><br>"
                . "You will retain full access to your paid features until <strong>" . self::e($endFmt) . "</strong>. "
                . "After that, your account will revert to the Free plan.<br><br>"
                . "If you change your mind, you can resubscribe at any time:<br>"
                . "<a href=\"{$appUrlE}/account/subscription\">{$appUrlE}/account/subscription</a>"
            );

            MailerService::send($userEmail, $userEmail, $subject, $bodyHtml);
        } catch (Throwable $e) {
            error_log('[NotificationService] subscriptionCancellationScheduled sub #' . $userSubscriptionId . ': ' . $e->getMessage());
        }
    }

    public static function subscriptionCanceled(int $userSubscriptionId): void
    {
        if (!MailerService::isEnabled()) {
            return;
        }

        try {
            $row = self::loadSubscriptionUser($userSubscriptionId);
            if (!$row) {
                return;
            }

            $userEmail = $row['user_email'];
            $planName  = $row['plan_display_name'];
            $appUrl    = rtrim($_ENV['APP_URL'] ?? 'https://f29.us', '/');
            $appUrlE   = self::e($appUrl);

            $subject  = 'Your subscription has ended - f29.us Dynamic QR';
            $bodyHtml = self::wrap(
                "Your <strong>" . self::e($planName) . "</strong> subscription has ended and your account has been moved to the Free plan.<br><br>"
                . "You can resubscribe at any time:<br>"
                . "<a href=\"{$appUrlE}/account/subscription\">{$appUrlE}/account/subscription</a>"
            );

            MailerService::send($userEmail, $userEmail, $subject, $bodyHtml);
        } catch (Throwable $e) {
            error_log('[NotificationService] subscriptionCanceled sub #' . $userSubscriptionId . ': ' . $e->getMessage());
        }
    }

    // ── Account: email address changed ───────────────────────────────────────

    public static function accountEmailChanged(int $userId, string $oldEmail, string $newEmail): void
    {
        if (!MailerService::isEnabled()) {
            return;
        }

        try {
            $support = self::supportEmail();
            $subject = 'Your email address was changed - f29.us Dynamic QR';

            $bodyOld = self::wrap(
                "The email address on your f29.us Dynamic QR account has been changed.<br><br>"
                . "<strong>Old address:</strong> " . htmlspecialchars($oldEmail, ENT_QUOTES) . "<br>"
                . "<strong>New address:</strong> " . htmlspecialchars($newEmail, ENT_QUOTES) . "<br><br>"
                . "If you did not make this change, contact us immediately at "
                . "<a href=\"mailto:{$support}\">{$support}</a>."
            );
            MailerService::send($oldEmail, $oldEmail, $subject, $bodyOld);

            $bodyNew = self::wrap(
                "Your f29.us Dynamic QR account email address has been updated to this address.<br><br>"
                . "Future account notifications will be sent here.<br><br>"
                . "If you did not make this change, contact us immediately at "
                . "<a href=\"mailto:{$support}\">{$support}</a>."
            );
            MailerService::send($newEmail, $newEmail, $subject, $bodyNew);
        } catch (Throwable $e) {
            error_log('[NotificationService] accountEmailChanged user #' . $userId . ': ' . $e->getMessage());
        }
    }

    // ── Account: password changed ─────────────────────────────────────────────

    public static function accountPasswordChanged(int $userId): void
    {
        if (!MailerService::isEnabled()) {
            return;
        }

        try {
            $stmt = Database::get()->prepare("SELECT email FROM users WHERE id = ? LIMIT 1");
            $stmt->execute([$userId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) {
                return;
            }

            $email   = $row['email'];
            $support = self::supportEmail();

            $subject  = 'Your password was changed - f29.us Dynamic QR';
            $bodyHtml = self::wrap(
                "The password for your f29.us Dynamic QR account has been changed.<br><br>"
                . "If you did not make this change, contact us immediately at "
                . "<a href=\"mailto:{$support}\">{$support}</a>."
            );

            MailerService::send($email, $email, $subject, $bodyHtml);
        } catch (Throwable $e) {
            error_log('[NotificationService] accountPasswordChanged user #' . $userId . ': ' . $e->getMessage());
        }
    }

    // ── Moderation: link disabled ─────────────────────────────────────────────

    public static function linkDisabled(int $shortLinkId): void
    {
        if (!MailerService::isEnabled()) {
            return;
        }

        try {
            $row = self::loadShortLinkOwner($shortLinkId);
            if (!$row || empty($row['owner_email'])) {
                return;
            }

            $email   = $row['owner_email'];
            $slug    = $row['slug'];
            $reason  = $row['disabled_reason'] ?? '';
            $appUrl  = rtrim($_ENV['APP_URL'] ?? 'https://f29.us', '/');
            $support = self::supportEmail();

            $subject  = 'A link on your account has been disabled - f29.us Dynamic QR';
            $bodyHtml = self::wrap(
                "The following link on your f29.us Dynamic QR account has been disabled by our team:<br><br>"
                . "<strong>Short link:</strong> {$appUrl}/" . htmlspecialchars($slug, ENT_QUOTES) . "<br>"
                . ($reason !== '' ? "<strong>Reason:</strong> " . htmlspecialchars($reason, ENT_QUOTES) . "<br>" : '')
                . "<br>Disabled links will not redirect visitors.<br><br>"
                . "If you believe this was done in error, contact us at "
                . "<a href=\"mailto:{$support}\">{$support}</a>."
            );

            MailerService::send($email, $email, $subject, $bodyHtml);
        } catch (Throwable $e) {
            error_log('[NotificationService] linkDisabled #' . $shortLinkId . ': ' . $e->getMessage());
        }
    }

    // ── Moderation: link restored ─────────────────────────────────────────────

    public static function linkRestored(int $shortLinkId): void
    {
        if (!MailerService::isEnabled()) {
            return;
        }

        try {
            $row = self::loadShortLinkOwner($shortLinkId);
            if (!$row || empty($row['owner_email'])) {
                return;
            }

            $email  = $row['owner_email'];
            $slug   = $row['slug'];
            $appUrl = rtrim($_ENV['APP_URL'] ?? 'https://f29.us', '/');

            $subject  = 'Your link has been restored - f29.us Dynamic QR';
            $bodyHtml = self::wrap(
                "The following link on your f29.us Dynamic QR account has been restored to active:<br><br>"
                . "<strong>Short link:</strong> {$appUrl}/" . htmlspecialchars($slug, ENT_QUOTES) . "<br><br>"
                . "The link is now live and will redirect visitors normally.<br><br>"
                . "<a href=\"{$appUrl}/qr\">Manage your QR codes</a>"
            );

            MailerService::send($email, $email, $subject, $bodyHtml);
        } catch (Throwable $e) {
            error_log('[NotificationService] linkRestored #' . $shortLinkId . ': ' . $e->getMessage());
        }
    }

    // ── Email verification: registration ─────────────────────────────────────

    public static function registrationVerification(string $email, string $rawToken): void
    {
        if (!MailerService::isEnabled()) {
            return;
        }

        try {
            $appUrl  = rtrim($_ENV['APP_URL'] ?? 'https://f29.us', '/');
            $link    = $appUrl . '/verify-email?token=' . urlencode($rawToken);
            $linkE   = self::e($link);
            $subject = 'Please verify your email address - f29.us Dynamic QR';

            $bodyHtml = self::wrap(
                "Thanks for creating an account. Please verify your email address by clicking the link below.<br><br>"
                . "<a href=\"{$linkE}\" style=\"display:inline-block;padding:0.5rem 1.2rem;background:#1a1a2e;color:#fff;text-decoration:none;border-radius:4px\">Verify Email Address</a><br><br>"
                . "Or copy and paste this URL into your browser:<br>"
                . "<a href=\"{$linkE}\">{$linkE}</a><br><br>"
                . "This link expires in 24 hours. If you did not create an account, you can safely ignore this email."
            );

            MailerService::send($email, $email, $subject, $bodyHtml);
        } catch (Throwable $e) {
            error_log('[NotificationService] registrationVerification to ' . $email . ': ' . $e->getMessage());
        }
    }

    // ── Email verification: email change ─────────────────────────────────────

    public static function emailChangeVerification(string $newEmail, string $rawToken): void
    {
        if (!MailerService::isEnabled()) {
            return;
        }

        try {
            $appUrl  = rtrim($_ENV['APP_URL'] ?? 'https://f29.us', '/');
            $link    = $appUrl . '/verify-email?token=' . urlencode($rawToken);
            $linkE   = self::e($link);
            $subject = 'Confirm your new email address - f29.us Dynamic QR';

            $bodyHtml = self::wrap(
                "We received a request to change your f29.us Dynamic QR account email address to this address.<br><br>"
                . "Click the link below to confirm this email address and complete the change:<br><br>"
                . "<a href=\"{$linkE}\" style=\"display:inline-block;padding:0.5rem 1.2rem;background:#1a1a2e;color:#fff;text-decoration:none;border-radius:4px\">Confirm New Email Address</a><br><br>"
                . "Or copy and paste this URL into your browser:<br>"
                . "<a href=\"{$linkE}\">{$linkE}</a><br><br>"
                . "This link expires in 24 hours. If you did not request this change, you can safely ignore this email."
            );

            MailerService::send($newEmail, $newEmail, $subject, $bodyHtml);
        } catch (Throwable $e) {
            error_log('[NotificationService] emailChangeVerification to ' . $newEmail . ': ' . $e->getMessage());
        }
    }

    public static function emailChangeSecurityNotice(string $oldEmail, string $newEmail): void
    {
        if (!MailerService::isEnabled()) {
            return;
        }

        try {
            $support  = self::supportEmail();
            $supportE = self::e($support);
            $subject  = 'Email change requested on your account - f29.us Dynamic QR';

            $bodyHtml = self::wrap(
                "A request was made to change the email address on your f29.us Dynamic QR account.<br><br>"
                . "<strong>Requested new address:</strong> " . self::e($newEmail) . "<br><br>"
                . "The change will only take effect once confirmed at the new address. Your account continues to use this address until then.<br><br>"
                . "If you did not request this change, contact us immediately at "
                . "<a href=\"mailto:{$supportE}\">{$supportE}</a>."
            );

            MailerService::send($oldEmail, $oldEmail, $subject, $bodyHtml);
        } catch (Throwable $e) {
            error_log('[NotificationService] emailChangeSecurityNotice to ' . $oldEmail . ': ' . $e->getMessage());
        }
    }

    public static function emailChangeCompleted(string $oldEmail, string $newEmail): void
    {
        if (!MailerService::isEnabled()) {
            return;
        }

        try {
            $support  = self::supportEmail();
            $supportE = self::e($support);
            $subject  = 'Your email address has been updated - f29.us Dynamic QR';

            $bodyOld = self::wrap(
                "The email address on your f29.us Dynamic QR account has been changed.<br><br>"
                . "<strong>Old address:</strong> " . self::e($oldEmail) . "<br>"
                . "<strong>New address:</strong> " . self::e($newEmail) . "<br><br>"
                . "If you did not make this change, contact us immediately at "
                . "<a href=\"mailto:{$supportE}\">{$supportE}</a>."
            );
            MailerService::send($oldEmail, $oldEmail, $subject, $bodyOld);

            $bodyNew = self::wrap(
                "Your f29.us Dynamic QR account email address has been updated to this address.<br><br>"
                . "Future account notifications will be sent here.<br><br>"
                . "If you did not make this change, contact us immediately at "
                . "<a href=\"mailto:{$supportE}\">{$supportE}</a>."
            );
            MailerService::send($newEmail, $newEmail, $subject, $bodyNew);
        } catch (Throwable $e) {
            error_log('[NotificationService] emailChangeCompleted old=' . $oldEmail . ' new=' . $newEmail . ': ' . $e->getMessage());
        }
    }

    // ── Password reset: reset requested ──────────────────────────────────────

    public static function passwordResetRequested(int $userId, string $rawToken): void
    {
        if (!MailerService::isEnabled()) {
            return;
        }

        try {
            $stmt = Database::get()->prepare("SELECT email FROM users WHERE id = ? LIMIT 1");
            $stmt->execute([$userId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) {
                return;
            }

            $email    = $row['email'];
            $appUrl   = rtrim($_ENV['APP_URL'] ?? 'https://f29.us', '/');
            $link     = $appUrl . '/reset-password?token=' . urlencode($rawToken);
            $linkE    = self::e($link);
            $support  = self::supportEmail();
            $supportE = self::e($support);

            $subject  = 'Reset your password - f29.us Dynamic QR';
            $bodyHtml = self::wrap(
                "We received a request to reset the password for your f29.us Dynamic QR account.<br><br>"
                . "Click the link below to set a new password. This link expires in 60 minutes.<br><br>"
                . "<a href=\"{$linkE}\" style=\"display:inline-block;padding:0.5rem 1.2rem;background:#1a1a2e;color:#fff;text-decoration:none;border-radius:4px\">Reset Password</a><br><br>"
                . "Or copy and paste this URL into your browser:<br>"
                . "<a href=\"{$linkE}\">{$linkE}</a><br><br>"
                . "If you did not request a password reset, you can safely ignore this email - your password has not been changed.<br><br>"
                . "If you have concerns, contact us at <a href=\"mailto:{$supportE}\">{$supportE}</a>."
            );

            MailerService::send($email, $email, $subject, $bodyHtml);
        } catch (Throwable $e) {
            error_log('[NotificationService] passwordResetRequested user #' . $userId . ': ' . $e->getMessage());
        }
    }

    // ── Password reset: reset completed ───────────────────────────────────────

    public static function passwordResetCompleted(int $userId): void
    {
        if (!MailerService::isEnabled()) {
            return;
        }

        try {
            $stmt = Database::get()->prepare("SELECT email FROM users WHERE id = ? LIMIT 1");
            $stmt->execute([$userId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) {
                return;
            }

            $email    = $row['email'];
            $support  = self::supportEmail();
            $supportE = self::e($support);

            $subject  = 'Your password was reset - f29.us Dynamic QR';
            $bodyHtml = self::wrap(
                "The password for your f29.us Dynamic QR account has been reset.<br><br>"
                . "If you made this change, no further action is needed.<br><br>"
                . "If you did not reset your password, contact us immediately at "
                . "<a href=\"mailto:{$supportE}\">{$supportE}</a>."
            );

            MailerService::send($email, $email, $subject, $bodyHtml);
        } catch (Throwable $e) {
            error_log('[NotificationService] passwordResetCompleted user #' . $userId . ': ' . $e->getMessage());
        }
    }

    // ── Contact form: message submitted ──────────────────────────────────────

    /**
     * Send a notification to the support inbox when a user submits the public
     * contact form. No-op when mail is disabled. Falls back gracefully if the
     * mailer fails — the contact_messages row is already persisted.
     */
    public static function contactMessageSubmitted(int $contactMessageId): void
    {
        if (!MailerService::isEnabled()) {
            return;
        }

        try {
            $stmt = Database::get()->prepare("
                SELECT id, user_id, name, email, category, subject, message,
                       user_agent, ip_hash, created_at
                FROM contact_messages
                WHERE id = ?
                LIMIT 1
            ");
            $stmt->execute([$contactMessageId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) {
                return;
            }

            $supportAddress = $_ENV['MAIL_SUPPORT_ADDRESS']
                ?? $_ENV['SUPPORT_EMAIL']
                ?? 'support@f29.us';

            $categoryLabels = [
                'general'   => 'General question',
                'billing'   => 'Billing / subscription',
                'technical' => 'Technical support',
                'account'   => 'Account access',
                'problem'   => 'Report a problem',
                'other'     => 'Other',
            ];
            $categoryLabel = $categoryLabels[$row['category']] ?? (string) $row['category'];

            $appUrl  = rtrim($_ENV['APP_URL'] ?? 'https://f29.us', '/');
            $appUrlE = self::e($appUrl);

            $subject = '[f29.us Contact] ' . $categoryLabel . ': ' . $row['subject'];

            $userLine = $row['user_id'] !== null
                ? '<strong>User ID:</strong> ' . (int) $row['user_id'] . '<br>'
                : '<strong>User ID:</strong> (not logged in)<br>';

            $messageBody = nl2br(self::e((string) $row['message']));

            $bodyHtml = self::wrap(
                "A new message was submitted through the contact form.<br><br>"
                . '<strong>Message ID:</strong> ' . (int) $row['id'] . '<br>'
                . '<strong>Name:</strong> ' . self::e((string) $row['name']) . '<br>'
                . '<strong>Email:</strong> ' . self::e((string) $row['email']) . '<br>'
                . $userLine
                . '<strong>Category:</strong> ' . self::e($categoryLabel) . '<br>'
                . '<strong>Subject:</strong> ' . self::e((string) $row['subject']) . '<br>'
                . '<strong>Submitted at:</strong> ' . self::e((string) $row['created_at']) . ' UTC<br>'
                . '<strong>User agent:</strong> ' . self::e((string) ($row['user_agent'] ?? '(not provided)')) . '<br>'
                . '<strong>IP hash:</strong> ' . self::e((string) ($row['ip_hash'] ?? '(not provided)')) . '<br><br>'
                . '<strong>Message:</strong><br>' . $messageBody . '<br><br>'
                . '<a href="' . $appUrlE . '/admin/contact-messages/' . (int) $row['id'] . '">Review in admin</a>'
            );

            MailerService::send($supportAddress, 'Support', $subject, $bodyHtml);
        } catch (Throwable $e) {
            error_log('[NotificationService] contactMessageSubmitted #' . $contactMessageId . ': ' . $e->getMessage());
        }
    }

    // ── Abuse report: submitted ──────────────────────────────────────────────

    /**
     * Notify the abuse inbox when a user submits the public abuse-report form.
     * Operates on the same `contact_messages` row that `contactMessageSubmitted`
     * uses, but routes to `ABUSE_EMAIL` (fallback `abuse@f29.us`) and labels
     * the subject as an abuse report.
     */
    public static function abuseReportSubmitted(int $contactMessageId): void
    {
        if (!MailerService::isEnabled()) {
            return;
        }

        try {
            $stmt = Database::get()->prepare("
                SELECT id, user_id, name, email, category, subject, message,
                       user_agent, ip_hash, created_at
                FROM contact_messages
                WHERE id = ?
                LIMIT 1
            ");
            $stmt->execute([$contactMessageId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) {
                return;
            }

            $abuseAddress = $_ENV['ABUSE_EMAIL'] ?? 'abuse@f29.us';

            $appUrl  = rtrim($_ENV['APP_URL'] ?? 'https://f29.us', '/');
            $appUrlE = self::e($appUrl);

            // "Abuse report: Phishing or credential theft" → strip the duplicate
            // "Abuse report:" prefix so the email subject reads cleanly.
            $rowSubject  = (string) $row['subject'];
            $typeLabel   = preg_replace('/^Abuse report:\s*/i', '', $rowSubject);
            $mailSubject = '[f29.us Abuse Report] ' . ($typeLabel !== '' ? $typeLabel : $rowSubject);

            $userLine = $row['user_id'] !== null
                ? '<strong>User ID:</strong> ' . (int) $row['user_id'] . '<br>'
                : '<strong>User ID:</strong> (not logged in)<br>';

            $messageBody = nl2br(self::e((string) $row['message']));

            $bodyHtml = self::wrap(
                "A new <strong>abuse report</strong> was submitted through the public form.<br><br>"
                . '<strong>Message ID:</strong> ' . (int) $row['id'] . '<br>'
                . '<strong>Name:</strong> ' . self::e((string) $row['name']) . '<br>'
                . '<strong>Email:</strong> ' . self::e((string) $row['email']) . '<br>'
                . $userLine
                . '<strong>Subject:</strong> ' . self::e($rowSubject) . '<br>'
                . '<strong>Submitted at:</strong> ' . self::e((string) $row['created_at']) . ' UTC<br>'
                . '<strong>User agent:</strong> ' . self::e((string) ($row['user_agent'] ?? '(not provided)')) . '<br>'
                . '<strong>IP hash:</strong> ' . self::e((string) ($row['ip_hash'] ?? '(not provided)')) . '<br><br>'
                . '<strong>Report:</strong><br>' . $messageBody . '<br><br>'
                . '<a href="' . $appUrlE . '/admin/contact-messages/' . (int) $row['id'] . '">Review in admin</a>'
            );

            MailerService::send($abuseAddress, 'Abuse', $mailSubject, $bodyHtml);
        } catch (Throwable $e) {
            error_log('[NotificationService] abuseReportSubmitted #' . $contactMessageId . ': ' . $e->getMessage());
        }
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    private static function loadSubscriptionUser(int $userSubscriptionId): array|false
    {
        $stmt = Database::get()->prepare("
            SELECT us.id, us.user_id, us.current_period_end, us.billing_status,
                   u.email         AS user_email,
                   p.display_name  AS plan_display_name
              FROM user_subscriptions us
              JOIN users u ON u.id  = us.user_id
              JOIN plans p ON p.id  = us.plan_id
             WHERE us.id = ?
             LIMIT 1
        ");
        $stmt->execute([$userSubscriptionId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    private static function loadSubscriptionRequest(int $requestId): array|false
    {
        $stmt = Database::get()->prepare("
            SELECT scr.id, scr.user_id,
                   u.email  AS user_email,
                   rp.display_name AS requested_plan_name
            FROM   subscription_change_requests scr
            JOIN   users u  ON u.id  = scr.user_id
            JOIN   plans rp ON rp.id = scr.requested_plan_id
            WHERE  scr.id = ?
            LIMIT  1
        ");
        $stmt->execute([$requestId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    private static function loadShortLinkOwner(int $shortLinkId): array|false
    {
        $stmt = Database::get()->prepare("
            SELECT sl.id, sl.slug, sl.disabled_reason,
                   u.email AS owner_email
            FROM   short_links sl
            LEFT JOIN users u ON u.id = sl.user_id
            WHERE  sl.id = ?
            LIMIT  1
        ");
        $stmt->execute([$shortLinkId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    private static function supportEmail(): string
    {
        return $_ENV['MAIL_SUPPORT_ADDRESS'] ?? $_ENV['SUPPORT_EMAIL'] ?? 'support@f29.us';
    }

    private static function e(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }

    private static function wrap(string $content): string
    {
        $appName = htmlspecialchars($_ENV['APP_NAME'] ?? 'f29.us Dynamic QR', ENT_QUOTES);
        return <<<HTML
<!DOCTYPE html>
<html>
<body style="font-family:sans-serif;color:#1a1a2e;max-width:560px;margin:0 auto;padding:1.5rem">
<p style="margin-top:0">{$content}</p>
<hr style="border:none;border-top:1px solid #e5e7eb;margin:2rem 0 1rem">
<p style="font-size:0.82rem;color:#6b7280;margin:0">{$appName}</p>
</body>
</html>
HTML;
    }
}
