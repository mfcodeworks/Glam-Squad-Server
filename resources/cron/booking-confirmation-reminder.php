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

    // Select events that have already passed which remain unpaid
    $sql =
    "SELECT j.id
        FROM nr_jobs as j
        LEFT JOIN nr_client_receipts as r ON j.id = r.event_id
        WHERE r.event_id IS NULL
        AND TIMESTAMPDIFF(MINUTE, NOW(), j.event_datetime) <= -60;";

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