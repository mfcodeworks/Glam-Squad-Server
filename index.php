<?php
	header('Access-Control-Allow-Origin: *');
	require_once "inc/database-interface.php";

	if( isset($_POST) && $_POST != "" ) {

		switch($_POST['form-context']) {

			case "client-registration":
				// TODO: Handle client registration
				break;

			case "artist-registration":
				// TODO: Handle artist registration
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
				echo "No data given in POST request.";
				break;
		}
	}
	else {
		echo "No data given in POST request.";
	}

	echo json_encode($_POST);
	echo json_encode($_FILES);
?>
