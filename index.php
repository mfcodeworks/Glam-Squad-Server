<?php
	// Allow all origin and headers
	header('Access-Control-Allow-Origin: *');
	header('Access-Control-Allow-Headers: *');

	// Require classes
	require_once "inc/class-client.php";
	require_once "inc/class-fcm.php";
	require_once "inc/class-event.php";

	// API HMAC shared secret
	define("API_SECRET", '\3"dCwhe/B?g-KLT<h%:Wfz)3CY}^}~*');

	// If request has HMAC header
	if(isset($_SERVER["HTTP_NR_HASH"])) {
		// Save form
		$form = $_POST;

		// Remove base64 blobs
		if(isset($_POST["photos"]))  unset($_POST["photos"]);

		// Verify HMAC
		$hash = hash_hmac('sha512', json_encode($_POST, JSON_NUMERIC_CHECK), API_SECRET);
		header("NR-Hash: $hash");

		// If invalid HMAC, nullify form
		if($hash != $_SERVER["HTTP_NR_HASH"])  $form = null;
	}
	else {
		die();
	}

	// Handle form by context
	switch($form["formContext"]) {

		case "client-registration":
			// Handle client registration
			$user = new NRClient();

			$data = $user->registerUser($form["username"], $form["email"], $form["password"]);

			echo json_encode($data);
			break;

		case "client-login":
			// Handle client authentication
			$user = new NRClient();

			$data = $user->authenticateUser($form["username"], $form["password"]);

			echo json_encode($data);
			break;

		case "client-session-check":
			// Validate client session
			$user = new NRClient();

			$data = $user->validateSession($form["userId"], $form["usernameHash"]);

			echo json_encode($data);
			break;

		case "artist-registration":
			// TODO: Handle artist registration
			break;

		case "artist-login":
			// TODO: Handle artist authentication
			break;

		case "fcm-registration":
			// TODO: Handle new FCM registration
			$fcm = new NRFCM();

			$data = $fcm->registerFcmId($form["id"], $form["userId"]);

			echo json_encode($data);
			break;

		case "fcm-client-id-fetch":
			// Get client FCM ID's
			$fcm = new NRFCM();

			$data = $fcm->getFcmId($form["type"], $form["options"]);

			echo json_encode($data);
			break;
			
		case "artist-portfolio-addition":
			// TODO: Handle artist portfolio addition
			break;

		case "new-card":
			// TODO: Handle new payment type entry
			break;

		case "event-form":
			// TODO: Handle event form
			$event = new NREvent();

			$data = $event->save($form);

			echo json_encode($data);
			break;

		case "artist-location":
			// TODO: Handle new artist location
			break;

		case "artist-apply-job":
			// TODO: Handle artist applying for job
			break;

		default:
			// No form context given
			echo json_encode("No data given in POST request.");
			break;
	}
?>
