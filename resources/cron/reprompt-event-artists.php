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
    require_once PROJECT_INC . "NRChat.php";
    require_once PROJECT_INC . "NREvent.php";
    require_once PROJECT_INC . "NRFCM.php";
    require_once PROJECT_INC . "NRImage.php";
    require_once PROJECT_INC . "NRPackage.php";
    require_once PROJECT_LIB . "autoload.php";

    // DEBUG: Measure exec time
    $timer = (new Timer())->begin();

    // Get upcoming events to check requirements fulfilled and reprompt if needed
    error_log("[".date('Y-m-d H:i:s')."] Prompting artist for upcoming jobs with open positions");

    $events = getUpcomingEvents();

    // For each event, if requirements aren't fulfilled push a notification
    foreach($events as $eventObject) {
        $event = (new NREvent())->getSingle($eventObject["id"]);
        if(!$event->requirementsFulfilled()) (new NRFCM())->sendEventNotification($event);
    }

    error_log("Reprompt Artists Execution Time: {$timer}");

    function getUpcomingEvents() {
        /**
         * Select events occuring within the next 3 hours that haven't passed
         *
         * - TIMESTAMPDIFF(DAY, event_datetime, NOW()) <= 0
         * - - Get events that are in the future (T-0 or lesser)
         * - TIMESTAMPDIFF(DAY, event_datetime, NOW()) >= -35
         * - - Get events that are less than 35 days (1 month~) away (T-35 or greater)
         * - TIMESTAMPDIFF(DAY, a.log_datetime, NOW()) >= 1
         * - - Get events that were last notified about more than a day ago (T+1 or greater)
         */
        $query = runSQLQuery(
            "SELECT j.id, j.event_datetime
            FROM nr_jobs as j
            LEFT JOIN nr_job_availability_reminders as a ON j.id = a.event_id
            WHERE a.event_id IS NOT NULL
            AND TIMESTAMPDIFF(DAY, event_datetime, NOW()) <= 0
            AND TIMESTAMPDIFF(DAY, event_datetime, NOW()) >= -35
            AND TIMESTAMPDIFF(DAY, a.log_datetime, NOW()) >= 1
            OR a.event_id IS NULL
            AND TIMESTAMPDIFF(DAY, event_datetime, NOW()) <= 0
            AND TIMESTAMPDIFF(DAY, event_datetime, NOW()) >= -35;"
        )["data"];

        // If no events, exit
        return $query ? $query : [];
    }
?>
