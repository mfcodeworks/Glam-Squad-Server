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

class NRFCM {
    // FCM Headers
    private $headers = array(
        'Content-Type:application/json',
        'Authorization:key=' . FCM_KEY,
        'project_id:' . FCM_SENDER_ID
    );

    public function __construct() {

    }

    public function send($payload, $endpoint) {
        $data = json_encode($payload, JSON_PRETTY_PRINT);

        error_log($data);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $endpoint);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $this->headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);

        return curl_exec($ch);
    }

    public function get($url) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $this->headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        return curl_exec($ch);
    }

    public function sendEventNotification($event) {
        // Log event
        error_log(json_encode($event, JSON_PRETTY_PRINT));

        // Set degree distance finder object with a range of 30km
        $distance = new DegreeDistanceFinder(JOB_DISTANCE);
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
                "error_code" => $res['error_code'],
                'error' => $res['error']
            ];
        }

        // Check artists exist
        if(!isset($res["data"])) {
            return [
                "response" => false,
                "error_code" => 611,
                "error" => "Unfortunately at the moment there's no artists available within your area."
            ];
        }
        
        // Save artists to a variable
        $artistList = $res["data"];

        // Loop through IDs to get artist object
        foreach($artistList as $id) {

            // Get artist
            $artist = new NRArtist();
            $artist->get([
                "id" => $id['artist_id']
            ]);

            // If artist is needed for this job save reference and track requirement fulfillment
            if(isset($event->requirements[$artist->role["name"]])) {
                $event->fulfillment[$artist->role["name"]]++;
                $artists[] = $artist;
            }
        }

        // Check requirement fulfillment
        foreach($event->requirements as $role => $requirement) {
            if($event->requirements[$role] > $event->fulfillment[$role]) return[
                "response" => false,
                "error_code" => 611,
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

            $notificationGroup = [
                "operation" => "create",
                "notification_key_name" => preg_replace("/[^A-Za-z0-9\-\_]/","-",$this->randomString(14)),
                "registration_ids" => $tokens
            ];
            
            $group = json_decode($this->send($notificationGroup, FCM_GROUP_ENDPOINT), true)["notification_key"];

            $notif = [
                "to" => $group,
                "priority" => 'high',
                "data" => [
                    "title" => "New Event Available",
                    "message" => "New event at {$event->address}",
                    'content-available'  => '1',
                    "image" => 'logo'
                ]
            ];

            $fcmResponses[] = $this->send($notif, FCM_NOTIFICATION_ENDPOINT);
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

        $group = [
            "operation" => "create",
            "notification_key_name" => "{$type}-{$id}",
            "registration_ids" => [$fcm_token]
        ];
            
        $response = json_decode($this->send($group, FCM_GROUP_ENDPOINT), true);

        if(isset($response["notification_key"])) {
            $notification_token = $response["notification_key"];
        } else {
            $groupUri = FCM_GROUP_ENDPOINT . "?notification_key_name={$type}-{$id}";
            $groupId = json_decode($this->get($groupUri), true)["notification_key"];

            $group = [
                "operation" => "add",
                "notification_key" => $groupId,
                "notification_key_name" => "{$type}-{$id}",
                "registration_ids" => [$fcm_token]
            ];

            $notification_token = json_decode($this->send($group, FCM_GROUP_ENDPOINT), true)["notification_key"];
        }

        error_log(print_r($group, true));

        switch($type) {
            case "artist":
                $sql = 
                "UPDATE nr_artists
                    SET fcm_token = \"$notification_token\"
                    WHERE id = $id;";
        
                return runSQLQuery($sql);
                break;

            case "client":
                $sql = 
                "UPDATE nr_clients
                    SET fcm_token = \"$notification_token\"
                    WHERE id = $id;";
        
                return runSQLQuery($sql);
                break;
        }
    }

    public function registerTopic($args) {
        extract($args);

        switch($type) {
            case "client":
                // Build SQL
                $sql = 
                "INSERT INTO nr_client_fcm_topics(fcm_topic, client_id)
                    VALUES(\"$topic\", $id);";
                return runSQLQuery($sql);
                break;
                
            case "artist":
                // Build SQL
                $sql = 
                "INSERT INTO nr_artist_fcm_topics(fcm_topic, artist_id)
                    VALUES(\"$topic\", $id);";
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
                $sql = 
                "SELECT *
                    FROM nr_client_fcm_topics
                    WHERE client_id = $id;";
                return runSQLQuery($sql);
                break;

            case "artist":
                // Build SQL
                $sql = 
                "SELECT *
                    FROM nr_artist_fcm_topics
                    WHERE artist_id = $id;";
                return runSQLQuery($sql);
                break;
        }
    }
}

?>