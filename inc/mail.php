<?php
class Mailer {
    public function mailer() {
        $mail = new PHPMailer();
        $mail->IsSMTP();
        $mail->CharSet = "UTF-8";
        $mail->Host = SMTP_HOST;
        $mail->SMTPDebug = 0;
        $mail->SMTPAuth = true;
        $mail->Port = SMTP_PORT;
        $mail->Username = SMTP_USER;
        $mail->Password = SMTP_PASS;
    
        return $mail;
    }
}
?>