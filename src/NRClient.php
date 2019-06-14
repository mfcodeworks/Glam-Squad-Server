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

use Enqueue\AmqpLib\AmqpConnectionFactory;
use Enqueue\AmqpLib\AmqpContext;

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
    public $twilio_sid;

    // functions
    public function __construct() {

    }

    public function register($args) {
        extract($args);

        // Hash password
        $password = NRAuth::hashInput($password);

        // Build SQL
        if(isset($profile_photo)) {
            $sql =
            "INSERT INTO nr_clients(username, email, password, profile_photo)
                VALUES(\"$username\", \"$email\", \"$password\", \"$url\");";
        } else {
            $sql =
            "INSERT INTO nr_clients(username, email, password, profile_photo)
                VALUES(\"$username\", \"$email\", \"$password\", \"https://glamsquad.sgp1.cdn.digitaloceanspaces.com/GlamSquad/default/images/profile.svg\");";
        }

        // Return SQL result
        $res = runSQLQuery($sql);

        if($res["response"] !== true) {
            return [
                "response" => false,
                "error_code" => 900,
                "error" => "Database error occured\n" . json_encode($res)
            ];
        }

        try {
            $this->sendWelcomeEmail($email, $username);
        }
        catch(Exception $e) {
            error_log($e);
        }

        // Register user with Twilio
        if(TWILIO_ENABLED && $res["id"] > 0) $twilioUser = (new NRChat)->register($res["id"], $username, "client");

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
                </html>"
        ];
        $message = $context->createMessage(json_encode($args));

        // Send message for queue
        $context->createProducer()->send($queue, $message);
    }

    public function registerGoogle($args) {
        error_log(print_r($args, true));
        extract($args);

        $client = new Google_Client(['client_id' => GOOGLE_APP_ID]);
        $payload = $client->verifyIdToken($idToken);
        if(!$payload) {
            return[
                "response" => false,
                "error" => "Invalid access token",
                "error_code" => 205
            ];
        }

        error_log(print_r($payload, true));

        $userName = str_replace(" ", "", $payload["name"]);
        $email = $payload["email"];
        $profilePicture = $payload["picture"];

        $sql =
        "SELECT *
            FROM nr_clients
            WHERE email = \"$email\";";

        $query = runSQLQuery($sql);

        if(isset($query["data"])) {
            return $query;
        } else {
            // Register user with random password
            $password = $this->randomString();
            $register = $this->register([
                "username" => $userName,
                "email" => $email,
                "password" => $password,
                "profile_photo" => $profilePicture
            ]);

            // Check registration success
            if($register["response"] == true) {
                $user = $this->authenticate($userName, $password);
                return $user;
            }
            else return $register;
        }
    }

    public function registerTwitter($args) {
        extract($args);

        $settings = [
            "oauth_access_token" => TWITTER_OAUTH_KEY,
            "oauth_access_token_secret" => TWITTER_OAUTH_SECRET,
            "consumer_key" => TWITTER_KEY,
            "consumer_secret" => TWITTER_SECRET
        ];

        $url = TWITTER_ENDPOINT . "users/show.json";
        $fields = "?user_id=$userId&screen_name=$userName";
        $method = "GET";

        $twitter = new TwitterAPIExchange($settings);
        $response = $twitter->setGetfield($fields)
            ->buildOauth($url, $method)
            ->performRequest();

        $response = json_decode($response, true);
        error_log(print_r($response, true));

        if($userName !== $response["screen_name"] || $userId !== $response["id"]) {
            return[
                "response" => false,
                "error" => "Invalid access token",
                "error_code" => 205
            ];
        }

        $sql =
        "SELECT *
            FROM nr_clients
            WHERE username = \"$userName\";";

        $query = runSQLQuery($sql);

        if(isset($query["data"])) {
            return $query;
        } else {
            // Register user with random password
            $password = $this->randomString();
            // FIXME: Get email permissions
            $email = "faketestemail@email.com";
            $profilePicture = $response["profile_image_url_https"];

            $register = $this->register([
                "username" => $userName,
                "email" => $email,
                "password" => $password,
                "profile_photo" => $profilePicture
            ]);

            // Check registration success
            if($register["response"] == true) return $this->authenticate($userName, $password);
            else return $register;
        }
    }

    public function registerFacebook($args) {
        extract($args);

        $fb = new Facebook\Facebook([
            "app_id" => FACEBOOK_APP_ID,
            "app_secret" => FACEBOOK_APP_SECRET,
            "default_graph_version" => FACEBOOK_GRAPH
        ]);

        try {
            // Get FB User
            $response = $fb->get('/me?fields=id,name,email,picture', $accessToken);
            $user = $response->getGraphUser();

            $profilePicture = $user["picture"]["url"];

            // Compare info
            if($email !== $user["email"] || $username !== str_replace(" ", "", $user["name"])) {
                return[
                    "response" => false,
                    "error" => "Invalid access token",
                    "error_code" => 205
                ];
            }
        } catch(Facebook\Exceptions\FacebookResponseException $e) {
            return[
                "response" => false,
                "error" => $e->getMessage(),
                "error_code" => 1
            ];
        } catch(Facebook\Exceptions\FacebookSDKException $e) {
            return[
                "response" => false,
                "error" => $e->getMessage(),
                "error_code" => 1
            ];
        }

        $sql =
        "SELECT *
            FROM nr_clients
            WHERE email = \"$email\";";

        $query = runSQLQuery($sql);

        if(isset($query["data"])) {
            return $query;
        } else {
            // Register user with random password
            $password = $this->randomString();
            $register = $this->register([
                "username" => $username,
                "email" => $email,
                "password" => $password,
                "profile_photo" => $profilePicture
            ]);

            // Check registration success
            if($register["response"] == true) {
                $user = $this->authenticate($username, $password);
                return $user;
            }
            else return $register;
        }
    }

    public function report($args) {
        extract($args);

        $sql = "INSERT INTO nr_client_reports(client_id, artist_id)
            VALUES($clientId, $artistId);";

        return runSQLQuery($sql);
    }

    public function updatePhoto($args) {
        extract($args);

        try {
            $photo = new NRImage();
            $photo->subdir = "GlamSquad/client/{$id}/images/";
            $photo->getData($picture);
            $spaces_path = $photo->upload();
            if($this->saveProfilePic($id, SPACES_CDN . $spaces_path)["response"]) {
                return [
                    "response" => true,
                    "error" => null,
                    "profile_photo" => SPACES_CDN . $spaces_path
                ];
            } else {
                throw new Exception("Error Saving Photo to Database.");
            }
        }
        catch (Exception $e) {
            error_log($e);
            return [
                "response" => false,
                "error_code" => 500,
                "error" => $e
            ];
        }
    }

    public function saveProfilePic($id, $url) {
        // Delete old profile photo
        $sql = "SELECT profile_photo
            FROM nr_clients
            WHERE id = $id;";

        $current = runSQLQuery($sql);

        if($current["data"][0]["profile_photo"]) {
            // Instantiate DO Spaces connection and delete file
            $spaces = new NRSpaces();
            $spaces->delete(explode(SPACES_CDN, $current["data"][0]["profile_photo"])[1]);
        }

        // Save new photo
        $sql = "UPDATE nr_clients
            SET profile_photo = \"$url\"
            WHERE id = $id;";

        return runSQLQuery($sql);
    }

    public function get($args) {
        // Get args
        extract($args);

        // Client SQL
        $sql =
        "SELECT *
            FROM nr_clients
            WHERE id = $id;";
        $response = runSQLQuery($sql);

        // Rating SQL
        $ratingSql = "SELECT AVG(rating) as avg
            FROM nr_client_ratings
            WHERE client_id = \"{$id}\";";
        $rating = runSQLQuery($ratingSql);
        $response["data"][0]["rating"] = $rating["data"][0]["avg"];

        // Return object
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
                break;

            default:
                // Hash password
                $password = NRAuth::hashInput($password);

                $sql =
                "UPDATE nr_clients
                SET username = \"$username\", email = \"$email\", password = \"$password\"
                WHERE id = $id;";
                break;
        }
        $response = runSQLQuery($sql);
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
        if(NRAuth::verifyInput($password, $response["data"][0]["password"]) == true) {
            return $response;
        }

        // Get client rating
        $response["data"][0]["rating"] = $this->getRating($response["data"][0]["id"]);

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
            $url = FORGOT_PASSWORD_URI . "?key=$key&type=client";
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
        "SELECT client_id, expiration_date
            FROM nr_client_forgot_password_key
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
        "UPDATE nr_clients
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
        "DELETE FROM nr_client_forgot_password_key
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

    public function validateSession($id, $key) {
        $client = $this->get(["id" => $id]);

        // If the ID exists
        if(isset($client["id"])) {
            return NRAuth::verifyUserKey($key, $client["data"][0]["username"], $client["data"][0]["password"]);
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

    /**
     * Process QR Payment from one NRClient to one NRArtist
     *
     * @param [integer] $id
     * @param [string] $cardToken
     * @param [string] $stripeAccountToken
     * @param [float] $amount
     * @return boolean
     */
    public function qrPay($args) {
        extract($args);

        // Get card
        $sql = "SELECT *
            FROM nr_payment_cards
            WHERE card_token = \"{$cardToken}\";";
        $query = runSQLQuery($sql);
        $card = $query["data"][0];

        // Get client
        $client = (new NRClient)->get(["id" => $id])["data"][0];

        // Get artist
        $artistSql =
        "SELECT *
            FROM nr_artists
            WHERE stripe_account_token = \"{$stripeAccountToken}\";";
        $artistQuery = runSQLQuery($artistSql);
        $artist = (new NRArtist)->get(["id" => $artistQuery["data"][0]["id"]]);

        // Set Stripe API key
        \Stripe\Stripe::setApiKey(STRIPE_SECRET);

        // Create client charge
        $charge = \Stripe\Charge::create([
            "amount" => $amount * 100,
            "currency" => "sgd",
            "source" => $card["card_token"],
            "customer" => $client["stripe_customer_id"],
            "description" => "QR pay charge for {$client["username"]} <{$client["email"]}>.",
            "receipt_email" => $client["email"]
        ]);
        error_log("Charging {$amount} SGD from {$client["username"]} <{$client["email"]}> with QR pay");

        // Enter receipt
        $chargeSql =
        "INSERT INTO nr_client_receipts(
            payment_amount,
            event_id,
            client_id,
            client_card_id,
            stripe_charge_id
        )
        VALUES(
            {$amount},
            0,
            {$client["id"]},
            {$card["id"]},
            \"$charge->id\"
        );";
        error_log(print_r(runSQLQuery($chargeSql), true));

        // Create artist transfer with 15% fee as usual
        $artistAmount = $amount * QR_PERCENTAGE;
        $transfer = [
            "amount" => $artistAmount * 100,
            "currency" => "sgd",
            "destination" => $artist->stripe_account_token,
            "description" => "Payment to {$artist->username} <{$artist->email}> with QR pay",
            "source_transaction" => $charge->id
        ];
        error_log("Transferring {$artistAmount} SGD to {$artist->username} <{$artist->email}> with QR pay");

        // Create artist transfer
        $transfer = \Stripe\Transfer::create($transfer);

        // Enter artist payment receipt
        $transferSql =
        "INSERT INTO nr_artist_payments(
            payment_amount,
            event_id,
            artist_id,
            artist_stripe_account,
            stripe_transfer_id
        )
        VALUES(
            {$artistAmount},
            0,
            {$artist->id},
            \"{$artist->stripe_account_token}\",
            \"{$transfer->id}\"
        );";
        error_log(print_r(runSQLQuery($transferSql), true));

        // Create payment notification
        $fcm = new NRFCM();

        // Notify Client of charge
        $notif = [
            "to" => $client["fcm_token"],
            "priority" => 'high',
            "data" => [
                "title" => "QR Pay Charge",
                "message" => "$$amount deducted for payment to artist {$artist->username}",
                'content-available'  => '1',
                "image" => 'logo'
            ]
        ];
        $fcm->send($notif, FCM_NOTIFICATION_ENDPOINT);

        // Notify Artist of payment
        $notif = [
            "to" => $artist->fcm_token,
            "priority" => 'high',
            "data" => [
                "title" => "New QR Payment",
                "message" => "Payment of $$artistAmount from {$client["username"]} transferred to account",
                'content-available'  => '1',
                "image" => 'logo'
            ]
        ];
        $fcm->send($notif, FCM_NOTIFICATION_ENDPOINT);

        return true;
    }

    private function getRating($id) {
        $sql = "SELECT AVG(rating) as avg FROM nr_client_ratings WHERE client_id = \"$id\";";
        $response = runSQLQuery($sql);
        return (isset($response["data"])) ? $response["data"][0]["avg"] : 0;
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
