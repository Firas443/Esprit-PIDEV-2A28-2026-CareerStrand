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
?>
