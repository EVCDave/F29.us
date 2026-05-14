<?php
declare(strict_types=1);

class MailerService
{
    public static function isEnabled(): bool
    {
        return filter_var($_ENV['MAIL_ENABLED'] ?? 'false', FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Send a transactional email. Returns true on success, false on any failure.
     * Never throws — all exceptions are caught and logged.
     */
    public static function send(
        string $toAddress,
        string $toName,
        string $subject,
        string $bodyHtml,
        string $bodyText = ''
    ): bool {
        if (!self::isEnabled()) {
            return false;
        }

        try {
            require_once ROOT_PATH . '/vendor/PHPMailer/Exception.php';
            require_once ROOT_PATH . '/vendor/PHPMailer/PHPMailer.php';
            require_once ROOT_PATH . '/vendor/PHPMailer/SMTP.php';
        } catch (Throwable $e) {
            error_log('[MailerService] PHPMailer load failed: ' . $e->getMessage());
            return false;
        }

        try {
            $mail = new PHPMailer\PHPMailer\PHPMailer(true);
            $mail->isSMTP();
            $mail->Host     = $_ENV['MAIL_SMTP_HOST']     ?? '';
            $mail->Port     = (int) ($_ENV['MAIL_SMTP_PORT'] ?? 587);
            $mail->SMTPAuth = true;
            $mail->Username = $_ENV['MAIL_SMTP_USERNAME'] ?? '';
            $mail->Password = $_ENV['MAIL_SMTP_PASSWORD'] ?? '';

            $enc = strtolower($_ENV['MAIL_SMTP_ENCRYPTION'] ?? 'tls');
            $mail->SMTPSecure = $enc === 'ssl'
                ? PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS
                : PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;

            $fromAddress = $_ENV['MAIL_FROM_ADDRESS'] ?? 'noreply@example.com';
            $fromName    = $_ENV['MAIL_FROM_NAME']    ?? 'f29.us Dynamic QR';

            $mail->setFrom($fromAddress, $fromName);
            $mail->addAddress($toAddress, $toName);

            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body    = $bodyHtml;
            $mail->AltBody = $bodyText !== '' ? $bodyText : strip_tags($bodyHtml);

            $mail->send();
            return true;
        } catch (Throwable $e) {
            error_log('[MailerService] Send failed to ' . $toAddress . ': ' . $e->getMessage());
            return false;
        }
    }
}
