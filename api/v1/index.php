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

    // HMAC check for queries 
    $api->add(function ($request, $response, $next) {
        // Get HMAC sent with request
        $hmac = $request->getHeader("NR_HASH");

        // Calculate HMAC of message with API key
        $hash = hash_hmac('sha512', $request->getBody(), API_SECRET);

        // If HMAC is correct proceed
        if($hash === $hmac) {
            return $next($request, $response);
        // If HMAC incorrect return 401 Unauthorized
        } else {
            return $response->withStatus(401)
                ->withHeader("NR-HASH", $hash)
                ->write("No Authorization Header");
        }
    });

    /** 
     * FIXME: Test routes
     * Define sample app routes
     */

    // Get /hello/[name of hello object to retreieve]
    $api->get('/hello/{name}', function($request, $response, $args) {
        return $response->getBody()->write(json_encode("Hello {$args["name"]}"));
    });
    // Post /hello/[name of object to save]
    $api->post('/hello/{name}', function($request, $response, $args) {
        return $response->getBody()->write(json_encode("Hello {$args["name"]} Saved"));
    });
    // Put /hello/[name of object to update]
    $api->put('/hello/{name}', function($request, $response, $args) {
        return $response->getBody()->write(json_encode("Hello {$args["name"]} Updated"));
    });
    // Delete /hello/[name of object to delete]
    $api->delete('/hello/{name}', function($request, $response, $args) {
        return $response->getBody()->write(json_encode("Hello {$args["name"]} Deleted"));
    });

    /**
     * API: Production routes for Glam Squad API
     */

    // Run API
    $api->run();
?>