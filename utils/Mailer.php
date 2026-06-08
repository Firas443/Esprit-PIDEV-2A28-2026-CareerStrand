<?php

require_once __DIR__ . '/../lib/PHPMailer/PHPMailer.php';
require_once __DIR__ . '/../lib/PHPMailer/SMTP.php';
require_once __DIR__ . '/../lib/PHPMailer/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

function sendApprovalEmail(string $to, string $name, string $status, string $reason = ''): bool
{
    $mail = new PHPMailer(true);

    try {
        // Show the real SMTP error while testing.
        // After it works, you can set SMTPDebug back to 0.
        $mail->SMTPDebug = 0;
        $mail->Debugoutput = 'html';

        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;

        // CHANGE THIS PASSWORD to your Google App Password.
        $mail->Username   = 'maymiaadi2003@gmail.com';
        $mail->Password   = 'vkodtjgnheynaggj';

        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;
        $mail->CharSet    = 'UTF-8';

        $mail->setFrom('maymiaadi2003@gmail.com', 'CareerStrand');
        $mail->addAddress($to, $name);

        $safeName   = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
        $safeReason = htmlspecialchars($reason, ENT_QUOTES, 'UTF-8');

        $mail->isHTML(true);

        if ($status === 'approved') {
            $mail->Subject = 'CareerStrand recruiter account approved';
            $mail->Body = "
                <h2>Hello {$safeName},</h2>
                <p>Your recruiter account has been approved.</p>
                <p>You can now log in to CareerStrand.</p>
            ";
            $mail->AltBody = "Hello {$name},\n\nYour recruiter account has been approved. You can now log in to CareerStrand.";
        } else {
            $mail->Subject = 'CareerStrand recruiter account rejected';
            $mail->Body = "
                <h2>Hello {$safeName},</h2>
                <p>Your recruiter account request was rejected.</p>
                <p><strong>Reason:</strong> {$safeReason}</p>
            ";
            $mail->AltBody = "Hello {$name},\n\nYour recruiter account request was rejected.\nReason: {$reason}";
        }

        return $mail->send();

    } catch (Exception $e) {
        die("Mailer Error: " . $mail->ErrorInfo);
    }
}

function sendApplicationStatusEmail(string $to, string $name, string $status, string $opportunityTitle, string $note = ''): bool
{
    $mail = new PHPMailer(true);

    try {
        $mail->SMTPDebug = 0;
        $mail->Debugoutput = 'html';

        $mail->isSMTP();
        $mail->Host       = getenv('MAIL_HOST') ?: 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = getenv('MAIL_USERNAME') ?: 'maymiaadi2003@gmail.com';
        $mail->Password   = getenv('MAIL_PASSWORD') ?: 'vkodtjgnheynaggj';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = (int) (getenv('MAIL_PORT') ?: 587);
        $mail->CharSet    = 'UTF-8';

        $fromEmail = getenv('MAIL_FROM') ?: $mail->Username;
        $fromName  = getenv('MAIL_FROM_NAME') ?: 'CareerStrand';
        $mail->setFrom($fromEmail, $fromName);
        $mail->addAddress($to, $name);
        $mail->isHTML(true);

        $safeName = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
        $safeTitle = htmlspecialchars($opportunityTitle, ENT_QUOTES, 'UTF-8');
        $safeNote = htmlspecialchars($note, ENT_QUOTES, 'UTF-8');
        $statusLabel = ucfirst(strtolower(trim($status)));

        if ($status === 'accepted') {
            $mail->Subject = 'Your CareerStrand application was accepted';
            $mail->Body = "
                <h2>Hello {$safeName},</h2>
                <p>Great news. Your application for <strong>{$safeTitle}</strong> has been accepted.</p>
                <p>Your current stage is now <strong>{$statusLabel}</strong>.</p>
                " . ($safeNote !== '' ? "<p><strong>Note:</strong> {$safeNote}</p>" : "") . "
                <p>Keep an eye on your tracker for the next steps.</p>
            ";
            $mail->AltBody = "Hello {$name},\n\nYour application for {$opportunityTitle} has been accepted.\nCurrent stage: {$statusLabel}." . ($note !== '' ? "\nNote: {$note}" : '') . "\n\nKeep an eye on your tracker for the next steps.";
        } else {
            $mail->Subject = 'CareerStrand application update';
            $mail->Body = "
                <h2>Hello {$safeName},</h2>
                <p>Your application for <strong>{$safeTitle}</strong> has been updated to <strong>{$statusLabel}</strong>.</p>
                " . ($safeNote !== '' ? "<p><strong>Note:</strong> {$safeNote}</p>" : "") . "
                <p>Check your CareerStrand tracker for the latest timeline entry.</p>
            ";
            $mail->AltBody = "Hello {$name},\n\nYour application for {$opportunityTitle} has been updated to {$statusLabel}." . ($note !== '' ? "\nNote: {$note}" : '') . "\n\nCheck your CareerStrand tracker for the latest timeline entry.";
        }

        return $mail->send();
    } catch (Exception $e) {
        return false;
    }
}
?>
