<?php
	header('Access-Control-Allow-Origin: *');
	include "inc/database-interface.php";

	if( isset($_POST['query']) && $_POST['query'] !== "" ) {
		// Run SQL query
		$response = runSQLQuery( $_POST['query'] );
		// Echo JSON response
		echo json_encode( $response );
	}
	else {
		echo "No data given in POST request.";
	}
?>
