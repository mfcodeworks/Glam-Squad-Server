<?php
/**
 * Class: NRFCM
 * Author: MF Softworks <mf@nygmarosebeauty.com>
 * Date Created: 13/11/2018
 * Date Edited: 13/11/2018
 * Description:
 * FCM class to communicate with FCM server
 */

require_once "database-interface.php";

define("fcmKey", "AIzaSyAwxC-XPBcbfQxVzmHzwPNQCWCuM-TiAoc");
define('fcmSenderId', '427808297057');
define("fcmEndpoint", "https://fcm.googleapis.com/fcm/send");
define("fcmGroupEndpoint", "https://fcm.googleapis.com/fcm/notification");

class NRFCM {
    public function __construct() {

    }

    public function sendEventNotification($event) {
        // Log event
        error_log(json_encode($event));

        // Set degree distance finder object with a range of 30km
        $distance = new DegreeDistanceFinder(30);
        $distance->lat = $event->lat;
        $distance->lng = $event->lng;

        // Get lat/lng range
        $latRange = $distance->latRange();
        $lngRange = $distance->lngRange();

        // Get artists within location area
        $sql =
        "SELECT DISTINCT artist_id
            FROM nr_artist_locations
            WHERE loc_lat < {$latRange['max']}
            AND loc_lat > {$latRange['min']}
            AND loc_lng < {$lngRange['max']}
            AND loc_lng > {$lngRange['min']}
        ;";

        $res = runSQLQuery($sql);

        // Check query passed
        if($res['response'] !== true) {
            return [
                'response' => false,
                'error' => $res['error']
            ];
        }

        // Check artists exist
        if(!isset($res["data"])) {
            return [
                "response" => false,
                "error" => "Unfortunately at the moment there's no artists available within your area."
            ];
        }
        
        // Save artists to a variable
        $artistList = $res["data"];

        // Track if any requirement couldn't be fulfilled
        $pseudoRequirement = $event->requirements;

        // Loop through IDs to get artist object
        foreach($artistList as $id) {

            // Get artist
            $artist = new NRArtist();
            $artist->get([
                "userId" => $id['artist_id']
            ]);

            // If artist is needed for this job save reference and track requirement fulfillment
            if(isset($event->requirements[$artist->role])) {
                $pseudoRequirement[$artist->role]--;
                $artists[] = $artist;
            }
        }

        // Check requirement fulfillment
        foreach($pseudoRequirement as $role => $requirement) {
            if($requirement > 0) return[
                "response" => false,
                "error" => "No $role available within your area. Try booking a package without $role included."
            ];
        }

        // Loop through artists
        for($i = 0; $i < count($artists); $i++) {
            $artist = $artists[$i];

            $sql = 
            "SELECT fcm_token
                FROM nr_artist_fcm_tokens
                WHERE artist_id = {$artist->id};";

            $tokenData = runSQLQuery($sql);

            foreach($tokenData["data"] as $tokenObj) {
                $tokens[] = $tokenObj["fcm_token"];
            }

            $headers = array(
                'Content-Type:application/json',
                'Authorization:key=' . fcmKey,
                'project_id:' . fcmSenderId
            );

            $notificationGroup = [
                "operation" => "create",
                "notification_key_name" => preg_replace("/[^A-Za-z0-9\-\_]/","-",$this->randomString(14)),
                "registration_ids" => $tokens
            ];

            $postData = json_encode($notificationGroup);

            error_log($postData);

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, fcmGroupEndpoint);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
            $groupId = json_decode(curl_exec($ch), true)["notification_key"];

            error_log($groupId);
            $formattedDatetime = 

            $notif = [
                "to" => $groupId,
                "priority" => 'high',
                "data" => [
                    "title" => "New Event Available",
                    "message" => "New event at {$event->address}",
                    'content-available'  => '1',
                    "image" => 'logo'
                ]
            ];

            $postNotif = json_encode($notif);

            error_log($postNotif);
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, fcmEndpoint);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $postNotif);
            $fcmResponses[] = curl_exec($ch);
        }

        return [
            "response" => true,
            "error" => null,
            "fcm_responses" => $fcmResponses
        ];
    }

    private function randomString($length = 32) {
        // Create random string with current date salt for uniqueness
        return date('Y-m-d-H-i-s').bin2hex(random_bytes($length));;
    }

    public function registerToken($args) {
        extract($args);

        $sql = 
        "INSERT INTO nr_artist_fcm_tokens(fcm_token, artist_id)
        VALUES(\"$token\", $userId);";

        return runSQLQuery($sql);
    }

    public function registerTopic($args) {
        extract($args);

        switch($type) {
            case "client":
                $sql = 
                "SELECT *
                FROM nr_client_fcm_topics
                WHERE fcm_topic LIKE \"$topic\"
                AND client_id = $userId;";
        
                if(isset(runSQLQuery($sql)["data"][0]["id"])) {
                    $res["response"] = false;
                    $res["error"] = "Topic already exists for user.";
                    return $res;
                }
        
                // Build SQL
                $sql = 
                "INSERT INTO nr_client_fcm_topics(fcm_topic, client_id)
                VALUES(\"$topic\", $userId);
                ";
        
                return runSQLQuery($sql);
                break;
                
            case "artist":
                $sql = 
                "SELECT *
                FROM nr_artist_fcm_topics
                WHERE fcm_topic LIKE \"$topic\"
                AND artist_id = $userId;";
        
                if(isset(runSQLQuery($sql)["data"][0]["id"])) {
                    $res["response"] = false;
                    $res["error"] = "Topic already exists for artist.";
                    return $res;
                }
        
                // Build SQL
                $sql = 
                "INSERT INTO nr_artist_fcm_topics(fcm_topic, artist_id)
                VALUES(\"$topic\", $userId);
                ";
        
                return runSQLQuery($sql);
                break;
        }
    }

    public function getTopics($args) {
        extract($args);

        // Switch request type
        switch($type) {
            case "client":
                // Build SQL
                $sql = "
                SELECT *
                FROM nr_client_fcm_topics
                WHERE client_id = $userId;
                ";

                return runSQLQuery($sql);
                break;
            case "artist":
                // Build SQL
                $sql = "
                SELECT *
                FROM nr_artist_fcm_topics
                WHERE artist_id = $userId;
                ";

                return runSQLQuery($sql);
                break;
        }
    }
}

?>