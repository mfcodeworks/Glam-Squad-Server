<?php
	// Allow all origin and headers
	header('Access-Control-Allow-Origin: *');
	header('Access-Control-Allow-Headers: *');

	// Paths
	define('PROJECT_ROOT', dirname(dirname(dirname(__FILE__))));
	define('PROJECT_INC', PROJECT_ROOT . '/inc/');
	define('PROJECT_LIB', PROJECT_ROOT . '/vendor/');

	// Require classes
	require_once PROJECT_INC . "config.php";
	require_once PROJECT_INC . "DegreeDistanceFinder.php";
	require_once PROJECT_INC . "Mailer.php";
	require_once PROJECT_INC . "NRArtist.php";
	require_once PROJECT_INC . "NRClient.php";
	require_once PROJECT_INC . "NREvent.php";
	require_once PROJECT_INC . "NRFCM.php";
	require_once PROJECT_INC . "NRImage.php";
	require_once PROJECT_INC . "NRPackage.php";
    require_once PROJECT_LIB . "autoload.php";
    
	/**
	 * API: Slim 3 RESTful API implementation
	 */
    $api = new \Slim\App;

    /** 
     * FIXME: Reactive after go-live
     *  // HMAC check for queries 
     *  $api->add(function ($request, $response, $next) {
     *      // Get HMAC sent with request
     *      $hmac = $request->getHeader("NR_HASH");
     *
     *      // Calculate HMAC of message with API key
     *      $hash = hash_hmac('sha512', $request->getBody(), API_SECRET);
     *
     *      // If HMAC is correct proceed
     *      if($hash === $hmac) {
     *          return $next($request, $response);
     *      // If HMAC incorrect return 401 Unauthorized
     *      } else {
     *          return $response->withStatus(401)
     *              ->withHeader("NR-Hash", $hash)
     *              ->write("No Authorization Header");
     *      }
     *  });
     */

    /**
     * CLIENT: Client Class Functions
     */
    $api->post('/client', function($request, $response, $args) {
        // Get POST form
        $form = $request->getParsedBody();

        // Create return variable from NRClient response
        $return = json_encode(
            (new NRClient)->register($form["username"], $form["email"], $form["password"]), 
            JSON_NUMERIC_CHECK | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT
        );

        return $response->getBody()->write($return);
    });
    $api->post('/client/authenticate', function($request, $response, $args) {
        // Get POST form
        $form = $request->getParsedBody();

        // Create return variable from NRClient response
        $return = json_encode(
            (new NRClient)->authenticate($form["username"], $form["password"]), 
            JSON_NUMERIC_CHECK | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT
        );

        return $response->getBody()->write($return);
    });
    $api->get('/client/{userId}', function($request, $response, $args) {
        // Get client from ID
        $return = json_encode(
            (new NRClient)->get($args), 
            JSON_NUMERIC_CHECK | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT
        );

        return $response->getBody()->write($return);
    });
    $api->put('/client/{userId}', function($request, $response, $args) {
        // Get PUT form
        $form = $request->getParsedBody();

        // Update Client Info 
        $return = json_encode(
            (new NRClient)->update($form),
            JSON_NUMERIC_CHECK | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT
        );

        return $response->getBody()->write($return);
    });

    // Run API
    $api->run();
?>