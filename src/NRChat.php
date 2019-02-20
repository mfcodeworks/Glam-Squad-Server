<?php
/**
 * Class: NRChat
 * Author: MF Softworks <mf@nygmarosebeauty.com>
 * Date Created: 11/02/2019
 * Date Edited: 11/02/2019
 * Description:
 * Chat class for realtime chat communication using Twilio API
 */

use Twilio\Rest\Client;
use Twilio\Jwt\AccessToken;
use Twilio\Jwt\Grants\ChatGrant;
use Twilio\Jwt\Grants\SyncGrant;
use Twilio\Jwt\Grants\IpMessagingGrant;

require_once "database-interface.php";

class NRChat {
    private $twilio;

    public function __construct() {
        // Create Twilio Client
        $this->twilio = new Client(TWILIO_SID, TWILIO_TOKEN);
    }

    public function register($id, $username, $type) {
        // Get user data
        ($type === "client") ? $userData = (new NRClient)->get(["id" => $id])["data"][0] : $userData = (new NRArtist)->get(["id" => $id]);

        // Create new Twilio user
        $user = $this->twilio
            ->chat
            ->v2
            ->services(TWILIO_SERVICE_DEV_SID)
            ->users
            // Set identity type-username e.g. client-nygmarose
            ->create(
                "$type-$username",
                [
                    "friendlyName" => $username,
                    "attributes" => json_encode($userData, JSON_NUMERIC_CHECK | JSON_UNESCAPED_SLASHES)
                ]
            );

        return $user;
    }

    public function updateUser($twilio_sid, $update) {
        return $this->twilio
            ->chat 
            ->v2
            ->services(TWILIO_SERVICE_DEV_SID)
            ->users($twilio_sid)
            ->update($update);
    }

    public function deleteChannel($channel) {
        return $this->twilio
            ->chat
            ->v2
            ->services(TWILIO_SERVICE_DEV_SID)
            ->channels($channel)
            ->delete();
    }

    public function addToChannel($user, $type, $channel) {
        return $this->twilio
            ->chat 
            ->v2
            ->services(TWILIO_SERVICE_DEV_SID)
            ->channels($channel)
            ->members
            ->create("$type-$user");
    }

    public function removeFromChannel($user, $type, $channel) {
        return $this->twilio
            ->chat 
            ->v2
            ->services(TWILIO_SERVICE_DEV_SID)
            ->channels($channel)
            ->members("$type-$user")
            ->delete();
    }

    public static function token($args) {
        // Extract form (requires username field)
        extract($args);

        // Create new Chat API Token
        $token = new AccessToken(TWILIO_SID, TWILIO_API_KEY, TWILIO_API_SECRET, 57600, "$type-$username");

        // Add Chat Grant
        $chatGrant = new ChatGrant();
        $chatGrant->setServiceSid(TWILIO_SERVICE_DEV_SID);
        $chatGrant->setPushCredentialSid(TWILIO_NOTIFICATION_SID);
        $token->addGrant($chatGrant);

        // Add Sync Grant
        $syncGrant = new SyncGrant();
        $syncGrant->setServiceSid('default');
        $token->addGrant($syncGrant);

        // Return JSON Web Token serialized token
        return $token->toJWT();
    }
}

?>