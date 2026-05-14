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

            $subject  = 'Plan change request received — f29.us Dynamic QR';
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

            $subject  = 'Your plan request has been approved — f29.us Dynamic QR';
            $bodyHtml = self::wrap(
                "Good news — your request to switch to the <strong>" . htmlspecialchars($planName, ENT_QUOTES) . "</strong> plan has been approved.<br><br>"
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
            $subject  = 'Your plan request was not approved — f29.us Dynamic QR';
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
                $subject  = 'Your plan request has been canceled — f29.us Dynamic QR';
                $bodyHtml = self::wrap(
                    "Your request to switch to the <strong>" . htmlspecialchars($planName, ENT_QUOTES) . "</strong> plan has been canceled by our team.<br><br>"
                    . "Your current plan and subscription remain unchanged.<br><br>"
                    . "If you have questions, please contact us at "
                    . "<a href=\"mailto:{$support}\">{$support}</a>."
                );
            } else {
                $subject  = 'Plan change request canceled — f29.us Dynamic QR';
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

    // ── Account: email address changed ───────────────────────────────────────

    public static function accountEmailChanged(int $userId, string $oldEmail, string $newEmail): void
    {
        if (!MailerService::isEnabled()) {
            return;
        }

        try {
            $support = self::supportEmail();
            $subject = 'Your email address was changed — f29.us Dynamic QR';

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

            $subject  = 'Your password was changed — f29.us Dynamic QR';
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

            $subject  = 'A link on your account has been disabled — f29.us Dynamic QR';
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

            $subject  = 'Your link has been restored — f29.us Dynamic QR';
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

    // ── Private helpers ───────────────────────────────────────────────────────

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
