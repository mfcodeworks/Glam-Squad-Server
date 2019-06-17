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

    error_log("[".date('Y-m-d H:i:s')."] Processing event payment for events T+3days");

    // Create payment notification object
    $fcm = new NRFCM();

    $sql =
    "SELECT j.id
        FROM nr_jobs as j
        LEFT JOIN nr_client_receipts as r ON j.id = r.event_id
        WHERE r.event_id IS NULL
        AND TIMESTAMPDIFF(DAY, j.event_datetime, NOW()) >= 3;";

    // Get list of event IDs
    $query = runSQLQuery($sql);

    // Loop event ID list
    if(isset($query["data"])) {
        // Loop event ID list
        foreach($query["data"] as $eventObject) {
            // Get event
            $event = (new NREvent())->getSingle($eventObject["id"]);

            // Set event initial price
            $eventPrice = $event->price;

            // For each artist create a transfer
            foreach($event->artists as $artist) {
                // Get artist attendance
                $attendanceQuery =
                "SELECT *
                    FROM nr_job_artist_attendance
                    WHERE event_id = {$event->id}
                    AND artist_id = {$artist->id};";

                $attendance = runSQLQuery($attendanceQuery);

                // If artist responded with attendance accept that, else set unattended
                if(isset($attendance["data"][0])) $attendance = $attendance["data"][0];
                else $attendance["attendance"] = 0;

                // Calculate payment amount
                switch($artist->role["id"]) {
                    case 1:
                        // If artist didn't attend, deduct from event price
                        if($attendance["attendance"] == 0) {
                            $eventPrice -= MAKEUP_ARTIST_FEE;
                            // Add attendance breach
                            runSQLQuery(
                                "INSERT INTO nr_artist_attendance_breaches(artist_id, event_id)
                                    VALUES({$artist->id}, {$event->id});"
                            );
                            continue 2;
                        }

                        // Transfer to makeup artist
                        $amount = MAKEUP_ARTIST_FEE * ARTIST_PERCENTAGE;

                        // Calculate extra hours payment
                        if($event->extraHours > 0) {
                            $amount += (MAKEUP_ARTIST_HOURLY_FEE * $event->extraHours) * ARTIST_PERCENTAGE;
                        }
                        break;

                    case 2:
                        // If artist didn't attend, deduct from event price
                        if($attendance["attendance"] == 0) {
                            $eventPrice -= HAIR_STYLIST_FEE;
                            // Add attendance breach
                            runSQLQuery(
                                "INSERT INTO nr_artist_attendance_breaches(artist_id, event_id)
                                    VALUES({$artist->id}, {$event->id});"
                            );
                            continue 2;
                        }

                        // Transfer to hair stylist
                        $amount = HAIR_STYLIST_FEE * ARTIST_PERCENTAGE;
                        break;
                }

                // Create artist transfer
                if($attendance["attendance"]) {
                    $transfers[] = [
                        "amount" => $amount * 100,
                        "currency" => "sgd",
                        "destination" => $artist->stripe_account_token,
                        "description" => "Payment to {$artist->username} <{$artist->email}> for event {$event->id} {$artist->role["name"]}",
                        "transfer_group" => "EVENT-{$event->id}"
                    ];
                }
            }

            error_log(print_r($transfers, true));

            $clientSql = "SELECT *
                FROM nr_job_client_attendance
                WHERE event_id = {$event->id}
                AND client_id = {$event->clientId};";

            $clientAttendance = runSQLQuery($clientSql);

            // If client responded with attendance accept that, else set unattended
            $clientAttendance["attendance"] = $clientAttendance["data"][0];

            // Get card
            $sql = "SELECT *
                FROM nr_payment_cards
                WHERE id = {$event->clientCardId};";

            $query = runSQLQuery($sql);

            // Email urgent error if card doesn't exist
            if(!isset($query["data"])) {
                email_error(print_r($query, true));
                continue;
            }

            // Get card details
            $card = $query["data"][0];

            // Get event client
            $client = (new NRClient)->get(["id" => $event->clientId])["data"][0];

            // If event price is $0 (No artists attended) but client attended create null receipt
            if($eventPrice == 0 && $clientAttendance["attendance"] == 1) {
                // Log event no attendance
                error_log("Event {$event->id} no one attended");

                // Enter receipt
                $chargeSql =
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
                );";

                $query = runSQLQuery($chargeSql);

                // Email urgent error if charge receipt doesn't save
                if($query["error"] != null) {
                    email_error(print_r($query, true));
                }

                continue;

            // If client didnt attend
            } else if($clientAttendance["attendance"] == 0) {
                isset($transfers) ? $artistCount = count($transfers) : $artistCount = 0;
                error_log("Event {$event->id} client didn't attend, {$artistCount} artists attended");

                // Add attendance breach
                runSQLQuery(
                    "INSERT INTO nr_client_attendance_breaches(event_id, client_id)
                        VALUES({$event->id}, {$client["id"]});"
                );

                /**
                 * API: Stripe PHP SDK
                 */

                // Create client charge
                $charge = \Stripe\Charge::create([
                    "amount" => $event->price * 100,
                    "currency" => "sgd",
                    "source" => $card["card_token"],
                    "customer" => $client["stripe_customer_id"],
                    "description" => "Event {$event->id} charge for {$client["username"]} <{$client["email"]}>",
                    "receipt_email" => $client["email"],
                    "transfer_group" => "EVENT-{$event->id}"
                ]);

                // Enter receipt
                $chargeSql =
                "INSERT INTO nr_client_receipts(
                    payment_amount,
                    event_id,
                    client_id,
                    client_card_id,
                    stripe_charge_id
                )
                VALUES(
                    {$event->price},
                    {$event->id},
                    {$event->clientId},
                    {$event->clientCardId},
                    \"$charge->id\"
                );";

                $query = runSQLQuery($chargeSql);

                // Email urgent error if charge receipt doesn't save
                if($query["error"] != null) {
                    email_error(print_r($query, true));
                }

                // Notify Client of charge
                $notif = [
                    "to" => $client["fcm_token"],
                    "priority" => 'high',
                    "data" => [
                        "title" => "Event Charge",
                        "message" => "${$event->price} deducted for event payment",
                        'content-available'  => '1',
                        "image" => 'logo'
                    ]
                ];
                $fcm->send($notif, FCM_NOTIFICATION_ENDPOINT);

            // If client attended
            } else {
                isset($transfers) ? $artistCount = count($transfers) : $artistCount = 0;
                error_log("Event {$event->id} client attended, {$artistCount} artists attended");

                /**
                 * API: Stripe PHP SDK
                 */

                // Create client charge
                $charge = \Stripe\Charge::create([
                    "amount" => $eventPrice * 100,
                    "currency" => "sgd",
                    "source" => $card["card_token"],
                    "customer" => $client["stripe_customer_id"],
                    "description" => "Event {$event->id} charge for {$client["username"]} <{$client["email"]}>.",
                    "receipt_email" => $client["email"],
                    "transfer_group" => "EVENT-{$event->id}"
                ]);

                // Enter receipt
                $chargeSql =
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
                );";

                $query = runSQLQuery($chargeSql);

                // Email urgent error if charge receipt doesn't save
                if($query["error"] != null) {
                    email_error(print_r($query, true));
                }

                // Notify Client of charge
                $notif = [
                    "to" => $client["fcm_token"],
                    "priority" => 'high',
                    "data" => [
                        "title" => "Event Charge",
                        "message" => "${$event->price} deducted for event payment",
                        'content-available'  => '1',
                        "image" => 'logo'
                    ]
                ];
                $fcm->send($notif, FCM_NOTIFICATION_ENDPOINT);
            }

            // Execute artist payments
            if(isset($transfers)) {
                foreach($transfers as $transfer) {
                    $transfer["source_transaction"] = $charge->id;

                    // Create artist transfer
                    $transfer = \Stripe\Transfer::create($transfer);

                    // Enter artist payment receipt
                    $amount = $transfer->amount / 100;
                    $transferSql =
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
                        {$artist->id},
                        \"{$artist->stripe_account_token}\",
                        \"{$transfer->id}\"
                    );";

                    $query = runSQLQuery($transferSql);

                    // Email urgent error if charge receipt doesn't save
                    if($query["error"] != null) {
                        email_error(print_r($query, true));
                    }

                    // Notify Client of charge
                    $notif = [
                        "to" => $client["fcm_token"],
                        "priority" => 'high',
                        "data" => [
                            "title" => "Event Payment",
                            "message" => "$$amount transferred to your account for event payment",
                            'content-available'  => '1',
                            "image" => 'logo'
                        ]
                    ];
                    $fcm->send($notif, FCM_NOTIFICATION_ENDPOINT);
                }
            }
        }
    }

    /**
     * Select all events without receipts that are less than 3 days in age to check for attendance completion
     */

    error_log("[".date('Y-m-d H:i:s')."] Processing event payment for recent events with attendance responded");

    $sql =
    "SELECT j.id
        FROM nr_jobs as j
        LEFT JOIN nr_client_receipts as r ON j.id = r.event_id
        WHERE r.event_id IS NULL
        AND TIMESTAMPDIFF(DAY, j.event_datetime, NOW()) < 3;";

    // Get list of event IDs
    $query = runSQLQuery($sql);

    // If no events, exit
    if(!isset($query["data"])) die(0);

    // Loop event ID list
    foreach($query["data"] as $eventObject) {
        // Get event
        $event = (new NREvent())->getSingle($eventObject["id"]);

        // Calculate attendance requirement
        $attendanceRequirement = 1;
        foreach($event->requirements as $role => $required) {
            $attendanceRequirement++;
        }

        // Count artist attendance
        $sql = "SELECT COUNT(id) as attended
            FROM nr_job_artist_attendance
            WHERE event_id = {$event->id};";

        $artistAttended = runSQLQuery($sql)["data"][0]["attended"];

        // Count client attendance
        $sql = "SELECT COUNT(id) as attended
            FROM nr_job_client_attendance
            WHERE event_id = {$event->id};";

        $clientAttended = runSQLQuery($sql)["data"][0]["attended"];

        // If client + artist attendance doesn't fulfill all persons attended continue to wait
        if(($clientAttended + $artistAttended) !== $attendanceRequirement) continue;
        error_log("Event {$event->id} all persons responded, processing payment");

        // Get client attendance
        $clientSql = "SELECT *
            FROM nr_job_client_attendance
            WHERE event_id = {$event->id}
            AND client_id = {$event->clientId};";

        $clientAttendance = runSQLQuery($clientSql);

        // Get client attendance response
        $clientAttendance["attendance"] = $clientAttendance["data"][0];

        // Set event initial price
        $eventPrice = $event->price;

        // For each artist create a transfer
        foreach($event->artists as $artist) {
            $attendanceQuery =
            "SELECT *
                FROM nr_job_artist_attendance
                WHERE event_id = {$event->id}
                AND artist_id = {$artist->id};";

            // Get artist attendance
            $attendance = runSQLQuery($attendanceQuery)["data"][0];

            // Calculate payment amount
            switch($artist->role["id"]) {
                case 1:
                    // If artist didn't attend, deduct from event price
                    if($attendance["attendance"] == 0) {
                        $eventPrice -= MAKEUP_ARTIST_FEE;
                        // Add attendance breach
                        runSQLQuery(
                            "INSERT INTO nr_artist_attendance_breaches(artist_id, event_id)
                                VALUES({$artist->id}, {$event->id});"
                        );
                        continue 2;
                    }

                    // If attended, transfer to makeup artist
                    $amount = MAKEUP_ARTIST_FEE * ARTIST_PERCENTAGE;

                    // Calculate extra hours payment
                    if($event->extraHours > 0) {
                        $amount += (MAKEUP_ARTIST_HOURLY_FEE * $event->extraHours) * ARTIST_PERCENTAGE;
                    }
                    break;

                case 2:
                    // If artist didn't attend, deduct from event price
                    if($attendance["attendance"] == 0) {
                        $eventPrice -= HAIR_STYLIST_FEE;
                        // Add attendance breach
                        runSQLQuery(
                            "INSERT INTO nr_artist_attendance_breaches(artist_id, event_id)
                                VALUES({$artist->id}, {$event->id});"
                        );
                        continue 2;
                    }

                    // If attended, transfer to hair stylist
                    $amount = HAIR_STYLIST_FEE * ARTIST_PERCENTAGE;
                    break;
            }

            // Create artist transfer
            if($attendance["attendance"]) {
                $transfers[] = [
                    "amount" => $amount * 100,
                    "currency" => "sgd",
                    "destination" => $artist->stripe_account_token,
                    "description" => "Payment to {$artist->username} <{$artist->email}> for event {$artist->role["name"]}",
                    "transfer_group" => "EVENT-{$event->id}"
                ];
                error_log("Transferring {$amount} SGD to {$artist->username} <{$artist->email}> for event {$event->id}");
            }
        }

        // Get card
        $sql = "SELECT *
            FROM nr_payment_cards
            WHERE id = {$event->clientCardId};";

        $query = runSQLQuery($sql);

        // Email urgent error if card doesn't exist
        if(!isset($query["data"])) {
            email_error(print_r($query, true));
            continue;
        }

        // Get card details
        $card = $query["data"][0];

        // Get event client
        $client = (new NRClient)->get(["id" => $event->clientId])["data"][0];

        // If event price is $0 (No artists attended) but client attended create null receipt
        if($eventPrice == 0 && $clientAttendance["attendance"] == 1) {
            // Log event no attendance
            error_log("Event {$event->id} no artists attended");

            // Enter receipt
            $chargeSql =
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
            );";

            $query = runSQLQuery($chargeSql);

            // Email urgent error if charge receipt doesn't save
            if($query["error"] != null) {
                email_error(print_r($query, true));
            }

            continue;

        // If client didn't attend charge full fee
        } else if($clientAttendance["attendance"] == 0) {
            // Add attendance breach
            runSQLQuery(
                "INSERT INTO nr_client_attendance_breaches(event_id, client_id)
                    VALUES({$event->id}, {$client["id"]});"
            );

            /**
             * API: Stripe PHP SDK
             */

            // Create client charge
            $charge = \Stripe\Charge::create([
                "amount" => $event->price * 100,
                "currency" => "sgd",
                "source" => $card["card_token"],
                "customer" => $client["stripe_customer_id"],
                "description" => "Event charge for {$client["username"]} <{$client["email"]}>.",
                "receipt_email" => $client["email"],
                "transfer_group" => "EVENT-{$event->id}"
            ]);

            // Enter receipt
            $chargeSql =
            "INSERT INTO nr_client_receipts(
                payment_amount,
                event_id,
                client_id,
                client_card_id,
                stripe_charge_id
            )
            VALUES(
                {$event->price},
                {$event->id},
                {$event->clientId},
                {$event->clientCardId},
                \"$charge->id\"
            );";

            $query = runSQLQuery($chargeSql);

            // Email urgent error if charge receipt doesn't save
            if($query["error"] != null) {
                email_error(print_r($query, true));
            }

            // Notify Client of charge
            $notif = [
                "to" => $client["fcm_token"],
                "priority" => 'high',
                "data" => [
                    "title" => "Event Charge",
                    "message" => "${$event->price} deducted for event payment",
                    'content-available'  => '1',
                    "image" => 'logo'
                ]
            ];
            $fcm->send($notif, FCM_NOTIFICATION_ENDPOINT);

        // If client and at least one artist attended
        } else {
            /**
             * API: Stripe PHP SDK
             */

            // Create client charge
            $charge = \Stripe\Charge::create([
                "amount" => $eventPrice * 100,
                "currency" => "sgd",
                "source" => $card["card_token"],
                "customer" => $client["stripe_customer_id"],
                "description" => "Event charge for {$client["username"]} <{$client["email"]}>.",
                "receipt_email" => $client["email"],
                "transfer_group" => "EVENT-{$event->id}"
            ]);

            // Enter receipt
            $chargeSql =
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
            );";

            $query = runSQLQuery($chargeSql);

            // Email urgent error if charge receipt doesn't save
            if($query["error"] != null) {
                email_error(print_r($query, true));
            }

            // Notify Client of charge
            $notif = [
                "to" => $client["fcm_token"],
                "priority" => 'high',
                "data" => [
                    "title" => "Event Charge",
                    "message" => "${$event->price} deducted for event payment",
                    'content-available'  => '1',
                    "image" => 'logo'
                ]
            ];
            $fcm->send($notif, FCM_NOTIFICATION_ENDPOINT);
        }

        // Execute artist payments
        if(isset($transfers)) {
            foreach($transfers as $transfer) {
                $transfer["source_transaction"] = $charge->id;

                // Create artist transfer
                $transfer = \Stripe\Transfer::create($transfer);

                // Enter artist payment receipt
                $amount = $transfer->amount / 100;
                $transferSql =
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
                    {$artist->id},
                    \"{$artist->stripe_account_token}\",
                    \"{$transfer->id}\"
                );";

                $query = runSQLQuery($transferSql);

                // Email urgent error if charge receipt doesn't save
                if($query["error"] != null) {
                    email_error(print_r($query, true));
                }

                // Notify Client of charge
                $notif = [
                    "to" => $client["fcm_token"],
                    "priority" => 'high',
                    "data" => [
                        "title" => "Event Payment",
                        "message" => "$$amount transferred to your account for event payment",
                        'content-available'  => '1',
                        "image" => 'logo'
                    ]
                ];
                $fcm->send($notif, FCM_NOTIFICATION_ENDPOINT);
            }
        }
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
