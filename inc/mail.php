<?php

// Import classes
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

function mailer() {
    $mail = new PHPMailer();
    $mail->IsSMTP();
    $mail->SMTPAuth = true;
    $mail->Host = SMTP_HOST;
    $mail->Port = SMTP_PORT;
    $mail->Username = SMTP_USER;
    $mail->Password = SMTP_PASS;
    $mail->CharSet = "UTF-8";
    $mail->SMTPDebug = 3;
    $mail->Debugoutput = function($str, $level) {
        error_log("Mailer Debug (Level: $level) \n\n $str");
    };

    return $mail;
}
?>