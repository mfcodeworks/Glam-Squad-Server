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

    // Get list of events
    $events = getRecentlyCompletedEvents();

    // Loop event ID list, if reminder hasn't been sent, send reminder
    foreach($events as $event) {
        if(!confirmationReminderSent($event["id"])) constructReminder("confirmation", $event);
    }

    // Log cron task
    error_log("[".date('Y-m-d H:i:s')."] Sending upcoming event reminders");

    // Get recently completed events
    $events = getUpcomingEvents();

    // Loop upcoming event ID list
    foreach($events as $event) {
        if(!upcomingReminderSent($event["id"])) constructReminder("upcoming", $event);
    }

    function constructReminder($type, $eventObject) {
        // Get event info
        $event = (new NREvent())->getSingle($eventObject["id"]);

        // Create notification payload
        $notification = [
            "condition" => "'event-{$event->id}-client' in topics || 'event-{$event->id}-artist' in topics",
            "priority" => "high",
            "data" => [
                "title" => ($type === "confirmation") ? "Event Confirmation" : "Event Reminder",
                "message" =>
                    ($type === "confirmation")
                        ? "It looks like the event at {$event->formatAddress()} is finished. Don't forget to confirm the event for payment."
                        : "Event at {$event->formatAddress()} starting soon, don't forget!",
                "content-available"  => "1",
                "image" => "logo"
            ]
        ];

        // DEBUG: Log notification
        error_log(json_encode($notification));

        try {
            // Try to send reminder notification
            (new NRFCM)->send($notification, FCM_NOTIFICATION_ENDPOINT);

            // Log reminder sent for specific type
            ($type === "confirmation")
                ? setConfirmationReminderSent($event->id)
                : setUpcomingReminderSent($event->id);
        } catch(Exception $e) {
            // Catch and log exceptions
            error_log("Error sending reminder for event {$event->id}");
        }
    }

    function getRecentlyCompletedEvents() {
        /**
         * Select events that have passed an hour with less than 3 hours which remain unpaid
         *
         * - TIMESTAMPDIFF(HOUR, event_datetime, NOW()) >= 1
         * - - Get events that are more than 1 hour in the past (T+1 or greater)
         * - TIMESTAMPDIFF(HOUR, event_datetime, NOW()) <= 3
         * - - Get events that are less than 3 hours in the past (T+3 or lesser)
         */
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

    function getUpcomingEvents() {
        /**
         * Select events occuring within the next 3 hours that haven't passed
         *
         * - TIMESTAMPDIFF(HOUR, event_datetime, NOW()) <= 0
         * - - Get events that are in the future (T-0 or greater)
         * - TIMESTAMPDIFF(HOUR, event_datetime, NOW()) >= -3
         * - - Get events that are less than 3 hours away (T-3 or lesser)
         */
        $query = runSQLQuery(
            "SELECT *
            FROM nr_jobs
            WHERE TIMESTAMPDIFF(HOUR, event_datetime, NOW()) <= 0
            AND TIMESTAMPDIFF(HOUR, event_datetime, NOW()) >= -3;"
        )["data"];

        // If no events, exit
        return $query ? $query : [];
    }

    function confirmationReminderSent($id) {
        // Check for reminder
        $data = runSQLQuery(
            "SELECT id
            FROM nr_job_confirmation_reminders
            WHERE event_id = $id;"
        );

        // Return true if reminder has been sent
        return isset($data["data"][0]);
    }

    function upcomingReminderSent($id) {
        // Check for reminder sent already
        $query = runSQLQuery(
            "SELECT id
            FROM nr_job_reminders
            WHERE event_id = $id;"
        );

        // Return true if reminder has been sent
        return isset($data["data"][0]);
    }

    function setConfirmationReminderSent($id) {
        // Set reminder as sent
        return runSQLQuery(
            "INSERT INTO nr_job_confirmation_reminders(event_id)
            VALUES($id);"
        )["response"];
    }

    function setUpcomingReminderSent($id) {
        // Set reminder as sent
        return runSQLQuery(
            "INSERT INTO nr_job_reminders(event_id)
            VALUES($id);"
        )["response"];
    }
?>
