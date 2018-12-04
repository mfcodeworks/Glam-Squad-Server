<?php
	// Allow all origin and headers
	header('Access-Control-Allow-Origin: *');
	header('Access-Control-Allow-Headers: *');

	// Paths
	define('PROJECT_ROOT', dirname(__FILE__));
	define('PROJECT_INC', PROJECT_ROOT . '/inc/');
	define('PROJECT_LIB', PROJECT_ROOT . '/vendor/');

	// Require classes
	require_once PROJECT_INC . "config.php";
	require_once PROJECT_INC . "class-client.php";
	require_once PROJECT_INC . "class-artist.php";
	require_once PROJECT_INC . "class-fcm.php";
	require_once PROJECT_INC . "class-event.php";
	require_once PROJECT_INC . "class-package.php";
	require_once PROJECT_INC . "mail.php";

	// If request has HMAC header
	if(isset($_SERVER["HTTP_NR_HASH"])) {
		// Save form
		$form = json_decode(file_get_contents('php://input'), true);

		// Verify HMAC
		$hash = hash_hmac('sha512', file_get_contents('php://input'), API_SECRET);
		header("NR-Hash: $hash");

		// If invalid HMAC, nullify form
		if($hash != $_SERVER["HTTP_NR_HASH"]) 
			$form = null;
	}
	else {
		die();
	}

	// Handle form by context
	switch($form["formContext"]) {

		case "client-registration":
			// Handle client registration
			$user = new NRClient();

			$data = $user->register($form["username"], $form["email"], $form["password"]);

			echo json_encode($data, JSON_NUMERIC_CHECK | JSON_UNESCAPED_SLASHES);
			break;

		case "client-login":
			// Handle client authentication
			$user = new NRClient();

			$data = $user->authenticate($form["username"], $form["password"]);

			echo json_encode($data, JSON_NUMERIC_CHECK | JSON_UNESCAPED_SLASHES);
			break;

		case "client-info-get":
			// Get client info
			$user = new NRClient();

			$data = $user->get($form);

			echo json_encode($data, JSON_NUMERIC_CHECK | JSON_UNESCAPED_SLASHES);
			break;

		case "client-info-update":
			// Update client info
			$user = new NRClient();

			$data = $user->update($form);

			echo json_encode($data, JSON_NUMERIC_CHECK | JSON_UNESCAPED_SLASHES);
			break;

		case "client-session-check":
			// Validate client session
			$user = new NRClient();

			$data = $user->validateSession($form["userId"], $form["usernameHash"]);

			echo json_encode($data, JSON_NUMERIC_CHECK | JSON_UNESCAPED_SLASHES);
			break;

		case "save-client-payment-info":
			$client = new NRClient();

			$data = $client->savePaymentInfo($form);

			echo json_encode($data, JSON_NUMERIC_CHECK | JSON_UNESCAPED_SLASHES);
			break;

		case "delete-card":
			$client = new NRClient();

			$data = $client->deleteCard($form);

			echo json_encode($data, JSON_NUMERIC_CHECK | JSON_UNESCAPED_SLASHES);
			break;

		case "artist-registration":
			// Handle artist registration
			$user = new NRArtist();

			$data = $user->register($form["username"], $form["email"], $form["password"]);

			echo json_encode($data, JSON_NUMERIC_CHECK | JSON_UNESCAPED_SLASHES);
			break;

		case "artist-login":
			// Handle artist authentication
			$user = new NRArtist();

			$data = $user->authenticate($form["username"], $form["password"]);

			echo json_encode($data, JSON_NUMERIC_CHECK | JSON_UNESCAPED_SLASHES);
			break;

		case "artist-info-get":
			// Handle artist info get
			$user = new NRArtist();

			$data = $user->get($form);

			echo json_encode($data, JSON_NUMERIC_CHECK | JSON_UNESCAPED_SLASHES);
			break;

		case "artist-info-update":
			// Handle artist info update
			$user = new NRArtist();

			$data = $user->update($form);

			echo json_encode($data, JSON_NUMERIC_CHECK | JSON_UNESCAPED_SLASHES);
			break;

		case "artist-session-check":
			// Handle artist session check
			$user = new NRArtist();

			$data = $user->validateSession($form["userId"], $form["usernameHash"]);

			echo json_encode($data, JSON_NUMERIC_CHECK | JSON_UNESCAPED_SLASHES);
			break;

		case "artist-location":
			// TODO: Handle new artist location
			break;

		case "artist-apply-job":
			// TODO: Handle artist applying for job
			break;
			
		case "artist-portfolio-addition":
			// TODO: Handle artist portfolio addition
			break;

		case "artist-stripe-id":
			// TODO: Handle saving artist stripe ID	
			break;

		case "fcm-topic-registration":
			// Handle new FCM registration
			$fcm = new NRFCM();

			$data = $fcm->registerTopic($form);

			echo json_encode($data, JSON_NUMERIC_CHECK | JSON_UNESCAPED_SLASHES);
			break;

		case "fcm-topic-fetch":
			// Get FCM topics
			$fcm = new NRFCM();

			$data = $fcm->getTopics($form);

			echo json_encode($data, JSON_NUMERIC_CHECK | JSON_UNESCAPED_SLASHES);
			break;

		case "event-form":
			// Handle event form
			$event = new NREvent();

			$data = $event->save($form);

			echo json_encode($data, JSON_NUMERIC_CHECK | JSON_UNESCAPED_SLASHES);
			break;

		case "event-update":
			// Handle event update
			$event = new NREvent();

			$data = $event->update($form);

			echo json_encode($data, JSON_NUMERIC_CHECK | JSON_UNESCAPED_SLASHES);
			break;

		case "event-delete":
			// Handle event delete
			$event = new NREvent();

			$data = $event->delete($form);

			echo json_encode($data, JSON_NUMERIC_CHECK | JSON_UNESCAPED_SLASHES);
			break;
			break;

		case "event-get":
			// Handle event fetching
			$event = new NREvent();

			$data = $event->get($form);

			echo json_encode($data, JSON_NUMERIC_CHECK | JSON_UNESCAPED_SLASHES);
			break;

		case "admin-fetch-packages":
			$packages = new NRPackage();

			$data = $packages->get();

			echo json_encode($data, JSON_NUMERIC_CHECK | JSON_UNESCAPED_SLASHES);
			break;

		case "admin-save-package":
			$package = new NRPackage();

			$data = $package->save($form);

			echo json_encode($data, JSON_NUMERIC_CHECK | JSON_UNESCAPED_SLASHES);
			break;

		case "admin-delete-package":
			$package = new NRPackage();

			$data = $package->delete($form["id"]);

			echo json_encode($data, JSON_NUMERIC_CHECK | JSON_UNESCAPED_SLASHES);
			break;

		default:
			// No form context given
			echo json_encode("No data given in POST request.");
			break;
	}
?>
