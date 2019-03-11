<?php
	// Allow all origin and headers
	header('Access-Control-Allow-Origin: *');
	header('Access-Control-Allow-Headers: content-type,nr-hash,origin,referrer,user-agent,*');
	header('Access-Control-Allow-Methods: GET,POST,OPTIONS,PUT,DELETE');

	// Paths
    define('PROJECT_ROOT', dirname(dirname(dirname(__FILE__))));
    define('PROJECT_CONFIG', PROJECT_ROOT . '/config/');
	define('PROJECT_INC', PROJECT_ROOT . '/src/');
	define('PROJECT_LIB', PROJECT_ROOT . '/vendor/');

	// Require classes
	require_once PROJECT_CONFIG . "config.php";
    require_once PROJECT_LIB . "autoload.php";
	require_once PROJECT_INC . "DegreeDistanceFinder.php";
	require_once PROJECT_INC . "Mailer.php";
	require_once PROJECT_INC . "NRChat.php";
	require_once PROJECT_INC . "NRArtist.php";
	require_once PROJECT_INC . "NRClient.php";
	require_once PROJECT_INC . "NREvent.php";
	require_once PROJECT_INC . "NRFCM.php";
	require_once PROJECT_INC . "NRImage.php";
	require_once PROJECT_INC . "NRSpaces.php";
    require_once PROJECT_INC . "NRPackage.php";
    
	/**
	 * API: Slim 3 RESTful API implementation
	 */
    $api = new \Slim\App;

    // HMAC check for queries 
    $api->add(function ($request, $response, $next) {
        /**
         * Skip HMAC for same origin requests
         * 
         * Same origin requests 
         *  - lost-password.php does key checking before sending server data
         */

        // DEBUG: Measure exec time
        $time_start = microtime(true); 

        // If HMAC enabled check
        if(HMAC_ENABLED) {
            if($request->getHeader("ORIGIN") && $request->getHeader("ORIGIN")[0] === SERVER_URL)
                return $next($request, $response);
            // Check if preflight and respond 200
            if($request->isOptions() && strpos($request->getHeader("ACCESS_CONTROL_REQUEST_HEADERS")[0], "nr-hash") > -1) {
                return $response->withStatus(200);
            // If not preflight, check NR-Hash present
            } else if(!$request->getHeader("NR-HASH")) {
                return $response->withStatus(401)
                    ->write("No Authorization Header");
            }
    
            // Get HMAC sent with request & Calculate HMAC of message with API key
            $hmac = $request->getHeader("NR_HASH")[0];
            $hash = hash_hmac('sha512', $request->getBody(), API_SECRET);
    
            // TODO: Authorization check for authorized user and actions
    
            // If HMAC incorrect return 401 Unauthorized
            if(!hash_equals($hash, $hmac)) {
                return $response->withStatus(401)
                    ->withHeader("NR-HASH", $hash)
                    ->write("No Authorization Header");
            }
        }

        // If HMAC is correct proceed
        $response = $next($request, $response);
            
        // DEBUG: Measure exec time
        $time_end = microtime(true);
        $execution_time = ($time_end - $time_start);
        error_log("API Execution Time: $execution_time s");

        return $response
            ->withHeader("NR-HASH", $hash);
    });

    /**
     * CLIENT: Client Functions
     */
    $api->post('/clients', function($request, $response, $args) {
        // Get POST form
        $form = $request->getParsedBody();

        // Register new client
        $return = (new NRClient)->register($form["username"], $form["email"], $form["password"]);

        // Incase of duplicate ID delete any Redis association
        $redis = new Redis;
        $redis->connect(REDIS_HOST);
        $redis->delete("client-{$return["id"]}");

        return $response->withJson($return, 200, JSON_PRETTY_PRINT | JSON_NUMERIC_CHECK | JSON_UNESCAPED_SLASHES);
    });
    $api->post('/clients/auth/{type: [a-z]+}', function($request, $response, $args) {
        // Get POST form
        $form = $request->getParsedBody();

        // Register new client with social media
        switch($args["type"]) {
            case "facebook":
                $return = (new NRClient)->registerFacebook($form);
                break;
            
            case "twitter":
                $return = (new NRClient)->registerTwitter($form);
                break;

            case "google":
                $return = (new NRClient)->registerGoogle($form);
                break;
        }

        // Incase of duplicate ID delete any Redis association
        $redis = new Redis;
        $redis->connect(REDIS_HOST);
        $redis->delete("client-{$return["id"]}");

        return $response->withJson($return, 200, JSON_PRETTY_PRINT | JSON_NUMERIC_CHECK | JSON_UNESCAPED_SLASHES);
    });
    $api->post('/clients/authenticate', function($request, $response, $args) {
        // Get POST form
        $form = $request->getParsedBody();

        // Authenticate client
        $return = (new NRClient)->authenticate($form["username"], $form["password"]);

        return $response->withJson($return, 200, JSON_PRETTY_PRINT | JSON_NUMERIC_CHECK | JSON_UNESCAPED_SLASHES);
    });
    $api->post('/clients/forgot-password', function($request, $response, $args) {
        // Get POST form
        $form = $request->getParsedBody();

        // Do forgot password function
        $return = (new NRClient)->forgotPassword($form["username"]);

        return $response->withJson($return, 200, JSON_PRETTY_PRINT | JSON_NUMERIC_CHECK | JSON_UNESCAPED_SLASHES);
    });
    $api->get('/clients/{id: [0-9]+}', function($request, $response, $args) {
        // Get Client from Redis
        $redis = new Redis;
        $redis->connect(REDIS_HOST);
        $return = $redis->get("client-{$args["id"]}");
        if($return) return $response
            ->withStatus(200)
            ->withHeader('Content-type', 'application/json')
            ->write("{\"response\":true,\"error\":null,\"id\":0,\"data\":[$return]}");

        // Get client from ID
        $return = (new NRClient)->get($args);

        // Cache client data
        if($return["data"]) {
            $redis->set(
                "client-{$return["data"][0]["id"]}", 
                json_encode($return["data"][0], JSON_PRETTY_PRINT | JSON_NUMERIC_CHECK | JSON_UNESCAPED_SLASHES),
                REDIS_TIMEOUT
            );
        }

        return $response->withJson($return, 200, JSON_PRETTY_PRINT | JSON_NUMERIC_CHECK | JSON_UNESCAPED_SLASHES);
    });
    $api->put('/clients/{id: [0-9]+}', function($request, $response, $args) {
        // Get PUT form
        $form = $request->getParsedBody();

        // Merge URL arguments and form parameters
        $form["id"] = $args["id"];

        // Update Client Info 
        $return = (new NRClient)->update($form);

        // Cache client data
        if($return["data"]) {
            $redis = new Redis;
            $redis->connect(REDIS_HOST);
            $redis->set(
                "client-{$return["data"][0]["id"]}", 
                json_encode($return["data"][0], JSON_PRETTY_PRINT | JSON_NUMERIC_CHECK | JSON_UNESCAPED_SLASHES),
                REDIS_TIMEOUT
            );
        }

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

        // Empty Redis cache for Client
        $redis = new Redis;
        $redis->connect(REDIS_HOST);
        $redis->delete("client-{$args["id"]}");

        return $response->withJson($return, 200, JSON_PRETTY_PRINT | JSON_NUMERIC_CHECK | JSON_UNESCAPED_SLASHES);
    });
    $api->delete('/clients/{id: [0-9]+}/payment/{token}', function($request, $response, $args) {
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

        // Empty Redis cache for Client FCM
        $redis = new Redis;
        $redis->connect(REDIS_HOST);
        $redis->delete("client-{$args["id"]}-fcm-topics");

        return $response->withJson($return, 200, JSON_PRETTY_PRINT | JSON_NUMERIC_CHECK | JSON_UNESCAPED_SLASHES);
    });
    $api->get('/clients/{id: [0-9]+}/fcm/topic', function($request, $response, $args) {
        // Get Client FCM Topics Cache
        $redis = new Redis;
        $redis->connect(REDIS_HOST);
        $return = $redis->get("client-{$args["id"]}-fcm-topics");
        if($return) return $response
            ->withStatus(200)
            ->withHeader('Content-type', 'application/json')
            ->write("{\"response\":true,\"error\":null,\"id\":0,\"data\":$return}");
        
        // Set fcm fetch type
        $args["type"] = "client";

        // Get client FCM topic
        $return = (new NRFCM)->getTopics($args);

        // Save Client FCM Topic Cache
        if($return["data"]) {
            $redis->set(
                "client-{$args["id"]}-fcm-topics", 
                json_encode($return["data"], JSON_PRETTY_PRINT | JSON_NUMERIC_CHECK | JSON_UNESCAPED_SLASHES),
                REDIS_TIMEOUT
            );
        }

        return $response->withJson($return, 200, JSON_PRETTY_PRINT | JSON_NUMERIC_CHECK | JSON_UNESCAPED_SLASHES);
    });
    $api->put('/clients/{id: [0-9]+}/fcm/token', function($request, $response, $args) {
        // Get PUT form
        $form = $request->getParsedBody();

        // Merge form and URL arguments
        $form["id"] = $args["id"];
        $form["type"] = "client";

        // Save Artist FCM token
        $return = (new NRFCM)->registerToken($form);

        return $response->withJson($return, 200, JSON_PRETTY_PRINT | JSON_NUMERIC_CHECK | JSON_UNESCAPED_SLASHES);
    });
    $api->get('/clients/{id: [0-9]+}/events', function($request, $response, $args) {
        // Get Client Events Cache
        $redis = new Redis;
        $redis->connect(REDIS_HOST);
        $return = $redis->get("client-{$args["id"]}-events");
        if($return) return $response
            ->withStatus(200)
            ->withHeader('Content-type', 'application/json')
            ->write("{\"response\":true,\"error\":null,\"id\":0,\"data\":$return}");

        // Get client events
        $return = (new NREvent)->get($args);

        // Save Client Events Cache
        if($return["data"]) {
            $redis->set(
                "client-{$args["id"]}-events", 
                json_encode($return["data"], JSON_PRETTY_PRINT | JSON_NUMERIC_CHECK | JSON_UNESCAPED_SLASHES),
                REDIS_TIMEOUT
            );
        }

        return $response->withJson($return, 200, JSON_PRETTY_PRINT | JSON_NUMERIC_CHECK | JSON_UNESCAPED_SLASHES);
    });
    $api->delete('/clients/{id: [0-9]+}/events/{eventId: [0-9]+}', function($request, $response, $args) {
        // Delete event 
        $return = (new NREvent)->delete($args);

        // Clear Client Events Cache
        $redis = new Redis;
        $redis->connect(REDIS_HOST);
        $redis->delete("client-{$args["id"]}-events");

        return $response->withJson($return, 200, JSON_PRETTY_PRINT | JSON_NUMERIC_CHECK | JSON_UNESCAPED_SLASHES);
    });
    $api->get('/clients/{id: [0-9]+}/events/recent/unpaid', function($request, $response, $args) {
        // Get Client Events Cache
        $redis = new Redis;
        $redis->connect(REDIS_HOST);
        $return = $redis->get("client-{$args["id"]}-events-unpaid");
        if($return) return $response
            ->withStatus(200)
            ->withHeader('Content-type', 'application/json')
            ->write("{\"response\":true,\"error\":null,\"id\":0,\"data\":$return}");

        // Set events get type 
        $args["type"] = "client";

        // Get recently completed unpaid events 
        $return = (new NREvent)->getRecentlyCompletedEvents($args);
        
        // Save Client Unpaid Events Cache
        if($return["data"]) {
            $redis->set(
                "client-{$args["id"]}-events-unpaid", 
                json_encode($return["data"], JSON_PRETTY_PRINT | JSON_NUMERIC_CHECK | JSON_UNESCAPED_SLASHES),
                REDIS_TIMEOUT
            );
        }

        return $response->withJson($return, 200, JSON_PRETTY_PRINT | JSON_NUMERIC_CHECK | JSON_UNESCAPED_SLASHES);
    });
    $api->put('/clients/{id: [0-9]+}/forgot-password', function($request, $response, $args) {
        // Get POST form
        $form = $request->getParsedBody();

        // Do forgot password function
        $return = (new NRClient)->forgotPasswordUpdate($form);

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

        // Incase of duplicate ID delete any Redis association
        $redis = new Redis;
        $redis->connect(REDIS_HOST);
        $redis->delete("artist-{$return["id"]}");

        return $response->withJson($return, 200, JSON_PRETTY_PRINT | JSON_NUMERIC_CHECK | JSON_UNESCAPED_SLASHES);
    });
    $api->post('/artists/authenticate', function($request, $response, $args) {
        // Get POST form
        $form = $request->getParsedBody();

        // Authenticate artist
        $return = (new NRArtist)->authenticate($form["username"], $form["password"]);

        return $response->withJson($return, 200, JSON_PRETTY_PRINT | JSON_NUMERIC_CHECK | JSON_UNESCAPED_SLASHES);
    });
    $api->post('/artists/forgot-password', function($request, $response, $args) {
        // Get POST form
        $form = $request->getParsedBody();

        // Do forgot password function
        $return = (new NRArtist)->forgotPassword($form["username"]);

        return $response->withJson($return, 200, JSON_PRETTY_PRINT | JSON_NUMERIC_CHECK | JSON_UNESCAPED_SLASHES);
    });
    $api->get('/artists/{id: [0-9]+}', function($request, $response, $args) {
        // Get Artist from Redis
        $redis = new Redis;
        $redis->connect(REDIS_HOST);
        $return = $redis->get("artist-{$args["id"]}");
        if($return) return $response
            ->withStatus(200)
            ->withHeader('Content-type', 'application/json')
            ->write($return);

        // Get Artist by ID
        $return = (new NRArtist)->get($args);

        // Cache Artist data
        if($return->id) {
            $redis->set(
                "artist-{$return->id}",
                json_encode($return, JSON_PRETTY_PRINT | JSON_NUMERIC_CHECK | JSON_UNESCAPED_SLASHES),
                REDIS_TIMEOUT
            );
        }

        return $response->withJson($return, 200, JSON_PRETTY_PRINT | JSON_NUMERIC_CHECK | JSON_UNESCAPED_SLASHES);
    });
    $api->put('/artists/{id: [0-9]+}', function($request, $response, $args) {
        // Get PUT form
        $form = $request->getParsedBody();

        // Merge form with URL arguments
        $form["id"] = $args["id"];

        // Update artist info
        $return = (new NRArtist)->update($form);

        // Cache Artist data
        if($return->id) {
            $redis = new Redis;
            $redis->connect(REDIS_HOST);
            $redis->set(
                "artist-{$return->id}",
                json_encode($return, JSON_PRETTY_PRINT | JSON_NUMERIC_CHECK | JSON_UNESCAPED_SLASHES),
                REDIS_TIMEOUT
            );
        }

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
        
        // Clear Artist Cache
        $redis = new Redis;
        $redis->connect(REDIS_HOST);
        $redis->delete("artist-{$args["id"]}");
        $redis->delete("artist-{$args["id"]}-locations");

        return $response->withJson($return, 200, JSON_PRETTY_PRINT | JSON_NUMERIC_CHECK | JSON_UNESCAPED_SLASHES);
    });
    $api->get('/artists/{id: [0-9]+}/locations', function($request, $response, $args) {
        // Get Artist Locations Cache
        $redis = new Redis;
        $redis->connect(REDIS_HOST);
        $return = $redis->get("artist-{$args["id"]}-locations");
        if($return) return $response
            ->withStatus(200)
            ->withHeader('Content-type', 'application/json')
            ->write("{\"response\":true,\"error\":null,\"id\":0,\"data\":$return}");
         
        // Merge form and URL arguments
        $form["id"] = $args["id"];

        // Get Artist Locations
        $return = (new NRArtist)->getLocations($form);

        // Save Artist Locations Cache
        if($return["data"]) {
            $redis->set(
                "artist-{$args["id"]}-locations", 
                json_encode($return["data"], JSON_PRETTY_PRINT | JSON_NUMERIC_CHECK | JSON_UNESCAPED_SLASHES),
                REDIS_TIMEOUT
            );
        }

        return $response->withJson($return, 200, JSON_PRETTY_PRINT | JSON_NUMERIC_CHECK | JSON_UNESCAPED_SLASHES);
    });
    $api->delete('/artists/{id: [0-9]+}/locations/{loc_id: [0-9]+}', function($request, $response, $args) {
        // Delete Artist Location
        $return = (new NRArtist)->deleteLocation($args);
        
        // Clear Artist Cache
        $redis = new Redis;
        $redis->connect(REDIS_HOST);
        $redis->delete("artist-{$args["id"]}");
        $redis->delete("artist-{$args["id"]}-locations");

        return $response->withJson($return, 200, JSON_PRETTY_PRINT | JSON_NUMERIC_CHECK | JSON_UNESCAPED_SLASHES);
    });
    $api->put('/artists/{id: [0-9]+}/portfolio', function($request, $response, $args) {
        // TODO: Implement artist portfolio update

        // Clear Artist Cache
        $redis = new Redis;
        $redis->connect(REDIS_HOST);
        $redis->delete("artist-{$args["id"]}");
    });
    $api->put('/artists/{id: [0-9]+}/payment/id', function($request, $response, $args) {
        // Get PUT form
        $form = $request->getParsedBody();

        // Merge form and URL arguments
        $form["id"] = $args["id"];

        // Save Artist Stripe payment ID
        $return = (new NRArtist)->saveStripeInfo($form);
        
        // Clear Artist Cache
        $redis = new Redis;
        $redis->connect(REDIS_HOST);
        $redis->delete("artist-{$args["id"]}");

        return $response->withJson($return, 200, JSON_PRETTY_PRINT | JSON_NUMERIC_CHECK | JSON_UNESCAPED_SLASHES);
    });
    $api->put('/artists/{id: [0-9]+}/fcm/token', function($request, $response, $args) {
        // Get PUT form
        $form = $request->getParsedBody();

        // Merge form and URL arguments
        $form["id"] = $args["id"];
        $form["type"] = "artist";

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

        // Empty Redis cache for Artist FCM
        $redis = new Redis;
        $redis->connect(REDIS_HOST);
        $redis->delete("artist-{$args["id"]}-fcm-topics");

        return $response->withJson($return, 200, JSON_PRETTY_PRINT | JSON_NUMERIC_CHECK | JSON_UNESCAPED_SLASHES);
    });
    $api->get('/artists/{id: [0-9]+}/fcm/topic', function($request, $response, $args) {
        // Get Artist FCM Topics Cache
        $redis = new Redis;
        $redis->connect(REDIS_HOST);
        $return = $redis->get("artist-{$args["id"]}-fcm-topics");
        if($return) return $response
            ->withStatus(200)
            ->withHeader('Content-type', 'application/json')
            ->write("{\"response\":true,\"error\":null,\"id\":0,\"data\":$return}");
        
        // Set fcm fetch type
        $args["type"] = "artist";

        // Get artist FCM topic
        $return = (new NRFCM)->getTopics($args);

        // Save Artist FCM Topic Cache
        if($return["data"]) {
            $redis->set(
                "artist-{$args["id"]}-fcm-topics", 
                json_encode($return["data"], JSON_PRETTY_PRINT | JSON_NUMERIC_CHECK | JSON_UNESCAPED_SLASHES),
                REDIS_TIMEOUT
            );
        }

        return $response->withJson($return, 200, JSON_PRETTY_PRINT | JSON_NUMERIC_CHECK | JSON_UNESCAPED_SLASHES);
    });
    $api->get('/artists/{id: [0-9]+}/events/recent/unpaid', function($request, $response, $args) {
        // Get Artist Events Cache
        $redis = new Redis;
        $redis->connect(REDIS_HOST);
        $return = $redis->get("artist-{$args["id"]}-events-unpaid");
        if($return) return $response
            ->withStatus(200)
            ->withHeader('Content-type', 'application/json')
            ->write("{\"response\":true,\"error\":null,\"id\":0,\"data\":$return}");

        // Set events get type 
        $args["type"] = "artist";

        // Get recently completed unpaid events 
        $return = (new NREvent)->getRecentlyCompletedEvents($args);

        // Save Artist Unpaid Events Cache
        if($return["data"]) {
            $redis->set(
                "artist-{$args["id"]}-events-unpaid", 
                json_encode($return["data"], JSON_PRETTY_PRINT | JSON_NUMERIC_CHECK | JSON_UNESCAPED_SLASHES),
                REDIS_TIMEOUT
            );
        }

        return $response->withJson($return, 200, JSON_PRETTY_PRINT | JSON_NUMERIC_CHECK | JSON_UNESCAPED_SLASHES);
    });
    $api->get('/artists/roles', function($request, $response, $args) {
        // Get Artist Roles Cache
        $redis = new Redis;
        $redis->connect(REDIS_HOST);
        $return = $redis->get("artist-roles");
        if($return) return $response
            ->withStatus(200)
            ->withHeader('Content-type', 'application/json')
            ->write("{\"response\":true,\"error\":null,\"id\":0,\"data\":$return}");

        // Get artist roles
        $return = NRArtist::getRoles();

        // Save Artist Roles Cache
        if($return["data"]) {
            $redis->set(
                "artist-roles", 
                json_encode($return["data"], JSON_PRETTY_PRINT | JSON_NUMERIC_CHECK | JSON_UNESCAPED_SLASHES),
                REDIS_TIMEOUT
            );
        }

        return $response->withJson($return, 200, JSON_PRETTY_PRINT | JSON_NUMERIC_CHECK | JSON_UNESCAPED_SLASHES);
    });
    $api->put('/artists/{id: [0-9]+}/forgot-password', function($request, $response, $args) {
        // Get POST form
        $form = $request->getParsedBody();

        // Do forgot password function
        $return = (new NRArtist)->forgotPasswordUpdate($form);

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

        if($return["id"]) {
            // Clear cache
            $redis = new Redis;
            $redis->connect(REDIS_HOST);
            $redis->delete("event-{$return["id"]}");
        }

        return $response->withJson($return, 200, JSON_PRETTY_PRINT | JSON_NUMERIC_CHECK | JSON_UNESCAPED_SLASHES);
    });
    $api->get('/events/{id: [0-9]+}', function($request, $response, $args) {
        // Get Event from Redis
        $redis = new Redis;
        $redis->connect(REDIS_HOST);
        $return = $redis->get("event-{$args["id"]}");
        if($return) return $response
            ->withStatus(200)
            ->withHeader('Content-type', 'application/json')
            ->write($return);
            
        // If event not found in Redis get from DB
        $return = (new NREvent)->getSingle($args["id"]);
        
        // Cache event data
        if($return->id) {
            $redis->set(
                "event-{$return->id}", 
                json_encode($return, JSON_PRETTY_PRINT | JSON_NUMERIC_CHECK | JSON_UNESCAPED_SLASHES),
                REDIS_TIMEOUT
            );
        }

        return $response->withJson($return, 200, JSON_PRETTY_PRINT | JSON_NUMERIC_CHECK | JSON_UNESCAPED_SLASHES);
    });
    $api->put('/events/{id: [0-9]+}', function($request, $response, $args) {
        // Get PUT form
        $form = $request->getParsedBody();

        // Merge form and URL arguments
        $form["id"] = $args["id"];

        // Update event 
        $return = (new NREvent)->update($form);
        
        // Clear cache
        $redis = new Redis;
        $redis->connect(REDIS_HOST);
        $redis->delete("event-{$args["id"]}");

        return $response->withJson($return, 200, JSON_PRETTY_PRINT | JSON_NUMERIC_CHECK | JSON_UNESCAPED_SLASHES);
    });
    $api->put('/events/{id: [0-9]+}/ratings/artist', function($request, $response, $args) {
        // Get PUT form
        $form = $request->getParsedBody();

        // Merge form and URL arguments
        $form["id"] = $args["id"];

        // Set event artist rating
        $return = NREvent::artistRating($form);

        // Clear cache
        $redis = new Redis;
        $redis->connect(REDIS_HOST);
        $redis->delete("event-{$args["id"]}");

        return $response->withJson($return, 200, JSON_PRETTY_PRINT | JSON_NUMERIC_CHECK | JSON_UNESCAPED_SLASHES);
    });
    $api->put('/events/{id: [0-9]+}/ratings/client', function($request, $response, $args) {
        // Get PUT form
        $form = $request->getParsedBody();

        // Merge form and URL arguments
        $form["id"] = $args["id"];

        // Set event client rating
        $return = NREvent::clientRating($form);
        
        // Clear cache
        $redis = new Redis;
        $redis->connect(REDIS_HOST);
        $redis->delete("event-{$args["id"]}");

        return $response->withJson($return, 200, JSON_PRETTY_PRINT | JSON_NUMERIC_CHECK | JSON_UNESCAPED_SLASHES);
    });
    $api->put('/events/{id: [0-9]+}/attendance/artist', function($request, $response, $args) {
        // Get PUT form
        $form = $request->getParsedBody();

        // Merge form and URL arguments
        $form["id"] = $args["id"];

        // Save event artist attendance
        $return = NREvent::saveArtistAttendance($form);
        
        // Clear cache
        $redis = new Redis;
        $redis->connect(REDIS_HOST);
        $redis->delete("event-{$args["id"]}");

        return $response->withJson($return, 200, JSON_PRETTY_PRINT | JSON_NUMERIC_CHECK | JSON_UNESCAPED_SLASHES);
    });
    $api->put('/events/{id: [0-9]+}/attendance/client', function($request, $response, $args) {
        // Get PUT form
        $form = $request->getParsedBody();

        // Merge form and URL arguments
        $form["id"] = $args["id"];

        // Save event client attendance
        $return = NREvent::saveClientAttendance($form);
        
        // Clear cache
        $redis = new Redis;
        $redis->connect(REDIS_HOST);
        $redis->delete("event-{$args["id"]}");

        return $response->withJson($return, 200, JSON_PRETTY_PRINT | JSON_NUMERIC_CHECK | JSON_UNESCAPED_SLASHES);
    });
    $api->post('/events/{id: [0-9]+}/apply', function($request, $response, $args) {
        // Get POST form
        $form = $request->getParsedBody();

        // Merge form and URL arguments
        $form["id"] = $args["id"];

        // Artist apply for job
        $return = (new NREvent)->apply($form);

        // Clear cache
        $redis = new Redis;
        $redis->connect(REDIS_HOST);
        $redis->delete("event-{$args["id"]}");
        $redis->delete("artist-{$args["id"]}-events-new");

        return $response->withJson($return, 200, JSON_PRETTY_PRINT | JSON_NUMERIC_CHECK | JSON_UNESCAPED_SLASHES);
    });
    $api->post('/events/{id: [0-9]+}/artist/{userId: [0-9]+}/cancel', function($request, $response, $args) {
        // Get POST form
        $form = $request->getParsedBody();

        // Artist cancel job booking
        $return = (new NREvent)->cancel($args);

        // Clear cache
        $redis = new Redis;
        $redis->connect(REDIS_HOST);
        $redis->delete("event-{$args["id"]}");
        $redis->delete("artist-{$args["userId"]}-events-new");

        return $response->withJson($return, 200, JSON_PRETTY_PRINT | JSON_NUMERIC_CHECK | JSON_UNESCAPED_SLASHES);
    });
    $api->get('/events/new/artist/{userId}', function($request, $response, $args) {
        // Get Cache
        $redis = new Redis;
        $redis->connect(REDIS_HOST);
        $return = $redis->get("artist-{$args["userId"]}-events-new");
        if($return) return $response
            ->withStatus(200)
            ->withHeader('Content-type', 'application/json')
            ->write("{\"response\":true,\"error\":null,\"data\":$return}");
        
        // Get events near artist from ID
        $return = (new NREvent)->getNew($args);

        if($return["data"]) {
            $redis = new Redis;
            $redis->connect(REDIS_HOST);
            // Shortened TTL to 30 minutes for event notifications
            $redis->set(
                "artist-{$args["userId"]}-events-new", 
                json_encode($return["data"], JSON_PRETTY_PRINT | JSON_NUMERIC_CHECK | JSON_UNESCAPED_SLASHES),
                1800
            );
        }

        return $response->withJson($return, 200, JSON_PRETTY_PRINT | JSON_NUMERIC_CHECK | JSON_UNESCAPED_SLASHES);
    });

    /**
     * PACKAGE: Package Functions
     */
    $api->post('/packages', function($request, $response, $args) {
        // Get POST form
        $form = $request->getParsedBody();

        // Create new event 
        $return = (new NRPackage)->save($form);

        // Clear cache
        $redis = new Redis;
        $redis->connect(REDIS_HOST);
        $redis->delete("packages");

        return $response->withJson($return, 200, JSON_PRETTY_PRINT | JSON_NUMERIC_CHECK | JSON_UNESCAPED_SLASHES);
    });
    $api->get('/packages[/{id: [0-9]+}]', function($request, $response, $args) {
        // Get Package Cache
        if(isset($args["id"])) {
            $redis = new Redis;
            $redis->connect(REDIS_HOST);
            $return = $redis->get("package-{$args["id"]}");
            if($return) return $response
                ->withStatus(200)
                ->withHeader('Content-type', 'application/json')
                ->write("{\"response\":true,\"error\":null,\"data\":[$return]}");

        // Get Packages Cache
        } else {
            $redis = new Redis;
            $redis->connect(REDIS_HOST);
            $return = $redis->get("packages");
            if($return) return $response
                ->withStatus(200)
                ->withHeader('Content-type', 'application/json')
                ->write("{\"response\":true,\"error\":null,\"data\":$return}");
        }

        // Get Packages
        $return = (new NRPackage)->get($args);

        // Write Package Cache
        if($return["data"] && isset($args["id"])) {
            $redis->set(
                "package-{$return["data"][0]["id"]}", 
                json_encode($return["data"][0], JSON_PRETTY_PRINT | JSON_NUMERIC_CHECK | JSON_UNESCAPED_SLASHES),
                REDIS_TIMEOUT
            );

        // Write Packages Cache
        } else if($return["data"]) {
            $redis->set(
                "packages", 
                json_encode($return["data"], JSON_PRETTY_PRINT | JSON_NUMERIC_CHECK | JSON_UNESCAPED_SLASHES),
                REDIS_TIMEOUT
            );
        }
        
        return $response->withJson($return, 200, JSON_PRETTY_PRINT | JSON_NUMERIC_CHECK | JSON_UNESCAPED_SLASHES);
    });
    $api->delete('/packages/{id: [0-9]+}', function($request, $response, $args) {
        // Delete package 
        $return = (new NRPackage)->delete($args);

        // Clear Packages Cache
        $redis = new Redis;
        $redis->connect(REDIS_HOST);
        $redis->delete("packages");
        $redis->delete("package-{$args["id"]}");
        
        return $response->withJson($return, 200, JSON_PRETTY_PRINT | JSON_NUMERIC_CHECK | JSON_UNESCAPED_SLASHES);
    });

    /**
     * CHAT: Chat Functions
     */
    $api->get('/chat/{type: [a-z]+}/{username}/token', function($request, $response, $args) {
        // Get API Token
        $return = (new NRChat)->token($args);      
        
        return $response->withJson($return, 200, JSON_PRETTY_PRINT | JSON_NUMERIC_CHECK | JSON_UNESCAPED_SLASHES);
    });

    /**
     * DEFAULT: Default Route
     */
    $api->any('/', function($request, $response, $args) {
        error_log( var_dump($request->getParsedBody()) );
        return $response->write("No parameters given")
            ->withStatus(400);
    });

    // Run API
    $api->run();
?>