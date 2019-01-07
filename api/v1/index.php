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
     *          return $next($request, $response)
     *              ->withHeader("NR-Hash", $hash);
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
    $api->post('/clients', function($request, $response, $args) {
        // Get POST form
        $form = $request->getParsedBody();

        // Register new client
        $return = (new NRClient)->register($form["username"], $form["email"], $form["password"]);

        return $response->withJson($return, 200, JSON_PRETTY_PRINT);
    });
    $api->post('/clients/authenticate', function($request, $response, $args) {
        // Get POST form
        $form = $request->getParsedBody();

        // Authenticate client
        $return = (new NRClient)->authenticate($form["username"], $form["password"]);

        return $response->withJson($return, 200, JSON_PRETTY_PRINT);
    });
    $api->get('/clients/{id}', function($request, $response, $args) {
        // Get client from ID
        $return = (new NRClient)->get($args);

        return $response->withJson($return, 200, JSON_PRETTY_PRINT);
    });
    $api->put('/clients/{id}', function($request, $response, $args) {
        // Get PUT form
        $form = $request->getParsedBody();

        // Merge URL arguments and form parameters
        $form["id"] = $args["id"];

        // Update Client Info 
        $return = (new NRClient)->update($form);

        return $response->withJson($return, 200, JSON_PRETTY_PRINT);
    });
    $api->post('/clients/{id}/validate', function($request, $response, $args) {
        // Get POST form
        $form = $request->getParsedBody();

        // Validate Client Session 
        $return = (new NRClient)->validateSession($args["id"], $form["usernameHash"]);

        return $response->withJson($return, 200, JSON_PRETTY_PRINT);
    });
    $api->put('/clients/{id}/payment', function($request, $response, $args) {
        // Get PUT form
        $form = $request->getParsedBody();

        // Merge URL arguments and form parameters
        $form["id"] = $args["id"];

        // Save Client Payment Info 
        $return = (new NRClient)->savePaymentInfo($form);

        return $response->withJson($return, 200, JSON_PRETTY_PRINT);
    });
    $api->delete('/clients/{id}/payment/{cardId}', function($request, $response, $args) {
        // Delete Client Payment Info 
        $return = (new NRClient)->deleteCard($args);

        return $response->withJson($return, 200, JSON_PRETTY_PRINT);
    });

    /**
     * ARTIST: Artist Class Functions
     */
    $api->post('/artists', function($request, $response, $args) {
        // Get POST form
        $form = $request->getParsedBody();

        // Register new artist
        $return = (new NRArtist)->register($form);

        return $response->withJson($return, 200, JSON_PRETTY_PRINT);
    });
    $api->post('/artists/authenticate', function($request, $response, $args) {
        // Get POST form
        $form = $request->getParsedBody();

        // Authenticate artist
        $return = (new NRArtist)->authenticate($form["username"], $form["password"]);

        return $response->withJson($return, 200, JSON_PRETTY_PRINT);
    });
    $api->get('/artists/{id}', function($request, $response, $args) {
        // Get artist by ID
        $return = (new NRArtist)->get($args);

        return $response->withJson($return, 200, JSON_PRETTY_PRINT);
    });
    $api->put('/artists/{id}', function($request, $response, $args) {
        // Get PUT form
        $form = $request->getParsedBody();

        // Merge form with URL arguments
        $form["id"] = $args["id"];

        // Update artist info
        $return = (new NRArtist)->update($form);

        return $response->withJson($return, 200, JSON_PRETTY_PRINT);
    });
    $api->post('/artists/{id}/validate', function($request, $response, $args) {
        // Get POST form
        $form = $request->getParsedBody();

        // Validate Artist Session 
        $return = (new NRArtist)->validateSession($args["id"], $form["usernameHash"]);

        return $response->withJson($return, 200, JSON_PRETTY_PRINT);
    });
    $api->put('/artists/{id}/locations', function($request, $response, $args) {
        // Get PUT form
        $form = $request->getParsedBody();

        // Merge form and URL arguments
        $form["id"] = $args["id"];

        // Save Artist Location
        $return = (new NRArtist)->saveLocation($form);

        return $response->withJson($return, 200, JSON_PRETTY_PRINT);
    });
    $api->get('/artists/{id}/locations', function($request, $response, $args) {
        // Merge form and URL arguments
        $form["id"] = $args["id"];

        // Get Artist Locations
        $return = (new NRArtist)->getLocations($form);

        return $response->withJson($return, 200, JSON_PRETTY_PRINT);
    });
    $api->delete('/artists/{id}/locations/{loc_id}', function($request, $response, $args) {
        // Delete Artist Location
        $return = (new NRArtist)->deleteLocation($args);

        return $response->withJson($return, 200, JSON_PRETTY_PRINT);
    });

    // Run API
    $api->run();
?>