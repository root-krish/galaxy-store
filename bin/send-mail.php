<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit;
require __DIR__ . '/../app/bootstrap.php';
require __DIR__ . '/../vendor/autoload.php';
if (!getenv('SG_SMTP_HOST') || !getenv('SG_MAIL_FROM')) exit("Configure SG_SMTP_HOST and SG_MAIL_FROM first.\n");
$count = store()->sendQueuedMail(static function (string $to, string $subject, string $body): void {
    $mail = new PHPMailer\PHPMailer\PHPMailer(true); $mail->isSMTP();
    $mail->Host = getenv('SG_SMTP_HOST'); $mail->Port = (int) (getenv('SG_SMTP_PORT') ?: 587);
    $mail->SMTPAuth = (bool) getenv('SG_SMTP_USER'); $mail->Username = getenv('SG_SMTP_USER') ?: ''; $mail->Password = getenv('SG_SMTP_PASSWORD') ?: '';
    $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Timeout = 10; $mail->CharSet = 'UTF-8';
    $mail->setFrom(getenv('SG_MAIL_FROM'), shop_name()); $mail->addAddress($to);
    $mail->Subject = $subject; $mail->Body = $body; $mail->send();
});
echo "Sent $count queued messages.\n";
