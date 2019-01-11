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
	require_once PROJECT_INC . "NREvent.php";
	require_once PROJECT_INC . "NRFCM.php";
	require_once PROJECT_INC . "NRImage.php";
	require_once PROJECT_INC . "NRPackage.php";
    require_once PROJECT_LIB . "autoload.php";
    \Stripe\Stripe::setApiKey(STRIPE_SECRET);
    
    /** 
     * Select all unpaid events greater than 3 days old 
     */

    $sql = 
    "SELECT j.id
        FROM nr_jobs as j
        LEFT JOIN nr_client_receipts as r ON j.id = r.event_id
        WHERE r.event_id IS NULL
        AND TIMESTAMPDIFF(MINUTE, NOW(), j.event_datetime) > -4320;";

    // Get list of event IDs
    $query = runSQLQuery($sql);
    
    // Loop event ID list
    foreach($query["data"] as $eventObject) {
        $event = (new NREvent())->getSingle($eventObject["id"]);

        // Get card
        $sql = "SELECT *
            FROM nr_payment_cards
            WHERE id = {$event->clientCardId};";

        $query = runSQLQuery($sql);

        if(!isset($query["data"])) {
            $error = print_r($query, true);
            error_log($error);

            $mail = new Mailer();
            $mail->setFrom("it@nygmarosebeauty.com", "Glam Squad IT");
            $mail->addAddress("it@nygmarosebeauty.com");
            $mail->Subject = "IMPORTANT Glam Squad Payment Error!";
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
                        <p>
                            {$error}
                        </p>
                    </body>
                </html>
EOD;
            $mail->send();

            continue;
        } 
        
        $card = $query["data"];

        // Get event client 
        $client = (new NRClient)->get(["id" => $event->clientId]);
        
        /**
         * API: Stripe PHP SDK
         */
        
        $charge = \Stripe\Charge::create([
            "amount" => $event->price * 100,
            "currency" => "sgd",
            "source" => $card["card_token"],
            "description" => "Event charge for {$client->username} <{$client->email}>.",
            "transfer_group" => "EVENT-{$event->id}"
        ]);

        error_log(print_r($charge, true));

        // TODO: For each artist create a transfer
    }

    /** 
     * Select all events without receipts that are 3 days+ in age
     */
    
    $sql = 
    "SELECT j.id
        FROM nr_jobs as j
        LEFT JOIN nr_client_receipts as r ON j.id = r.event_id
        WHERE r.event_id IS NULL
        AND TIMESTAMPDIFF(MINUTE, NOW(), j.event_datetime) <= -4320;";

    // Get list of event IDs
    $query = runSQLQuery($sql);

	// If no events, exit
    if(!isset($query["data"])) die(0);
    
    // Loop event ID list
    foreach($query["data"] as $eventObject) {
        $event = new NREvent();
        $event->getSingle($eventObject["id"]);
    }
?>