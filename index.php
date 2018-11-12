<?php
	header('Access-Control-Allow-Origin: *');
	header('Access-Control-Allow-Headers: *');
	require_once "inc/class-client.php";

	define("API_SECRET", '\3"dCwhe/B?g-KLT<h%:Wfz)3CY}^}~*');

	if(isset($_SERVER["HTTP_NR_HASH"])) {
		$form = $_POST;
		
		$hash = hash_hmac('sha512', json_encode($form), API_SECRET);

		if($hash != $_SERVER["HTTP_NR_HASH"]) {
			$form = null;
		}
	}

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
			break;

		case "artist-portfolio-addition":
			// TODO: Handle artist portfolio addition
			break;
		
		case "new-card":
			// TODO: Handle new payment type entry
			break;

		case "event-form":
			// TODO: Handle event form
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
