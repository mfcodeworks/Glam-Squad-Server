<?php
    header('Access-Control-Allow-Origin: *');
    header("Connection: close\r\n");
    header("Content-Encoding: none\r\n");
    header("Content-Length: 1");
    ignore_user_abort(true);

	// Paths
    define('PROJECT_ROOT', dirname(dirname(__FILE__)));
    define('PROJECT_CONFIG', PROJECT_ROOT . '/config/');
	define('PROJECT_INC', PROJECT_ROOT . '/src/');
	define('PROJECT_LIB', PROJECT_ROOT . '/vendor/');

    require_once PROJECT_CONFIG . "config.php";
    require_once PROJECT_INC . "NRResmushIt.php";

    $photos = $_POST;

    foreach($photos as $photo) {
        new NRResmushIt($photo);
    }
?>