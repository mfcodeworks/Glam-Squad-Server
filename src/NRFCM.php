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
        $artistList = runSQLQuery(
            "SELECT DISTINCT artist_id
            FROM nr_artist_locations
            WHERE loc_lat < {$latRange['max']}
            AND loc_lat > {$latRange['min']}
            AND loc_lng < {$lngRange['max']}
            AND loc_lng > {$lngRange['min']};"
        )["data"];

        // Check artists exist
        if(!isset($artistList)) {
            return [
                "response" => false,
                "error_code" => 611,
                "error" => "Unfortunately at the moment there's no artists available within your area."
            ];
        }

        // Loop through IDs to get artist object
        foreach($artistList as $id) {
            // Get artist
            $artist = new NRArtist();
            $artist->get([
                "id" => $id['artist_id']
            ]);

            // If artist already accepted booking, continue to next artist
            foreach($event->artists as $bookedArtist) {
                if($bookedArtist["id"] == $artist->id) continue 2;
            }

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
        foreach($artists as $artist) {
            // Clear Artists Notification Cache
            $redis = new Redis;
            $redis->connect(REDIS_HOST);
            $redis->delete("artist-{$artist->id}-events-new");

            // Format datetime for client JS consumption (SQL requires Y-m-d H:i:s)
            $event->datetime = (new Datetime($event->datetime))->format(Datetime::ATOM);

            // Send notification and save response
            $fcmResponses[] = $this->send(
                [
                    "to" => $artist->fcmToken,
                    "priority" => 'high',
                    "data" => [
                        "title" => "New Event Available",
                        "message" => "New event at {$event->formatAddress()}",
                        "content-available"  => "1",
                        "image" => 'logo',
                        "notId" => $event->id,
                        "newEvent" => (array) $event,
                    ]
                ], FCM_NOTIFICATION_ENDPOINT
            );
        }

        // Set job proposal as sent
        $event->setEventArtistProposalSent();

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

            $operation = json_decode($this->send($group, FCM_GROUP_ENDPOINT), true);
            if(isset($operation["error"])) {
                error_log($operation["error"]."\n\n".print_r($group, true));
                return[
                    "response" => false,
                    "error" => $operation["error"],
                    "data" => $group
                ];
            }
            $notification_token = $operation["notification_key"];
        }

        error_log(print_r($group, true));

        switch($type) {
            case "artist":
                return runSQLQuery(
                    "UPDATE nr_artists
                    SET fcm_token = \"$notification_token\"
                    WHERE id = $id;"
                );

            case "client":
                return runSQLQuery(
                    "UPDATE nr_clients
                    SET fcm_token = \"$notification_token\"
                    WHERE id = $id;"
                );
        }
    }

    public function registerTopic($args) {
        extract($args);

        // Save topic
        switch($type) {
            case "client":
                return runSQLQuery(
                    "INSERT INTO nr_client_fcm_topics(fcm_topic, client_id)
                    VALUES(\"$topic\", $id);"
                );

            case "artist":
                return runSQLQuery(
                    "INSERT INTO nr_artist_fcm_topics(fcm_topic, artist_id)
                    VALUES(\"$topic\", $id);"
                );
        }
    }

    public function deleteTopic($args) {
        extract($args);

        // Delete topic
        switch($type) {
            case "client":
                return runSQLQuery(
                    "DELETE FROM nr_client_fcm_topics(fcm_topic, client_id)
                    WHERE fcm_topic = \"$topic\"
                    AND client_id = $id);"
                );

            case "artist":
                return runSQLQuery(
                    "DELETE FROM nr_artist_fcm_topics(fcm_topic, client_id)
                    WHERE fcm_topic = \"$topic\"
                    AND client_id = $id);"
                );
        }
    }

    public function getTopics($args) {
        extract($args);

        // Return topics
        switch($type) {
            case "client":
                return runSQLQuery(
                    "SELECT *
                    FROM nr_client_fcm_topics
                    WHERE client_id = $id;"
                );

            case "artist":
                return runSQLQuery(
                    "SELECT *
                    FROM nr_artist_fcm_topics
                    WHERE artist_id = $id;"
                );
        }
    }
}

?>
