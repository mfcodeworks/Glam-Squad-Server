<?php
    // Paths
    define('PROJECT_ROOT', dirname(dirname(dirname(__FILE__))));
    define('PROJECT_CONFIG', PROJECT_ROOT . '/config/');
    define('PROJECT_INC', PROJECT_ROOT . '/src/');
    define('PROJECT_LIB', PROJECT_ROOT . '/vendor/');

    // Require classes
    require_once PROJECT_CONFIG . "config.php";
    require_once PROJECT_INC . "DegreeDistanceFinder.php";
    require_once PROJECT_INC . "Timer.php";
    require_once PROJECT_INC . "Mailer.php";
    require_once PROJECT_INC . "NRArtist.php";
    require_once PROJECT_INC . "NRClient.php";
    require_once PROJECT_INC . "NREvent.php";
    require_once PROJECT_INC . "NRFCM.php";
    require_once PROJECT_INC . "NRImage.php";
    require_once PROJECT_INC . "NRPackage.php";
    require_once PROJECT_LIB . "autoload.php";

    // DEBUG: Measure exec time
    $timer = (new Timer())->begin();

    // Log cron task
    error_log("[".date('Y-m-d H:i:s')."] Sending recent event confirmation reminders.");

    // Get recently completed events
    $events = getRecentlyCompletedEvents();

    // Loop event ID list, if reminder hasn't been sent, send reminder
    foreach($events as $eventObject) {
        // Get event info
        $event = (new NREvent())->getSingle($eventObject["id"]);
        // If no reminder sent, send one
        if(!$event->confirmationReminderSent()) constructReminder(1, $event);
    }

    // Log cron task
    error_log("[".date('Y-m-d H:i:s')."] Sending upcoming event reminders");

    // Get upcoming events
    $events = getUpcomingEvents();

    // Loop upcoming event ID list
    foreach($events as $eventObject) {
        // Get event info
        $event = (new NREvent())->getSingle($eventObject["id"]);
        // If no reminder sent, send one
        if(!$event->upcomingReminderSent()) constructReminder(2, $event);
    }

    error_log("Reminder Notification Push Execution Time: {$timer}");

    /**
     * Construct reminder notification for users.
     * Type:
     * - 1: Confirmation
     * - 2: Reminder
     *
     * @param int $type
     * @param array $eventObject
     * @return void
     */
    function constructReminder($type, $event) {
        // Create notification payload
        $notification = [
            "condition" => "'event-{$event->id}-client' in topics || 'event-{$event->id}-artist' in topics",
            "priority" => "high",
            "data" => [
                "title" => ($type === 1) ? "Event Confirmation" : "Event Reminder",
                "message" =>
                    ($type === 1)
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
            ($type === 1)
                ? $event->setConfirmationReminderSent()
                : $event->setUpcomingReminderSent();
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
            "SELECT j.id
            FROM nr_jobs as j
            WHERE TIMESTAMPDIFF(HOUR, event_datetime, NOW()) <= 0
            AND TIMESTAMPDIFF(HOUR, event_datetime, NOW()) >= -3;"
        )["data"];

        // If no events, exit
        return $query ? $query : [];
    }
?>
