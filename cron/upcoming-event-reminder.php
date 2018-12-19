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
    
    // Select events occuring within the next 2 hours that haven't passed
    $sql = 
    "SELECT id
        FROM nr_jobs 
        WHERE TIMESTAMPDIFF(MINUTE, NOW(), event_datetime) <= 120 
		AND TIMESTAMPDIFF(MINUTE, NOW(), event_datetime) >= 0;";
		
	$query = runSQLQuery($sql);

	if(!isset($query["data"])) die(0);

	foreach($query["data"] as $eventObject) {
		$event = new NREvent();
		$event->getSingle($eventObject["id"]);

		$fcm = new NRFCM();

		$addressArray = explode(",", $event->address);
		$notifAddress = $addressArray[0];
		(isset($addressArray[1])) ? $notifAddress .= "," . $addressArray[1] : null;
		(isset($addressArray[2])) ? $notifAddress .= "," . $addressArray[2] : null;

		$notification = [
			"condition" => "'event-{$event->id}-client' in topics || 'event-{$event->id}-artist' in topics",
			"priority" => 'high',
			"data" => [
				"title" => "Event Reminder",
				"message" => "Event at {$notifAddress} starting soon, don't forget!",
				'content-available'  => '1',
				"image" => 'logo'
			]
		];

		try {
			$fcm->send($notification, fcmEndpoint);
		}
		catch(Exception $e) {
			error_log("Error sending reminder for event {$event->id}");
		}
	}
?>