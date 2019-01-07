<?php
	// Allow all origin and headers
	header('Access-Control-Allow-Origin: *');
	header('Access-Control-Allow-Headers: *');

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

	// If request has HMAC header
	if(isset($_SERVER["HTTP_NR_HASH"])) {

		// Verify HMAC
		$hash = hash_hmac('sha512', file_get_contents('php://input'), API_SECRET);
		header("NR-Hash: $hash");

		// If invalid HMAC, nullify form
		if($hash === $_SERVER["HTTP_NR_HASH"]) {
			// Decode form
			$form = json_decode(file_get_contents('php://input'), true);
		} else {
			// Nullify form
			http_response_code(401);
			$form = null;
		}

	} else {
		http_response_code(401);
		die(json_encode("No authorization header present."));
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

			$data = $user->register($form);

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
			// Handle new artist location
			$artist = new NRArtist();

			$data = $artist->saveLocation($form);

			echo json_encode($data, JSON_NUMERIC_CHECK | JSON_UNESCAPED_SLASHES);
			break;

		case "artist-location-fetch":
			// Get artist locations
			$artist = new NRArtist();

			$data = $artist->getLocations($form);

			echo json_encode($data, JSON_NUMERIC_CHECK | JSON_UNESCAPED_SLASHES);
			break;

		case "artist-location-delete":
			// Delete artist location
			$artist = new NRArtist();

			$data = $artist->deleteLocation($form);

			echo json_encode($data, JSON_NUMERIC_CHECK | JSON_UNESCAPED_SLASHES);
			break;

		case "artist-apply-job":
			// Handle artist applying for job
			$event = new NREvent();
			
			$data = $event->apply($form);

			echo json_encode($data, JSON_NUMERIC_CHECK | JSON_UNESCAPED_SLASHES);
			break;

		case "artist-cancel-job":
			// Handle artist cancel job
			$event = new NREvent();

			$data = $event->cancel($form);

			echo json_encode($data, JSON_NUMERIC_CHECK | JSON_UNESCAPED_SLASHES);
			break;

		case "artist-fetch-new-events":
			// Handle artist fetching new relevant events
			$events = new NREvent();

			$data = $events->getNew($form);

			echo json_encode($data, JSON_NUMERIC_CHECK | JSON_UNESCAPED_SLASHES);
			break;
			
		case "artist-portfolio-addition":
			// TODO: Handle artist portfolio addition
			break;

		case "artist-stripe-id":
			// Handle saving artist stripe ID
			$artist = new NRArtist();

			$data = $artist->saveStripeInfo($form);

			echo json_encode($data, JSON_NUMERIC_CHECK | JSON_UNESCAPED_SLASHES);
			break;

		case "fcm-token-registration":
			// Handle FCM Token Saving
			$fcm = new NRFCM();

			$data = $fcm->registerToken($form);

			echo json_encode($data, JSON_NUMERIC_CHECK | JSON_UNESCAPED_SLASHES);
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

		case "event-recently-completed":
			// Handle fetch recently completed events
			$data = NREvent::getRecentlyCompletedEvents($form);

			echo json_encode($data, JSON_NUMERIC_CHECK | JSON_UNESCAPED_SLASHES);
			break;

		case "event-packages-get":
			// Get event packages
			$data = NREvent::getPackages();

			echo json_encode($data, JSON_NUMERIC_CHECK | JSON_UNESCAPED_SLASHES);
			break;

		case "event-artist-rating":
			// Save artist rating
			$data = NREvent::artistRating($form);

			echo json_encode($data, JSON_NUMERIC_CHECK | JSON_UNESCAPED_SLASHES);
			break;

		case "event-client-rating":
			// Save client rating
			$data = NREvent::clientRating($form);

			echo json_encode($data, JSON_NUMERIC_CHECK | JSON_UNESCAPED_SLASHES);
			break;

		case "event-client-attendance":
			// Save client attendance
			$data = NREvent::saveClientAttendance($form);

			echo json_encode($data, JSON_NUMERIC_CHECK | JSON_UNESCAPED_SLASHES);
			break;

		case "event-artist-attendance":
			// Save artist attendance
			$data = NREvent::saveArtistAttendance($form);

			echo json_encode($data, JSON_NUMERIC_CHECK | JSON_UNESCAPED_SLASHES);
			break;

		case "roles-get":
			// Get available artist roles
			$data = NRArtist::getRoles();

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
