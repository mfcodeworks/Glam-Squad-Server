<?php
    // Paths
    define('PROJECT_ROOT', dirname(dirname(dirname(__FILE__))));
    define('PROJECT_CONFIG', PROJECT_ROOT . '/config/');
    define('PROJECT_INC', PROJECT_ROOT . '/src/');
    define('PROJECT_LIB', PROJECT_ROOT . '/vendor/');

    // Require classes
    require_once PROJECT_CONFIG . "config.php";
    require_once PROJECT_INC . "DegreeDistanceFinder.php";
    require_once PROJECT_INC . "Mailer.php";
    require_once PROJECT_INC . "NRArtist.php";
    require_once PROJECT_INC . "NRClient.php";
    require_once PROJECT_INC . "NRChat.php";
    require_once PROJECT_INC . "NREvent.php";
    require_once PROJECT_INC . "NRFCM.php";
    require_once PROJECT_INC . "NRImage.php";
    require_once PROJECT_INC . "NRPackage.php";
    require_once PROJECT_LIB . "autoload.php";

    error_log("[".date('Y-m-d H:i:s')."] Checking for low rated artists");

    // Find artists with below average scores
    $query = runSQLQuery(
        "SELECT *
        FROM (
            SELECT AVG(r.rating) as rating, a.id as id, a.username as name, a.email as email
            FROM nr_artists as a
            LEFT OUTER JOIN nr_artist_ratings as r
            ON r.artist_id = a.id
            WHERE r.artist_id IS NOT NULL
            AND a.locked = 0
            GROUP BY a.id
        ) as ra
        WHERE ra.rating <= 5.0;"
    );

    // If artiss found, construct email
    $query["data"] ? $artists = $query["data"] : exit(0);
    $text = "";
    error_log(print_r($artists, true));

    foreach($artists as $artist) {
        $rating = number_format($artist["rating"], 2);
        $text .= "<br>ID {$artist["id"]} {$artist["name"]} <<a href='mailto:{$artist["email"]}'>{$artist["email"]}</a>> Rating $rating<br><br>";
    }

    send_email($text);

    function send_email($artists) {
        // Email artists
        $mail = new Mailer();
        $mail->setFrom("it@nygmarosebeauty.com", "Glam Squad IT");
        $mail->addAddress("mua@nygmarosebeauty.com");
        $mail->Subject = "[GlamSquad] Low Rated Artists";
        $mail->Body = <<<EOD
            <html>
                <head>
                    <style>
                        body {
                            font-family: Arial;
                        }
                    </style>
                </head>
                <body>
                    GlamSquad has identified artists with severly low ratings to investigate, their accounts have been suspended pending investigation.
                    <br>
                    $artists
                    <small>This is an auto-generated message from the GlamSquad server.</small>
                </body>
            </html>
    EOD;
        $mail->send();
    }
?>