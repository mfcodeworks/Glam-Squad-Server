<?php
    // Require config
    define('PROJECT_ROOT', dirname(dirname(__FILE__)));
    define('PROJECT_CONFIG', PROJECT_ROOT . '/config/');
	require_once PROJECT_CONFIG . "config.php";

    if(verify_payload()) {
        $path = dirname(__FILE__);
        echo shell_exec("$path/deploy.sh");
    } else {
        http_response_code(401);
        echo "Unauthorized Access";
    }

    function verify_payload() {
        // Create sha1 hash from raw request read
        $hash = "sha1=" . hash_hmac('sha1', file_get_contents("php://input"), GIT_SECRET);
        
        // Compare hashes and return
        if(hash_equals($_SERVER["HTTP_X_HUB_SIGNATURE"], $hash)) return true;
        return false;
    }
?>