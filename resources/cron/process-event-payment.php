<?php
	// Paths
	define('PROJECT_ROOT', dirname(dirname(__FILE__)));
	define('PROJECT_INC', PROJECT_ROOT . '/inc/');
	define('PROJECT_LIB', PROJECT_ROOT . '/vendor/');

	// Require classes
	require_once PROJECT_INC . "config.php";
	require_once PROJECT_INC . "class-client.php";
	require_once PROJECT_INC . "class-artist.php";
	require_once PROJECT_INC . "class-fcm.php";
	require_once PROJECT_INC . "class-event.php";
	require_once PROJECT_INC . "class-package.php";
	require_once PROJECT_INC . "class-db-image.php";
	require_once PROJECT_INC . "class-degree-distance-finder.php";
    require_once PROJECT_INC . "mail.php";
	require_once PROJECT_LIB . "autoload.php";

    // Select all events without receipts that are 3 days+ in age
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