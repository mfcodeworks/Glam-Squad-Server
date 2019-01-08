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
     * FIXME: Reactivate after go-live
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
     * CLIENT: Client Functions
     */
    $api->post('/clients', function($request, $response, $args) {
        // Get POST form
        $form = $request->getParsedBody();

        // Register new client
        $return = (new NRClient)->register($form["username"], $form["email"], $form["password"]);

        return $response->withJson($return, 200, JSON_PRETTY_PRINT | JSON_NUMERIC_CHECK | JSON_UNESCAPED_SLASHES);
    });
    $api->post('/clients/authenticate', function($request, $response, $args) {
        // Get POST form
        $form = $request->getParsedBody();

        // Authenticate client
        $return = (new NRClient)->authenticate($form["username"], $form["password"]);

        return $response->withJson($return, 200, JSON_PRETTY_PRINT | JSON_NUMERIC_CHECK | JSON_UNESCAPED_SLASHES);
    });
    $api->get('/clients/{id: [0-9]+}', function($request, $response, $args) {
        // Get client from ID
        $return = (new NRClient)->get($args);

        return $response->withJson($return, 200, JSON_PRETTY_PRINT | JSON_NUMERIC_CHECK | JSON_UNESCAPED_SLASHES);
    });
    $api->put('/clients/{id: [0-9]+}', function($request, $response, $args) {
        // Get PUT form
        $form = $request->getParsedBody();

        // Merge URL arguments and form parameters
        $form["id"] = $args["id"];

        // Update Client Info 
        $return = (new NRClient)->update($form);

        return $response->withJson($return, 200, JSON_PRETTY_PRINT | JSON_NUMERIC_CHECK | JSON_UNESCAPED_SLASHES);
    });
    $api->post('/clients/{id: [0-9]+}/validate', function($request, $response, $args) {
        // Get POST form
        $form = $request->getParsedBody();

        // Validate Client Session 
        $return = (new NRClient)->validateSession($args["id"], $form["usernameHash"]);

        return $response->withJson($return, 200, JSON_PRETTY_PRINT | JSON_NUMERIC_CHECK | JSON_UNESCAPED_SLASHES);
    });
    $api->put('/clients/{id: [0-9]+}/payment', function($request, $response, $args) {
        // Get PUT form
        $form = $request->getParsedBody();

        // Merge URL arguments and form parameters
        $form["id"] = $args["id"];

        // Save Client Payment Info 
        $return = (new NRClient)->savePaymentInfo($form);

        return $response->withJson($return, 200, JSON_PRETTY_PRINT | JSON_NUMERIC_CHECK | JSON_UNESCAPED_SLASHES);
    });
    $api->delete('/clients/{id: [0-9]+}/payment/{cardId: [0-9]+}', function($request, $response, $args) {
        // Delete Client Payment Info 
        $return = (new NRClient)->deleteCard($args);

        return $response->withJson($return, 200, JSON_PRETTY_PRINT | JSON_NUMERIC_CHECK | JSON_UNESCAPED_SLASHES);
    });
    $api->put('/clients/{id: [0-9]+}/fcm/topic', function($request, $response, $args) {
        // Get PUT form
        $form = $request->getParsedBody();

        // Merge form and URL arguments
        $form["id"] = $args["id"];
        $form["type"] = "client";

        // Save client FCM topic
        $return = (new NRFCM)->registerTopic($form);

        return $response->withJson($return, 200, JSON_PRETTY_PRINT | JSON_NUMERIC_CHECK | JSON_UNESCAPED_SLASHES);
    });
    $api->get('/clients/{id: [0-9]+}/fcm/topic', function($request, $response, $args) {
        // Set fcm fetch type
        $args["type"] = "client";

        // Get client FCM topic
        $return = (new NRFCM)->getTopics($args);

        return $response->withJson($return, 200, JSON_PRETTY_PRINT | JSON_NUMERIC_CHECK | JSON_UNESCAPED_SLASHES);
    });
    $api->get('/clients/{id: [0-9]+}/events', function($request, $response, $args) {
        // Get client events
        $return = (new NREvent)->get($args);

        return $response->withJson($return, 200, JSON_PRETTY_PRINT | JSON_NUMERIC_CHECK | JSON_UNESCAPED_SLASHES);
    });
    $api->delete('/clients/{id: [0-9]+}/events/{eventId: [0-9]+}', function($request, $response, $args) {
        // Delete event 
        $return = (new NREvent)->delete($args);

        return $response->withJson($return, 200, JSON_PRETTY_PRINT | JSON_NUMERIC_CHECK | JSON_UNESCAPED_SLASHES);
    });
    $api->get('/clients/{id: [0-9]+}/events/recent/unpaid', function($request, $response, $args) {
        // Set events get type 
        $args["type"] = "client";

        // Get recently completed unpaid events 
        $return = (new NREvent)->getRecentlyCompletedEvents($args);

        return $response->withJson($return, 200, JSON_PRETTY_PRINT | JSON_NUMERIC_CHECK | JSON_UNESCAPED_SLASHES);
    });

    /**
     * ARTIST: Artist Functions
     */
    $api->post('/artists', function($request, $response, $args) {
        // Get POST form
        $form = $request->getParsedBody();

        // Register new artist
        $return = (new NRArtist)->register($form);

        return $response->withJson($return, 200, JSON_PRETTY_PRINT | JSON_NUMERIC_CHECK | JSON_UNESCAPED_SLASHES);
    });
    $api->post('/artists/authenticate', function($request, $response, $args) {
        // Get POST form
        $form = $request->getParsedBody();

        // Authenticate artist
        $return = (new NRArtist)->authenticate($form["username"], $form["password"]);

        return $response->withJson($return, 200, JSON_PRETTY_PRINT | JSON_NUMERIC_CHECK | JSON_UNESCAPED_SLASHES);
    });
    $api->get('/artists/{id: [0-9]+}', function($request, $response, $args) {
        // Get artist by ID
        $return = (new NRArtist)->get($args);

        return $response->withJson($return, 200, JSON_PRETTY_PRINT | JSON_NUMERIC_CHECK | JSON_UNESCAPED_SLASHES);
    });
    $api->put('/artists/{id: [0-9]+}', function($request, $response, $args) {
        // Get PUT form
        $form = $request->getParsedBody();

        // Merge form with URL arguments
        $form["id"] = $args["id"];

        // Update artist info
        $return = (new NRArtist)->update($form);

        return $response->withJson($return, 200, JSON_PRETTY_PRINT | JSON_NUMERIC_CHECK | JSON_UNESCAPED_SLASHES);
    });
    $api->post('/artists/{id: [0-9]+}/validate', function($request, $response, $args) {
        // Get POST form
        $form = $request->getParsedBody();

        // Validate Artist Session 
        $return = (new NRArtist)->validateSession($args["id"], $form["usernameHash"]);

        return $response->withJson($return, 200, JSON_PRETTY_PRINT | JSON_NUMERIC_CHECK | JSON_UNESCAPED_SLASHES);
    });
    $api->put('/artists/{id: [0-9]+}/locations', function($request, $response, $args) {
        // Get PUT form
        $form = $request->getParsedBody();

        // Merge form and URL arguments
        $form["id"] = $args["id"];

        // Save Artist Location
        $return = (new NRArtist)->saveLocation($form);

        return $response->withJson($return, 200, JSON_PRETTY_PRINT | JSON_NUMERIC_CHECK | JSON_UNESCAPED_SLASHES);
    });
    $api->get('/artists/{id: [0-9]+}/locations', function($request, $response, $args) {
        // Merge form and URL arguments
        $form["id"] = $args["id"];

        // Get Artist Locations
        $return = (new NRArtist)->getLocations($form);

        return $response->withJson($return, 200, JSON_PRETTY_PRINT | JSON_NUMERIC_CHECK | JSON_UNESCAPED_SLASHES);
    });
    $api->delete('/artists/{id: [0-9]+}/locations/{loc_id: [0-9]+}', function($request, $response, $args) {
        // Delete Artist Location
        $return = (new NRArtist)->deleteLocation($args);

        return $response->withJson($return, 200, JSON_PRETTY_PRINT | JSON_NUMERIC_CHECK | JSON_UNESCAPED_SLASHES);
    });
    $api->put('/artists/{id: [0-9]+}/portfolio', function($request, $response, $args) {
        // TODO: Implement artist portfolio update
    });
    $api->put('/artists/{id: [0-9]+}/payment/id', function($request, $response, $args) {
        // Get PUT form
        $form = $request->getParsedBody();

        // Merge form and URL arguments
        $form["id"] = $args["id"];

        // Save Artist Stripe payment ID
        $return = (new NRArtist)->saveStripeInfo($form);

        return $response->withJson($return, 200, JSON_PRETTY_PRINT | JSON_NUMERIC_CHECK | JSON_UNESCAPED_SLASHES);
    });
    $api->put('/artists/{id: [0-9]+}/fcm/token', function($request, $response, $args) {
        // Get PUT form
        $form = $request->getParsedBody();

        // Merge form and URL arguments
        $form["id"] = $args["id"];

        // Save Artist FCM token
        $return = (new NRFCM)->registerToken($form);

        return $response->withJson($return, 200, JSON_PRETTY_PRINT | JSON_NUMERIC_CHECK | JSON_UNESCAPED_SLASHES);
    });
    $api->put('/artists/{id: [0-9]+}/fcm/topic', function($request, $response, $args) {
        // Get PUT form
        $form = $request->getParsedBody();

        // Merge form and URL arguments
        $form["id"] = $args["id"];
        $form["type"] = "artist";

        // Save artist FCM topic
        $return = (new NRFCM)->registerTopic($form);

        return $response->withJson($return, 200, JSON_PRETTY_PRINT | JSON_NUMERIC_CHECK | JSON_UNESCAPED_SLASHES);
    });
    $api->get('/artists/{id: [0-9]+}/fcm/topic', function($request, $response, $args) {
        // Set fcm fetch type
        $args["type"] = "artist";

        // Get artist FCM topic
        $return = (new NRFCM)->getTopics($args);

        return $response->withJson($return, 200, JSON_PRETTY_PRINT | JSON_NUMERIC_CHECK | JSON_UNESCAPED_SLASHES);
    });
    $api->get('/artists/{id: [0-9]+}/events/recent/unpaid', function($request, $response, $args) {
        // Set events get type 
        $args["type"] = "artist";

        // Get recently completed unpaid events 
        $return = (new NREvent)->getRecentlyCompletedEvents($args);

        return $response->withJson($return, 200, JSON_PRETTY_PRINT | JSON_NUMERIC_CHECK | JSON_UNESCAPED_SLASHES);
    });
    $api->get('/artists/roles', function($request, $response, $args) {
        // Get artist roles
        $return = NRArtist::getRoles();

        return $response->withJson($return, 200, JSON_PRETTY_PRINT | JSON_NUMERIC_CHECK | JSON_UNESCAPED_SLASHES);
    });

    /** 
     * EVENT: Event Functions
     */
    $api->post('/events', function($request, $response, $args) {
        // Get POST form
        $form = $request->getParsedBody();

        // Create new event 
        $return = (new NREvent)->save($form);

        return $response->withJson($return, 200, JSON_PRETTY_PRINT | JSON_NUMERIC_CHECK | JSON_UNESCAPED_SLASHES);
    });
    $api->get('/events/{id: [0-9]+}', function($request, $response, $args) {
        // Get Event
        $return = (new NREvent)->getSingle($args["id"]);

        return $response->withJson($return, 200, JSON_PRETTY_PRINT | JSON_NUMERIC_CHECK | JSON_UNESCAPED_SLASHES);
    });
    $api->put('/events/{id: [0-9]+}', function($request, $response, $args) {
        // Get PUT form
        $form = $request->getParsedBody();

        // Merge form and URL arguments
        $form["id"] = $args["id"];

        // Update event 
        $return = (new NREvent)->update($form);

        return $response->withJson($return, 200, JSON_PRETTY_PRINT | JSON_NUMERIC_CHECK | JSON_UNESCAPED_SLASHES);
    });
    $api->put('/events/{id: [0-9]+}/ratings/artist', function($request, $response, $args) {
        // Get PUT form
        $form = $request->getParsedBody();

        // Merge form and URL arguments
        $form["id"] = $args["id"];

        // Set event artist rating
        $return = NREvent::artistRating($form);

        return $response->withJson($return, 200, JSON_PRETTY_PRINT | JSON_NUMERIC_CHECK | JSON_UNESCAPED_SLASHES);
    });
    $api->put('/events/{id: [0-9]+}/ratings/client', function($request, $response, $args) {
        // Get PUT form
        $form = $request->getParsedBody();

        // Merge form and URL arguments
        $form["id"] = $args["id"];

        // Set event client rating
        $return = NREvent::clientRating($form);

        return $response->withJson($return, 200, JSON_PRETTY_PRINT | JSON_NUMERIC_CHECK | JSON_UNESCAPED_SLASHES);
    });
    $api->put('/events/{id: [0-9]+}/attendance/artist', function($request, $response, $args) {
        // Get PUT form
        $form = $request->getParsedBody();

        // Merge form and URL arguments
        $form["id"] = $args["id"];

        // Save event artist attendance
        $return = NREvent::saveArtistAttendance($form);

        return $response->withJson($return, 200, JSON_PRETTY_PRINT | JSON_NUMERIC_CHECK | JSON_UNESCAPED_SLASHES);
    });
    $api->put('/events/{id: [0-9]+}/attendance/client', function($request, $response, $args) {
        // Get PUT form
        $form = $request->getParsedBody();

        // Merge form and URL arguments
        $form["id"] = $args["id"];

        // Save event client attendance
        $return = NREvent::saveClientAttendance($form);

        return $response->withJson($return, 200, JSON_PRETTY_PRINT | JSON_NUMERIC_CHECK | JSON_UNESCAPED_SLASHES);
    });
    $api->post('/events/{id: [0-9]+}/apply', function($request, $response, $args) {
        // Get POST form
        $form = $request->getParsedBody();

        // Merge form and URL arguments
        $form["id"] = $args["id"];

        // Artist apply for job
        $return = (new NREvent)->apply($form);

        return $response->withJson($return, 200, JSON_PRETTY_PRINT | JSON_NUMERIC_CHECK | JSON_UNESCAPED_SLASHES);
    });
    $api->post('/events/{id: [0-9]+}/artist/{userId: [0-9]+}/cancel', function($request, $response, $args) {
        // Get POST form
        $form = $request->getParsedBody();

        // Artist cancel job booking
        $return = (new NREvent)->cancel($args);

        return $response->withJson($return, 200, JSON_PRETTY_PRINT | JSON_NUMERIC_CHECK | JSON_UNESCAPED_SLASHES);
    });
    $api->get('/events/new/artist/{userId}', function($request, $response, $args) {
        // Get events near artist from ID
        $return = (new NREvent)->getNew($args);

        return $response->withJson($return, 200, JSON_PRETTY_PRINT | JSON_NUMERIC_CHECK | JSON_UNESCAPED_SLASHES);
    });
    $api->get('/events/packages', function($request, $response, $args) {
        // Get event packages
        $return = NREvent::getPackages();        
        
        return $response->withJson($return, 200, JSON_PRETTY_PRINT | JSON_NUMERIC_CHECK | JSON_UNESCAPED_SLASHES);
    });

    // Run API
    $api->run();
?>