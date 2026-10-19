<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'C:/inetpub/wwwroot/it_request/libs/PHPMailer/src/Exception.php';
require 'C:/inetpub/wwwroot/it_request/libs/PHPMailer/src/PHPMailer.php';
require 'C:/inetpub/wwwroot/it_request/libs/PHPMailer/src/SMTP.php';

$mail = new PHPMailer(true);

try {
    //Server settings
    $mail->SMTPDebug = 2;                                       // Enable verbose debug output
    $mail->isSMTP();                                            // Set mailer to use SMTP
    $mail->Host       = 'smtp.gmail.com';                       // Specify main and backup SMTP servers
    $mail->SMTPAuth   = true;                                   // Enable SMTP authentication
    $mail->Username = "it.kruger.th@gmail.com";
    $mail->Password = "omhjnmumurxzzigk";
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;         // Enable TLS encryption, `PHPMailer::ENCRYPTION_SMTPS` also accepted
    $mail->Port       = 587;                                    // TCP port to connect to
    $mail->CharSet = "utf-8";

    //Recipients
    $mail->setFrom('it.kruger.th@gmail.com', 'IT Kruger Auto Message');
    $mail->addAddress('paitoon.panjohn@krugerfan.com', 'Revicever');           // Add a recipient
    // Content
   
    $mail->Subject = 'Here is the subject';
    $mail->Body    = 'This is the HTML message body <b>in bold!</b>';
    $mail->AltBody = 'This is the body in plain text for non-HTML mail clients';

    $mail->send();
    echo 'Message has been sent';
} catch (Exception $e) {
    echo "Message could not be sent. Mailer Error: {$mail->ErrorInfo}";
}
?>