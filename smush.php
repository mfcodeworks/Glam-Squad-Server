<?php
    //ob_end_clean();
    header('Access-Control-Allow-Origin: *');
    header("Connection: close\r\n");
    header("Content-Encoding: none\r\n");
    header("Content-Length: 1");
    ignore_user_abort(true);

    require_once "inc/config.php";
    require_once "inc/class-resmushit.php";

    $photos = $_POST;

    foreach($photos as $photo) {
        new NRResmushIt($photo);
    }
?>