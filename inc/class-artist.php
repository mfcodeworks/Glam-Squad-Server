<?php
/**
 * Class: NRClient
 * Author: MF Softworks <mf@nygmarosebeauty.com>
 * Date Created: 03/12/2018
 * Description:
 * User class to handle artist authentication and registration
 */
    
require_once "database-interface.php";

class NRArtist {
    // properties
    private $username;
    private $email;
    private $password;

    // functions
    public function __construct() {

    }

    public function register($username, $email, $password) {
        // Hash password
        $password = $this->hashInput($password);

        // Build SQL
        // FIXME: Fix locked to initially = 1 (true)
        $sql = 
        "INSERT INTO nr_artists(username, email, password, locked)
        VALUES(\"$username\", \"$email\", \"$password\", 0);
        ";

        try {
            $mail = new Mailer();
            $mail->setFrom("mua@nygmarosebeauty.com", "NygmaRose");
            $mail->addAddress($email);
            $mail->Subject = "NygmaRose Glam Squad Registration";
            $mail->Body = 
<<<EOD
    <html>
        <head>
            <style>
                body {
                    font-family: Arial;
                }
            </style>
        </head>
        <body>
            <p>
                Hi $username,
                <br><br>
                Your Glam Squad registration has been successfully received! 
                <br>
                The NR Glam Squad team will be in contact soon if your application is approved and schedule an interview.
                <br><br>
                Best Wishes,
                <br>
                NygmaRose
            </p>
        </body>
    </html>
EOD;
            $mail->send();
        }
        catch(Exception $e) {
            error_log($e);
        }

        // Return SQL result
        return runSQLQuery($sql);
    }

    public function get($args) {
        // Get args
        extract($args);

        // Build SQL
        $sql = 
        "SELECT * 
        FROM nr_artists
        WHERE id = $userId;";

        $response = runSQLQuery($sql);

        // Save hashed username for session verification
        if(isset($response["data"][0])) {
            if($response["data"][0]["locked"]) {
                unset($response["data"]);
                $response["response"] = false;
                $response["error"] = "Artist has not been approved for login yet.";
                return $response;
            }

            $response["data"][0]["usernameHash"] = $this->hashInput($response["data"][0]["username"]);

            unset($response["data"][0]["password"]);
        }

        return $response;
    }

    public function update($args) {
        extract($args);

        switch(trim($password)) {
            case "":
                $sql = 
                "UPDATE nr_artists
                SET username = \"$username\", email = \"$email\"
                WHERE id = $userId;";

                $response = runSQLQuery($sql);
                break;

            default:
                // Hash password
                $password = $this->hashInput($password);

                $sql = 
                "UPDATE nr_artists
                SET username = \"$username\", email = \"$email\", password = \"$password\"
                WHERE id = $userId;";

                $response = runSQLQuery($sql);
                break;
        }
        if($response["response"] == true) {
            $sql = 
            "SELECT * FROM nr_clients
            WHERE id = $userId;";

            $response = runSQLQuery($sql);

            unset($response["data"][0]["password"]);
            $response["data"][0]["usernameHash"] = $this->hashInput($username);
        }
        return $response;
    }

    public function authenticate($username, $password) {
        // Get user ID & Password hash
        $sql = 
        "SELECT *
            FROM nr_artists
            WHERE username = \"$username\";
        ";

        // Save SQL response
        $response = runSQLQuery($sql);

        // If no rows found matching username, return and let client handle error
        if(!isset($response["data"])) {
            $response["response"] = false;
            $response["error"] = "Username not found.";
            return $response;
        }

        // Verify password and password hash
        if($this->verifyInput($password, $response["data"][0]["password"]) == true) {
            // Remove password hash and return successful result
            unset($response["data"][0]["password"]);

            // Save hashed username for session verification
            $response["data"][0]["usernameHash"] = $this->hashInput($username);
            return $response;
        }

        // Password wasn't correct, return an error
        $response["response"] = false;
        $response["error"] = "Incorrect login details.";
        unset($response["data"]);

        return $response;
    }

    public function validateSession($id, $usernameHash) {
        // Get plaintext username
        $sql = 
        "SELECT username
        FROM nr_artists
        WHERE id = $id;
        ";

        // Save response
        $r = runSQLQuery($sql);

        // If the ID exists 
        if(isset($r["data"])) {

            // Save plaintext username of ID
            $username = $r["data"][0]["username"];

            // Verify password against hash
            if($this->verifyInput($username,$usernameHash)) {
                return true;
            }
        }

        // If ID doesn't exist or username hash is wrong return false
        return false;
    }

    public function savePaymentInfo($args) {
        extract($args);

        $sql;

        $res = runSQLQuery($sql);

        return $res;
    }

    public function saveLocation($args) {
        extract($args);

        $sql = 
        "INSERT INTO nr_artist_locations(loc_name, loc_lat, loc_lng, artist_id)
        VALUES(\"$name\", $lat, $lng, $userId);";

        return runSQLQuery($sql);
    }

    private function hashInput($password) {
        // Hash password with Argon2 (PHP7.2+)
        return password_hash($password, PASSWORD_ARGON2I, ["memory_cost" => 2048, "time_cost" => 4, "threads" => 2]);
    }

    private function verifyInput($password, $password_hash) {
        // Verify password against password hash
        if(password_verify($password, $password_hash)) {
            return true;
        } 
        return false;
    }
}

?>