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
define("fcmEndpoint", "https://fcm.googleapis.com/fcm/send");

class NRFCM {

    public function sendEventNotification() {
        extract($args);

        /**
         * 
         *  - Take user LatLng
         *  - 1 Lattitude = ~111km
         *  - 45/111 * 1 = 0.405
         *  - 0.405 Lattitude = 45km
         *  - LatDist = 0.405
         *  - LngKM = cos(lat decimal degrees) * 111
         *  - 45/LngKM = LngDist
         *  - 
         *  - SQL 
         *  - SELECT artistID 
         *  - FROM locations 
         *  - WHERE
         *  - Lat < userLat+LatDist 
         *  - AND lat > userLat-LatDist 
         *  - AND Lng < userLng+LngDist 
         *  - AND lng > userLng-LngDist
         */
        $latDist = 0.405;
        $latMax = $lat + $latDist;
        $latMin = $lat - $latDist;

        $lngKM = cos(deg2rad($lng)) * 111;
        $lngDist = 45/$lngKM;
        $lngMax = $lng + $lngDist;
        $lngMin = $lng - $lngDist;

        $sql =
        "SELECT artist_id
        FROM nr_artist_locations
        WHERE loc_lat < " . $latMax . "
        AND loc_lat > " . $latMin . "
        AND loc_lng < " . $lngMax . "
        AND loc_lng > " . $lngMin . "
        ;";

        return runSQLQuery($sql);
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