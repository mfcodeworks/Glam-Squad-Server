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

    // Set Stripe Payment Key
    \Stripe\Stripe::setApiKey(STRIPE_SECRET);

    /**
     * Select all unpaid events greater than 3 days old and loop through for processing
     */
    error_log("[".date('Y-m-d H:i:s')."] Processing event payment for events T+3days");

    $events = getExpiredEvents();
    foreach($events as $eventObject) {
        // Get event
        $event = (new NREvent())->getSingle($eventObject["id"]);
        // Process event for payment
        processEvent($event);
    }

    /**
     * Select all events within the last 3 days (Now - 3 days ago) without receipts to check for attendance completion and process
     */
    error_log("[".date('Y-m-d H:i:s')."] Processing event payment for recent events with attendance completed");

    $events = getRecentEvents();
    foreach($events as $eventObject) {
        // Get event
        $event = (new NREvent())->getSingle($eventObject["id"]);
        // If attendance complete, process event for payment
        if($event->attendanceComplete()) processEvent($event);
    }

    function processEvent($event) {
        // Calculate price owed for event and get Artist transfer array
        extract(calculatePriceOwed($event));

        // Charge client and pay artists respectively
        $charge = processPayment($event, $eventPrice, $transfers);

        // Execute artist payments
        if(isset($transfers) && $charge) processTransfers($event, $transfers, $charge);

        // Remove Twilio Channel DEBUG: Log chat removal
        error_log("Removing chat: event-{$event->id}");
        (new NRChat())->deleteChannel("event-{$event->id}");
    }

    function processTransfers($event, $transfers, $charge) {
        // DEBUG: Log transfers
        error_log(print_r($transfers, true));

        // Loop through transfers array
        foreach($transfers as $transfer) {
            // Find correct artist and save reference
            $currentArtist;
            foreach($event->artists as $artist) {
                if($artist->id === $transfer["artist_id"]) {
                    $fcmToken = $artist->fcmToken;
                    $currentArtist = $artist;
                    unset($transfer["artist_id"]);
                    continue;
                }
            }

            // Set transfers source charge ID
            $transfer["source_transaction"] = $charge->id;

            // Create artist transfer
            $transfer = \Stripe\Transfer::create($transfer);

            // Enter artist payment receipt
            $amount = $transfer->amount / 100;
            $query = runSQLQuery(
                "INSERT INTO nr_artist_payments(
                    payment_amount,
                    event_id,
                    artist_id,
                    artist_stripe_account,
                    stripe_transfer_id
                )
                VALUES(
                    {$amount},
                    {$event->id},
                    {$currentArtist->id},
                    \"{$currentArtist->stripe_account_token}\",
                    \"{$transfer->id}\"
                );"
            );

            // Email urgent error if charge receipt doesn't save
            if($query["error"] != null) email_error(print_r($query, true));

            // Notify Artist of transfer
            $fcmAmount = number_format($amount, 2);
            (new NRFCM())->send(
                $notif = [
                    "to" => $currentArtist->fcmToken,
                    "priority" => 'high',
                    "data" => [
                        "title" => "Event Payment",
                        "message" => "\$$fcmAmount transferred to your account for event payment",
                        'content-available'  => '1',
                        "image" => 'logo'
                    ]
                ],
                FCM_NOTIFICATION_ENDPOINT
            );
        }
    }

    function processPayment($event, $eventPrice, $transfers) {
        // Get card, if no card was retrieved email error and continue to next event
        try {
            $card = getEventCard($event);
        } catch(Exception $e) {
            email_error($e->getMessage());
            return null;
        }

        // Get client attendance
        $clientAttendance = getClientAttendance($event);

        // Count the artists and clients in attendance for logging
        $artistCount = isset($transfers) ? count($transfers) : 0;
        $clientCount = $clientAttendance ? "client attended" : "client didn't attend";
        error_log("Event {$event->id}: $clientCount, $artistCount artists' attended");

        // If no artists attended but the client attended, create a null receipt
        if($eventPrice == 0 && $clientAttendance == 1) {
            createNullReceipt($event);
            return null;
        }

        // If client skipped event, log attendance breach
        if(!$clientAttendance) {
            runSQLQuery(
                "INSERT INTO nr_client_attendance_breaches(event_id, client_id)
                VALUES({$event->id}, {$client["id"]});"
            );
        }

        // Get event client
        $client = (new NRClient)->get(["id" => $event->clientId])["data"][0];

        /**
         * API: Stripe PHP SDK
         */

        // Create client charge, if client attended charge for artists, if client skipped event charge full price
        $charge = \Stripe\Charge::create([
            "amount" => ($clientAttendance) ? $eventPrice * 100 : $event->price * 100,
            "currency" => "sgd",
            "source" => $card["card_token"],
            "customer" => $client["stripe_customer_id"],
            "description" => "Event {$event->id} charge for {$client["username"]} <{$client["email"]}>",
            "receipt_email" => $client["email"],
            "transfer_group" => "EVENT-{$event->id}"
        ]);

        // Enter client receipt
        $query = runSQLQuery(
            "INSERT INTO nr_client_receipts(
                payment_amount,
                event_id,
                client_id,
                client_card_id,
                stripe_charge_id
            )
            VALUES(
                {$eventPrice},
                {$event->id},
                {$event->clientId},
                {$event->clientCardId},
                \"$charge->id\"
            );"
        );

        // Email urgent error if charge receipt doesn't save
        if($query["error"] != null) email_error(print_r($query, true));

        // Notify Client of charge
        $fcmAmount = number_format($event->price, 2);
        (new NRFCM())->send(
            [
                "to" => $client["fcm_token"],
                "priority" => 'high',
                "data" => [
                    "title" => "Event Charge",
                    "message" => "\$$fcmAmount deducted for event payment",
                    'content-available'  => '1',
                    "image" => 'logo'
                ]
            ],
            FCM_NOTIFICATION_ENDPOINT
        );

        return $charge;
    }

    function createNullReceipt($event) {
        // Log event no attendance
        error_log("Event {$event->id} client attended, no artists attended. Creating null receipt.");

        // Enter receipt
        $query = runSQLQuery(
            "INSERT INTO nr_client_receipts(
                payment_amount,
                event_id,
                client_id,
                client_card_id,
                stripe_charge_id
            )
            VALUES(
                0,
                {$event->id},
                {$event->clientId},
                {$event->clientCardId},
                \"NULL\"
            );"
        );

        // Email urgent error if charge receipt doesn't save
        if($query["error"] != null) email_error(print_r($query, true));
    }

    function getRecentEvents() {
        $sql = "SELECT j.id
            FROM nr_jobs as j
            LEFT JOIN nr_client_receipts as r ON j.id = r.event_id
            WHERE r.event_id IS NULL
            AND TIMESTAMPDIFF(DAY, j.event_datetime, NOW()) < 3;";

        // Get list of event IDs
        return runSQLQuery($sql)["data"];
    }

    function getExpiredEvents() {
        $sql = "SELECT j.id
            FROM nr_jobs as j
            LEFT JOIN nr_client_receipts as r ON j.id = r.event_id
            WHERE r.event_id IS NULL
            AND TIMESTAMPDIFF(DAY, j.event_datetime, NOW()) >= 3;";

        // Get list of event IDs
        return runSQLQuery($sql)["data"];
    }

    function getClientAttendance($event) {
        $attendance = runSQLQuery(
            "SELECT *
            FROM nr_job_client_attendance
            WHERE event_id = {$event->id}
            AND client_id = {$event->clientId};"
        );

        // If client responded with attendance accept that, else set unattended
        return isset($attendance["data"][0])
            ? $attendance["data"][0]["attendance"] : 0;
    }

    function getEventCard($event) {
        // Get card data
        $query = runSQLQuery(
            "SELECT *
            FROM nr_payment_cards
            WHERE id = {$event->clientCardId};"
        );

        // Email urgent error if card doesn't exist
        if(!isset($query["data"])) {
            throw new Exception("ERROR. Couldn't get card for event {$event->id}. Card ID {$event->clientCardId}. Client ID {$event->clientId}.");
        // Otherwise return card details
        } else {
            return $query["data"][0];
        }
    }

    function calculatePriceOwed($event) {
        // Set event initial price
        $eventPrice = $event->price;

        // For each artist create a transfer
        foreach($event->artists as $artist) {
            // Get artist attendance
            $attendance = runSQLQuery(
                "SELECT *
                FROM nr_job_artist_attendance
                WHERE event_id = {$event->id}
                AND artist_id = {$artist->id};"
            );

            // If artist responded with attendance accept that, else set unattended
            $attendance = isset($attendance["data"][0])
                ? $attendance["data"][0]["attendance"] : 0;

            // Calculate payment amount
            switch($artist->role["id"]) {
                case 1:
                    // If artist didn't attend, deduct from event price
                    if($attendance == 0) {
                        $eventPrice -= MAKEUP_ARTIST_FEE;
                        // Add attendance breach
                        runSQLQuery(
                            "INSERT INTO nr_artist_attendance_breaches(artist_id, event_id)
                                VALUES({$artist->id}, {$event->id});"
                        );
                        continue 2;
                    }

                    // Transfer to makeup artist if attended
                    $amount = MAKEUP_ARTIST_FEE * ARTIST_PERCENTAGE;

                    // Calculate extra hours payment
                    if($event->extraHours > 0) {
                        $amount += (MAKEUP_ARTIST_HOURLY_FEE * $event->extraHours) * ARTIST_PERCENTAGE;
                    }
                    break;

                case 2:
                    // If artist didn't attend, deduct from event price
                    if($attendance == 0) {
                        $eventPrice -= HAIR_STYLIST_FEE;
                        // Add attendance breach
                        runSQLQuery(
                            "INSERT INTO nr_artist_attendance_breaches(artist_id, event_id)
                                VALUES({$artist->id}, {$event->id});"
                        );
                        continue 2;
                    }

                    // Transfer to hair stylist if attended
                    $amount = HAIR_STYLIST_FEE * ARTIST_PERCENTAGE;
                    break;
            }

            // Create artist transfer
            if($attendance) {
                $transfers[] = [
                    "artist_id" => $artist->id,
                    "amount" => $amount * 100,
                    "currency" => "sgd",
                    "destination" => $artist->stripe_account_token,
                    "description" => "Payment to {$artist->username} <{$artist->email}> for event {$event->id} {$artist->role["name"]}",
                    "transfer_group" => "EVENT-{$event->id}"
                ];
            }
        }

        return [
            "transfers" => $transfers,
            "eventPrice" => $eventPrice
        ];
    }

    function email_error($error) {
        // Log error
        error_log($error);

        // Email error
        $mail = new Mailer();
        $mail->setFrom("it@nygmarosebeauty.com", "Glam Squad IT");
        $mail->addAddress("it@nygmarosebeauty.com");
        $mail->Subject = "IMPORTANT Glam Squad Error!";
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
                    <pre>
                        {$error}
                    </pre>
                </body>
            </html>
EOD;
        $mail->send();
    }
?>
