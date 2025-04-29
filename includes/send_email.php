<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require __DIR__ . '/../vendor/autoload.php';

function sendEmail($to, $subject, $message) {
    $mail = new PHPMailer(true);

    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'ariesdave253@gmail.com';
        $mail->Password   = 'uvux bxxn yakk geec';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port       = 465;

        // Recipients
        $mail->setFrom('no-reply@balispa.com', 'Bali Ayurveda Spa'); // Changed to no-reply
        $mail->addAddress($to);
        // Removed addReplyTo() completely since this is for OTP

        // Content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = createEmailTemplate($subject, $message);
        $mail->AltBody = strip_tags($message);

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Message could not be sent. Mailer Error: {$mail->ErrorInfo}");
        return false;
    }
}

function createEmailTemplate($subject, $message) {
    return '
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>'.htmlspecialchars($subject).'</title>
        <style>
            body {
                font-family: "Montserrat", Arial, sans-serif;
                line-height: 1.6;
                color: #333;
                background-color: #f9f9f9;
                margin: 0;
                padding: 0;
            }
            .container {
                max-width: 600px;
                margin: 0 auto;
                padding: 20px;
                background-color: #ffffff;
            }
            .header {
                background-color: #1A3A32;
                padding: 30px;
                text-align: center;
            }
            .header h1 {
                color: #D4AF37;
                margin: 0;
                font-size: 24px;
            }
            .content {
                padding: 30px;
            }
            .code-container {
                background-color: #f5f5f5;
                border-radius: 4px;
                padding: 15px;
                margin: 20px 0;
                text-align: center;
                font-size: 24px;
                font-weight: bold;
                color: #1A3A32;
            }
            .footer {
                text-align: center;
                font-size: 12px;
                color: #777;
                margin-top: 30px;
                padding-top: 20px;
                border-top: 1px solid #eee;
            }
            .button {
                display: inline-block;
                padding: 10px 20px;
                background-color: #1A3A32;
                color: #ffffff !important;
                text-decoration: none;
                border-radius: 4px;
                margin: 15px 0;
            }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <h1>Bali Ayurveda Spa</h1>
            </div>
            <div class="content">
                <h2>'.htmlspecialchars($subject).'</h2>
                '. (strlen($message) === 6 ? 
                   '<div class="code-container">'.$message.'</div>
                    <p>This verification code will expire in 5 minutes.</p>' 
                   : '<p>'.$message.'</p>') .'
                
                <p><strong>Please do not reply to this email</strong> as it is automatically generated for your security.</p>
                <p>Warm regards,<br>The Bali Ayurveda Spa Team</p>
            </div>
            <div class="footer">
                <p>&copy; '.date('Y').' Bali Ayurveda Spa. All rights reserved.</p>
            </div>
        </div>
    </body>
    </html>
    ';
}
?>