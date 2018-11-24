<?php
/**
 * Class: NRClient
 * Author: MF Softworks <mf@nygmarosebeauty.com>
 * Date Created: 09/11/2018
 * Date Edited: 12/11/2018
 * Description:
 * User class to handle client authentication and registration
 */

require_once "database-interface.php";

class NRClient {
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
        $sql = 
        "INSERT INTO nr_clients(username, email, password)
        VALUES(\"$username\", \"$email\", \"$password\");
        ";

        // Return SQL result
        return runSQLQuery($sql);
    }

    public function get($args) {
        // Get args
        extract($args);

        // Build SQL
        $sql = 
        "SELECT * 
        FROM nr_clients
        WHERE id = $userId;";

        $response = runSQLQuery($sql);

        unset($response["data"][0]["password"]);

        return $response;
    }

    public function update($args) {
        extract($args);

        switch(trim($password)) {
            case "":
                $sql = 
                "UPDATE nr_clients
                SET username = \"$username\", email = \"$email\"
                WHERE id = $userId;";

                $response = runSQLQuery($sql);
                break;

            default:
                // Hash password
                $password = $this->hashInput($password);

                $sql = 
                "UPDATE nr_clients
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
            FROM nr_clients
            WHERE username = \"$username\";
        ";

        // Save SQL response
        $response = runSQLQuery($sql);

        // If no rows found matching username, return and let client handle error
        if(!isset($response["data"])) {
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
        FROM nr_clients
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