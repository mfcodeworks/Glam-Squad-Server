<?php
    // Run SQL query
    function runSQLQuery($sql) {
        // Open DB
        $db = connectToDB();

        if( $db->connect_errno ) {
            return [
                'response' => false,
                'error_code' => $db->connect_errno,
                'error' => $db->connect_error
            ];
        }

        // Run & return query
        $response = $db->query($sql);

        switch(true) {
            case $response === true:
                return [
                    'response' => true,
                    'error' => null,
                    'id' => $db->insert_id
                ];

            case $response === false:
                return [
                    'response' => false,
                    'error_code' => $db->errno,
                    'error' => $db->error,
                    'sql' => $sql
                ];

            default:
                $data = [
                    'response' => true,
                    'error' => null,
                    'id' => $db->insert_id
                ];

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
        return new \MySQLi(
            DB_SERVER,
            DB_USER,
            DB_PASS,
            DB_NAME
        );
    }
?>