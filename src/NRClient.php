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
    public $id;
    public $username;
    public $email;
    private $password;
    public $profile_photo;
    public $stripe_customer_id;
    public $rating;
    public $fcmTopics = [];
    public $cards = [];
    public $receipts;

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
        $res = runSQLQuery($sql);

        if($res["response"] !== true) {
            return [
                "response" => false,
                "error_code" => 900,
                "error" => "Database error occured\n" . json_encode($r)
            ];
        }

        try {
            $mail = new Mailer();
            $mail->setFrom("mua@nygmarosebeauty.com", "NygmaRose");
            $mail->addAddress($email);
            $mail->Subject = "NygmaRose Glam Squad Registration";
            $mail->Body = <<<EOD
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
                            <br><br>
                            Welcome to Glam Squad, enjoy getting your face beat and relaxing while your own personal glam squad attend to your every beauty need.
                            <br>
                            We look forward to your first booking!
                            <br><br>
                            All the love,
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

        return $res;
    }

    public function get($args) {
        // Get args
        extract($args);

        // Build SQL
        $sql = 
        "SELECT * 
        FROM nr_clients
        WHERE id = $id;";

        $response = runSQLQuery($sql);

        // FIXME: Fix giving username hash for all get requests
        // Save hashed username for session verification
        $response["data"][0]["usernameHash"] = $this->hashInput($response["data"][0]["username"]);

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
                WHERE id = $id;";

                $response = runSQLQuery($sql);
                break;

            default:
                // Hash password
                $password = $this->hashInput($password);

                $sql = 
                "UPDATE nr_clients
                SET username = \"$username\", email = \"$email\", password = \"$password\"
                WHERE id = $id;";

                $response = runSQLQuery($sql);
                break;
        }
        if($response["response"] === true) return $this->get(["id" => $id]);
        else return $response;
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
            return [
                "response" => false,
                "error_code" => 205,
                "error" => "Incorrect login details"
            ];
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
        return [
            "response" => false,
            "error_code" => 205,
            "error" => "Incorrect login details"
        ];
    }

    public function forgotPassword($username) {
        // Get user info 
        $sql =
        "SELECT id, email 
            FROM nr_clients 
            WHERE username = \"$username\";";

        $r = runSQLQuery($sql);

        if(isset($r["data"])) {
            $id = $r["data"][0]["id"];
            $email = $r["data"][0]["email"];
        } else {
            return [
                "response" => false,
                "error_code" => 205,
                "error" => "Incorrect username"
            ];
        }

        $key = $this->randomString();

        $sql = 
        "INSERT INTO nr_client_forgot_password_key(
            unique_key,
            expiration_date,
            client_id
        )
        VALUES(
            \"$key\",
            NOW() + INTERVAL 12 HOUR,
            $id
        );";

        $r = runSQLQuery($sql);

        if($r["response"] !== true) {
            return [
                "response" => false,
                "error_code" => 900,
                "error" => "Database error occured\n" . json_encode($r)
            ];
        }
        
        try {
            $url = FORGOT_PASSWORD_URI . "?key=$key";
            $mail = new Mailer();
            $mail->setFrom("it@nygmarosebeauty.com", "NygmaRose");
            $mail->addAddress($email);
            $mail->Subject = "Reset Password NygmaRose Glam Squad";
            $mail->Body = <<<EOD
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
                            Please click <a href="$url">here</a> or paste the link below into your browser to reset your password, this link will be valid for the next 12 hours.
                            <br>
                            <a href="$url">$url</a>
                            </br>
                            <br><br>
                            Best Regards,
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

        return $r;
    }

    private function randomString($length = 32) {
        // Create random string with current date salt for uniqueness
        return date('Y-m-d-H-i-s').bin2hex(random_bytes($length));;
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

            // plaintext username of ID
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

        // If client wishes to save card permanently
        if(isset($stripeId)) {
            $sql = 
            "UPDATE nr_clients
            SET stripe_customer_id = \"$stripeId\"
            WHERE id = $id;
            ";
    
            $res = runSQLQuery($sql);
            if($res["response"] != true) return $res;
        }

        $sql = 
        "INSERT INTO nr_payment_cards(
            card_type,
            card_last_digits,
            card_token,
            client_id
        )
        VALUES(
            \"$type\",
            $lastFour,
            \"$token\",
            $id
        );";

        $res = runSQLQuery($sql);

        return $res;
    }

    public function deleteCard($args) {
        extract($args);

        $sql = 
        "DELETE FROM nr_payment_cards
        WHERE client_id = $id
        AND card_token LIKE \"$token\";
        ";

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