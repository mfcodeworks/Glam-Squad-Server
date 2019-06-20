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

    // Log cron task
    error_log("[".date('Y-m-d H:i:s')."] Sending recent event confirmation reminders.");

    // Init. FCM object
    $fcm = new NRFCM();

    // Get list of events
    $events = getRecentlyCompletedEvents();

    // Loop event ID list, if reminder hasn't been sent, send reminder
    foreach($events as $eventObject) {
        if(!reminderSent($eventObject["id"])) constructReminder($eventObject);
    }

    function constructReminder($eventObject) {
        // Get event info
        $event = (new NREvent())->getSingle($eventObject["id"]);

        // Format address for notifications
        $addressArray = explode(",", $event->address);
        $notifAddress = $addressArray[0];
        (isset($addressArray[1])) ? $notifAddress .= "," . $addressArray[1] : null;
        (isset($addressArray[2])) ? $notifAddress .= "," . $addressArray[2] : null;

        // Create notification payload
        $notification = [
            "condition" => "'event-{$event->id}-client' in topics || 'event-{$event->id}-artist' in topics",
            "priority" => "high",
            "data" => [
                "title" => "Event Confirmation",
                "message" => "It looks like the event at {$notifAddress} is finished. Don't forget to confirm the event for payment.",
                "content-available"  => "1",
                "image" => "logo"
            ]
        ];

        // DEBUG: Log notification
        error_log(json_encode($notification));

        // Try to send reminder notification
        try {
            $fcm->send($notification, FCM_NOTIFICATION_ENDPOINT);
            setReminderSent($event->id);
        } catch(Exception $e) {
            error_log("Error sending reminder for event {$event->id}");
        }
    }

    function getRecentlyCompletedEvents() {
        // Select events that have passed an hour with less than 3 hours which remain unpaid
        $query = runSQLQuery(
            "SELECT j.id
            FROM nr_jobs as j
            LEFT JOIN nr_client_receipts as r ON j.id = r.event_id
            WHERE r.event_id IS NULL
            AND TIMESTAMPDIFF(HOUR, j.event_datetime, NOW()) >= 1
            AND TIMESTAMPDIFF(HOUR, j.event_datetime, NOW()) <= 3;"
        )["data"];

        // Return found events or empty array
        return $query ? $query : [];
    }

    function reminderSent($id) {
        // Check for reminder
        $data = runSQLQuery(
            "SELECT id
            FROM nr_job_confirmation_reminders
            WHERE event_id = $id;"
        );

        // Return true if reminder has been sent
        return isset($data["data"][0]);
    }

    function setReminderSent($id) {
        // Set reminder as sent
        return runSQLQuery(
            "INSERT INTO nr_job_confirmation_reminders(event_id)
            VALUES($id);"
        )["response"];
    }
?>
