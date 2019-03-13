<?php
/**
 * Class: NRClient
 * Author: MF Softworks <mf@nygmarosebeauty.com>
 * Date Created: 03/12/2018
 * Description:
 * User class to handle artist authentication and registration
 */
    
require_once "database-interface.php";

use Enqueue\AmqpLib\AmqpConnectionFactory;
use Enqueue\AmqpLib\AmqpContext;

class NRArtist {
    // properties
    public $id;
    public $username;
    public $email;
    private $password;
    public $profile_photo;
    public $bio;
    public $portfolio = [];
    public $rating;
    public $stripe_account_token;
    public $role = [
        "id" => 0,
        "name" => ""
    ];
    private $probation = 0;
    private $locked = 1;
    public $locations = [];
    public $fcmTopics = [];
    public $fcmTokens = [];
    public $bookings = [];
    public $receipts;
    public $twilio_sid;

    // functions
    public function __construct() {

    }

    public function register($args) {
        extract($args);

        if(!in_array($country, COUNTRIES)) {
            return [
                "response" => false,
                "error_code" => 601,
                "error" => "NR Glam Squad not available in your country at the moment."
            ];
        }

        // Hash password
        $password = NRAuth::hashInput($password);

        // Build SQL
        // FIXME: Fix locked to initially = 1 (true)
        if(isset($profile_photo)) {
            $sql = 
            "INSERT INTO nr_artists(username, email, password, bio, instagram, facebook, twitter, role_id, locked, probation, profile_photo)
                VALUES(\"$username\", \"$email\", \"$password\", \"$bio\", \"$instagram\", \"$facebook\", \"$twitter\", $role, 0, 0, \"$profile_photo\");";    
        } else {
            $sql = 
            "INSERT INTO nr_artists(username, email, password, bio, instagram, facebook, twitter, role_id, locked, probation, profile_photo)
                VALUES(\"$username\", \"$email\", \"$password\", \"$bio\", \"$instagram\", \"$facebook\", \"$twitter\", $role, 0, 0, \"https://glamsquad.sgp1.cdn.digitaloceanspaces.com/GlamSquad/default/images/profile.svg\");";
        }
        
        $res = runSQLQuery($sql);

        if(!isset($res["id"])) {
            if($res["error_code"] === 1062) return [
                "response" => false,
                "error_code" => 400,
                "error" => "Username or email already exists",
            ];
            else return [
                "response" => false,
                "error_code" => 500,
                "error" => "Unable to save new artist",
                "query" => $res
            ];
        } 

        $this->id = $res["id"];
        $this->username = $username;
        $this->email  = $email;

        try {
            // Register user with Twilio
            if(TWILIO_ENABLED && $this->id > 0) $twilioUser = (new NRChat)->queueRegister($this->id, $username, "artist");
        }
        catch(Exception $e) {
            error_log($e);
        }

        if(isset($portfolio)) {
            foreach ($portfolio as $artistPhoto) {
                try {
                    // Create photo object
                    $photo = new NRImage();
                    $photo->subdir = "GlamSquad/artist/{$this->username}/portfolio/";
                    $photo->getData($artistPhoto);
                    $spaces_path = $photo->uploadToSpaces();
                    $this->savePortfolioImage(SPACES_CDN . $spaces_path);
                }
                catch(Exception $e) {
                    return [
                        "response" => false,
                        "error_code" => 107,
                        "error" => "Failed saving attached portfolio images"
                    ];
                }
            }
        }

        try {
            $this->sendWelcomeEmail($email, $username);
        }
        catch(Exception $e) {
            error_log($e);
        }
        // Return SQL result
        return $res;
    }

    function sendWelcomeEmail($email, $username) {
        // Create context and queue
        $context = (new AmqpConnectionFactory(ENQUEUE_OPTIONS))->createContext();
        $queue = $context->createQueue('send_email');
        $context->declareQueue($queue);

        // Create message
        $args = [
            "email" => $email,
            "from" => "mua@nygmarosebeauty.com",
            "from_name" => "NygmaRose",
            "subject" => "NygmaRose Glam Squad Registration",
            "body" => 
                "<html>
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
                </html>"
        ];
        $message = $context->createMessage(json_encode($args));

        // Send message for queue
        $context->createProducer()->send($queue, $message);
    }
    
    public function forgotPassword($username) {
        // Get user info 
        $sql =
        "SELECT id, email 
            FROM nr_artists 
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
        "INSERT INTO nr_artist_forgot_password_key(
            unique_key,
            expiration_date,
            artist_id
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
            $url = FORGOT_PASSWORD_URI . "?key=$key&type=artist";
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

    public function forgotPasswordUpdate($args) {
        extract($args);
        
        // Double check key validity
        $sql =
        "SELECT artist_id, expiration_date
            FROM nr_artist_forgot_password_key
            WHERE unique_key = \"$key\";";
    
        $r = runSQLQuery($sql);
    
        // Return data or false if not found
        if(!isset($r["data"])) {
            return [
                "response" => false,
                "error_code" => 205,
                "error" => "Invalid key"
            ];
        }

        // Hash password
        $password = NRAuth::hashInput($password);

        // Update user password
        $sql = 
        "UPDATE nr_artists
            SET password = \"$password\"
            WHERE id = $id;";

        $response = runSQLQuery($sql);

        if($response["response"] !== true) return [
            "response" => false,
            "error_code" => 900,
            "error" => "Unknown database error"
        ];
        
        // Remove key validity
        $sql =
        "DELETE FROM nr_artist_forgot_password_key
            WHERE unique_key = \"$key\"";

        // Check all requests successful
        if(runSQLQuery($sql)["response"] === true) return $response;

        else return [
            "response" => false,
            "error_code" => 900,
            "error" => "Unknown database error"
        ];
    }

    private function randomString($length = 32) {
        // Create random string with current date salt for uniqueness
        return bin2hex(random_bytes($length));;
    }

    private function savePortfolioImage($uri) {
        $sql = "INSERT INTO nr_artist_portfolios(photo, artist_id)
            VALUES(\"$uri\", {$this->id});";

        $res = runSQLQuery($sql);

        if($res["response"] !== true) throw new Exception("Could not save image at $uri.");
        
        return $res["id"];
    }

    public function get($args) {
        // Get args
        extract($args);

        // Build SQL
        $sql = 
        "SELECT a.id, a.username, a.profile_photo, a.email, a.bio, a.rating, a.role_id, r.role_name, a.probation, a.locked, a.stripe_account_token, a.twilio_sid
            FROM nr_artists as a
            LEFT JOIN nr_job_roles as r ON r.id = a.role_id
            WHERE a.id = $id;";

        $response = runSQLQuery($sql);
        if(!$response["data"][0])
            return [
                "response" => false,
                "error" => "User doesn't exist",
                "error_code" => 400,
                "enddata" => $response
            ];

        extract($response['data'][0]);

        // Save properties
        $this->id = $id;
        $this->username = $username;
        $this->profile_photo = $profile_photo;
        $this->email = $email;
        $this->bio = $bio;
        $this->rating = $rating;
        $this->role = [
            "id" => $role_id,
            "name" => $role_name,
        ];
        $this->probation = $probation;
        $this->locked = $locked;
        $this->stripe_account_token = $stripe_account_token;
        $this->twilio_sid = $twilio_sid;

        if($this->locked) {
            return [
                "response" => false,
                "error_code" => 223,
                "error" => "Artist account is currently locked."
            ];
        }

        $this->locations = $this->getLocations(["id" => $this->id])["data"];
        $this->portfolio = $this->getPortfolio();
        $this->bookings = $this->getBookings();

        return $this;
    }

    public function getBookings() {
        $sql =
        "SELECT aj.event_id as id
            FROM nr_artist_jobs as aj
            LEFT JOIN nr_jobs as j ON aj.event_id = j.id
            WHERE artist_id = {$this->id}
            ORDER BY j.event_datetime DESC;";

        return runSQLQuery($sql)["data"];
    }

    public function getPortfolio() {
        $sql =
        "SELECT id, photo
            FROM nr_artist_portfolios
            WHERE artist_id = {$this->id};";

        return runSQLQuery($sql)["data"];
    }

    public function getLocations($args) {
        extract($args);

        $sql = 
        "SELECT id, loc_name as name, loc_lat as lat, loc_lng as lng
        FROM nr_artist_locations
        WHERE artist_id = {$id};";

        $res = runSQLQuery($sql);

        if(!isset($res["data"])) {
            return [
                "response" => false,
                "error_code" => 107,
                "error" => "No locations saved",
                "data" => null
            ];
        }
        return $res;
    }

    public static function getRoles() {
        $sql = 
        "SELECT id, role_name as name
            FROM nr_job_roles
            ORDER BY id ASC;";

        return runSQLQuery($sql);
    }

    public function update($args) {
        extract($args);

        if(!isset($password)) $password = "";

        $this->get(["id" => $id]);

        switch(trim($password)) {
            case "":
                $sql = 
                "UPDATE nr_artists
                SET username = \"$username\", email = \"$email\"
                WHERE id = $id;";
                break;

            default:
                // Hash password
                $password = NRAuth::hashInput($password);

                $sql = 
                "UPDATE nr_artists
                SET username = \"$username\", email = \"$email\", password = \"$password\"
                WHERE id = $id;";
                break;
        }
        $response = runSQLQuery($sql);
        error_log(print_r($response, true));

        if($response["response"] == true) {
            $this->get(["id" => $id]);

            // Update twilio username
            if(TWILIO_ENABLED) {
                (new NRChat())->updateUser($this->twilio_sid, [
                    "friendlyName" => $username,
                    "identity" => "artist-$username",
                    "attributes" => json_encode($this, JSON_NUMERIC_CHECK | JSON_UNESCAPED_SLASHES)
                ]);
            }

            return $this;
        }
        else return $response;
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
            return [
                "response" => false,
                "error_code" => 205,
                "error" => "Username not found"
            ];
        }

        // Verify password and password hash
        if(NRAuth::verifyInput($password, $response["data"][0]["password"]) == true) {
            // Get Artist information
            $this->get(["id" => $response["data"][0]["id"]]);
            $response["data"][0] = $this;
            return $response;
        }

        // Password wasn't correct, return an error
        return [
            "response" => false,
            "error_code" => 205,
            "error" => "Incorrect login details"
        ];
    }

    public function validateSession($id, $key) {
        $this->get(["id" => $id]);

        // If the ID exists
        if(isset($this->id) && NRAuth::verifyUserKey($key, $this->username, $this->password)) {
            return [
                "response" => true,
                "error" => null,
                "data" => [$this],
                "valid" => true
            ];
        }

        // If ID doesn't exist or username hash is wrong return false
        return [
            "response" => true,
            "error_code" => 206,
            "error" => "User not found",
            "valid" => false
        ];
    }

    public function saveStripeInfo($args) {
        extract($args);

        $sql = 
        "UPDATE nr_artists
            SET stripe_account_token = \"$token\"
            WHERE id = $id";
            
        return runSQLQuery($sql);
    }

    public function saveLocation($args) {
        extract($args);

        $sql = 
        "INSERT INTO nr_artist_locations(loc_name, loc_lat, loc_lng, artist_id)
            VALUES(\"$name\", $lat, $lng, $id);";

        return runSQLQuery($sql);
    }

    public function deleteLocation($args) {
        extract($args);

        $sql =
        "DELETE FROM nr_artist_locations
            WHERE artist_id = $id
            AND id = $loc_id;";

        return runSQLQuery($sql);
    }

    public function getPassword() {
        return $this->password;
    }

    private function hashInput($password) {
        // Hash password with Argon2 (PHP7.2+)
        return password_hash($password, PASSWORD_ARGON2I, ARGON_CONFIG);
    }

    private function verifyInput($password, $password_hash) {
        // Verify password against password hash
        return password_verify($password, $password_hash);
    }
}

?>