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
    
    // Select events that have already passed which remain unpaid
    $sql = 
    "SELECT j.id 
        FROM nr_jobs as j 
        LEFT JOIN nr_client_receipts as r ON j.id = r.event_id 
        WHERE r.event_id IS NULL
        AND TIMESTAMPDIFF(MINUTE, NOW(), event_datetime) <= -60;";
		
	// Get list of events
	$query = runSQLQuery($sql);

	// If no events, exit
	if(!isset($query["data"])) die(0);

	// Loop event ID list
	foreach($query["data"] as $eventObject) {

		// If reminder hasn't been sent
		if(reminderSent($eventObject["id"]) === false) {

			// Get event info
			$event = new NREvent();
			$event->getSingle($eventObject["id"]);
	
			// Init. FCM object
			$fcm = new NRFCM();
	
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
	
			// Try to send reminder notification
			try {
				$fcm->send($notification, FCM_NOTIFICATION_ENDPOINT);
				setReminderSent($event->id);
			}
			catch(Exception $e) {
				error_log("Error sending reminder for event {$event->id}");
			}
		}
    }

	// Check reminder sent
	function reminderSent($id) {
		// Check for reminder 
		$sql =
		"SELECT id
			FROM nr_job_confirmation_reminders
			WHERE event_id = $id;";

		$data = runSQLQuery($sql);

		if(isset($data["data"][0])) return true;
		return false;
	}

	function setReminderSent($id) {
		$sql =
		"INSERT INTO nr_job_confirmation_reminders(event_id)
			VALUES($id);";

		return runSQLQuery($sql)["response"];
	}
?>