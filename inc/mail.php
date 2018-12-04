<?php

// Import classes
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Require classes
require_once PROJECT_LIB . "phpmailer/phpmailer/src/PHPMailer.php";
require_once PROJECT_LIB . "phpmailer/phpmailer/src/SMTP.php";
require_once PROJECT_LIB . "phpmailer/phpmailer/src/Exception.php";

class Mailer extends PHPMailer {
    function __construct() {
        parent::__construct();
        $this->IsSMTP();
        $this->SMTPAuth = true;
        $this->Host = SMTP_HOST;
        $this->Port = SMTP_PORT;
        $this->Username = SMTP_USER;
        $this->Password = SMTP_PASS;
        $this->isHTML(true);
        $this->CharSet = "UTF-8";
        $this->SMTPDebug = 2;
        $this->Debugoutput = function($str, $level) {
            error_log("Mailer Debug (Level: $level) \n\n $str");
        };
    }
}
?>