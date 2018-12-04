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

    public function sendEventNotification($args) {
        extract($args);

        $Dist = 15;

        $latDist = $Dist/111;

        $latMax = $lat + $latDist;
        $latMin = $lat - $latDist;
        $lngKM = cos(deg2rad($lat)) * 111;
        $lngDist = abs($Dist/$lngKM);

        $lngMax = $lng + $lngDist;
        $lngMin = $lng - $lngDist;

        $sql =
        "SELECT DISTINCT artist_id
        FROM nr_artist_locations
        WHERE loc_lat < " . $latMax . "
        AND loc_lat > " . $latMin . "
        AND loc_lng < " . $lngMax . "
        AND loc_lng > " . $lngMin . "
        ;";

        $artistData = runSQLQuery($sql);

        $artists = $artistData["data"];

        $results = [];

        error_log(print_r($artists,true));

        foreach($artists as $artist) {
            $artistId = $artist["artist_id"];

            $sql = 
            "SELECT fcm_token
            FROM nr_artist_fcm_tokens
            WHERE artist_id = $artistId;";

            error_log($sql);

            $tokenData = runSQLQuery($sql);
            foreach($tokenData["data"] as $tokenObj) {
                $tokens[] = $tokenObj["fcm_token"];
            }

            error_log(print_r($tokens,true));

            $notificationGroup = [
                "operation" => "create",
                "notification_key_name" => preg_replace("/[^A-Za-z0-9\-\_]/","-",$this->randomString(14)),
                "registration_ids" => $tokens
            ];

            $postData = json_encode($notificationGroup);
            $headers = array(
                'Content-Type:application/json',
                'Authorization:key=' . fcmKey,
                'project_id:' . fcmSenderId
            );

            error_log($postData);

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, fcmGroupEndpoint);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
            $groupId = curl_exec($ch);

            error_log($groupId);

            $notif = [
                "to" => $groupId,
                "priority" => 'high',
                "data" => [
                    "title" => "New Event Available",
                    "message" => "New event at $address",
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
            $result[] = curl_exec($ch);
        }

        error_log(json_encode($results));
        return $result;
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
                WHERE topic LIKE \"$topic\"
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
                WHERE topic LIKE \"$topic\"
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