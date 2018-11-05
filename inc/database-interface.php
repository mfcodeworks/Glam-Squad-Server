<?php
    require "db-config.php";
    
    // Run SQL query
    function runSQLQuery($sql) {

        // Open DB
        $db = connectToDB();

        // Run & return query
        $response = $db->query($sql);
        
        if( is_bool($response) ) {
            $response = ($response) ? 'true' : 'false';
        }

        switch($response) {
            case "true":
                $data['response'] = true;

                $data['error'] = null;

                return $data;

            case "false":
                $data['response'] = false;

                $data['error'] = $db->error;

                return $data;

            default:
                $data['response'] = true;

                $data['error'] = null;

				if( $response->num_rows > 0) {
                    while( $row = $response->fetch_assoc() ) {
                        $data['data'][] = $row;
                    }
				}
				else {
					$data['data'] = null;
                }
                
                return $data;
        }
    }

    // Open database connection
    function connectToDB() {
        // Connect to DB
        return new mysqli(DB_SERVER,DB_USER,DB_PASS,DB_NAME);
    }
?>