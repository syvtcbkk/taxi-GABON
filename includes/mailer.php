<?php
// includes/mailer.php — Envoi d'e-mails via PHPMailer (SMTP)
// Installez via : composer require phpmailer/phpmailer

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../vendor/autoload.php';

function sendMail(string $toEmail, string $toName, string $subject, string $htmlBody): bool
{
    $mail = new PHPMailer(true);
    try {
        // ── SMTP (adaptez avec votre fournisseur : Gmail, Brevo, Mailgun…) ──
        $mail->isSMTP();
        $mail->Host       = $_ENV['MAIL_HOST']     ?? 'danaiyannickneil@gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = $_ENV['MAIL_USER']     ?? '';
        $mail->Password   = $_ENV['MAIL_PASS']     ?? '';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = (int)($_ENV['MAIL_PORT'] ?? 587);
        $mail->CharSet    = 'UTF-8';

        $mail->setFrom(
            $_ENV['MAIL_FROM']      ?? 'noreply@taxigabon.ga',
            $_ENV['MAIL_FROM_NAME'] ?? 'Taxi Gabon'
        );
        $mail->addAddress($toEmail, $toName);

        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $htmlBody;
        $mail->AltBody = strip_tags($htmlBody);

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log('Mailer Error: ' . $mail->ErrorInfo);
        return false;
    }
}

// ── Templates ────────────────────────────────────────────────────────────────

function emailVerificationTemplate(string $name, string $link): string
{
    return <<<HTML
    <div style="font-family:Arial,sans-serif;max-width:600px;margin:auto;padding:30px;border:1px solid #eee;border-radius:12px;">
        <h2 style="color:#1a1a2e;text-align:center;">🚕 Taxi Gabon</h2>
        <p style="font-size:16px;">Bonjour <strong>{$name}</strong>,</p>
        <p>Merci de vous être inscrit ! Cliquez sur le bouton ci-dessous pour vérifier votre adresse e-mail et activer votre compte.</p>
        <div style="text-align:center;margin:30px 0;">
            <a href="{$link}" style="background:#ffd700;color:#1a1a2e;padding:14px 32px;border-radius:50px;text-decoration:none;font-weight:bold;font-size:16px;">
                ✅ Vérifier mon e-mail
            </a>
        </div>
        <p style="color:#888;font-size:13px;">Ce lien expire dans 24 heures. Si vous n'avez pas créé de compte, ignorez cet e-mail.</p>
    </div>
    HTML;
}

function passwordResetTemplate(string $name, string $code): string
{
    return <<<HTML
    <div style="font-family:Arial,sans-serif;max-width:600px;margin:auto;padding:30px;border:1px solid #eee;border-radius:12px;">
        <h2 style="color:#1a1a2e;text-align:center;">🚕 Taxi Gabon</h2>
        <p style="font-size:16px;">Bonjour <strong>{$name}</strong>,</p>
        <p>Vous avez demandé à réinitialiser votre mot de passe. Voici votre code de récupération :</p>
        <div style="text-align:center;margin:30px 0;">
            <span style="background:#f8f9fa;border:2px dashed #ffd700;padding:16px 40px;border-radius:12px;font-size:36px;font-weight:bold;letter-spacing:12px;color:#1a1a2e;">
                {$code}
            </span>
        </div>
        <p style="text-align:center;color:#888;">Ce code expire dans <strong>15 minutes</strong>.</p>
        <p style="color:#888;font-size:13px;">Si vous n'avez pas demandé de réinitialisation, ignorez cet e-mail. Votre mot de passe restera inchangé.</p>
    </div>
    HTML;
}
