<?php
/**
 * Class: NREvent
 * Author: MF Softworks <mf@nygmarosebeauty.com>
 * Date Created: 13/11/2018
 * Date Edited: 13/11/2018
 * Description:
 * Event class to handle new events
 */

require_once "database-interface.php";
require_once "NRResmushIt.php";

class NREvent {
    // properties
    public $id;
    public $address;
    public $lat;
    public $lng;
    public $datetime;
    public $note;
    public $price;
    public $clientId;
    public $clientCardId;
    public $references = [];
    public $packages = [];
    public $extraHours = 0;
    public $requirements = [];
    public $fulfillment = [];
    public $artists = [];
    public $ratings = [];
    
    public function __construct() {
        
    }

    public function save($args) {
        // Get arguments
        extract($args);

        // Save properties
        $this->address = $address;
        $this->lat = $lat;
        $this->lng = $lng;
        $this->datetime = date("Y-m-d H:i:s", strtotime($datetime));
        $this->note = $note;
        $this->price = $price;
        $this->clientId = $userId;

        // Set event hours
        (isset($hours)) ? $this->extraHours = $hours : $this->extraHours = 0;

        try {
            $this->clientCardId = $this->getCardId($userId, $card);
        }
        catch(Exception $e) {
            return [
                "response" => false,
                "error_code" => 107,
                "error" => $e
            ];
        }

        try {
            $this->id = $this->saveEventMeta();
        }
        catch(Exception $e) {
            return [
                "response" => false,
                "error_code" => 107,
                "error" => $e
            ];
        }

        foreach($packages as $package) {
            try { 
                $this->savePackageReference($package);
                if($package === 3) $this->saveEventHours();
            }
            catch(Exception $e) {
                return [
                    "response" => false,
                    "error_code" => 107,
                    "error" => $e
                ];
            }
        }

        foreach($this->requirements as $role => $amount) {
            $this->fulfillment[$role] = 0;
        }

        // Save images
        if (isset($photos)) {
            $filepaths = [];

            foreach ($photos as $eventPhoto) {
                try {
                    // Create photo object
                    $photo = new NRImage();
                    $photo->getData($eventPhoto);
                    $filepaths[] = $photo->filepath;
                    $this->saveImageReference($photo->publicpath);
                }
                catch(Exception $e) {
                    $this->delete(
                        [
                            "jobId" => $this->id,
                            "userId" => $this->clientId
                        ]
                    );
                    return [
                        "response" => false,
                        "error_code" => 107,
                        "error" => "Failed saving attached images"
                    ];
                }
            }

            if(count($filepaths) > 0) {
                NRImage::optimizeImage($filepaths);
            }
        }

        $fcm = new NRFCM();
        $notification = $fcm->sendEventNotification($this);

        if($notification["response"] === false) {
            $this->delete(
                [
                    "jobId" => $this->id,
                    "userId" => $this->clientId
                ]
            );
            return $notification;
        }

        return [
            'response' => true,
            'error' => null,
            'id' => $this->id,
            'fcm' => $notification
        ];
    }

    private function saveEventHours() {
        $sql =
        "INSERT INTO nr_job_extra_hours(
            event_hours_booked,
            event_id
        )
        VALUES(
            {$this->extraHours},
            {$this->id}
        );";

        return runSQLQuery($sql);
    }

    private function saveEventMeta() {
        // Build sql
        $sql = "
        INSERT INTO nr_jobs(
            event_address,
            event_lat,
            event_lng,
            event_datetime,
            event_note,
            event_price,
            client_id,
            client_card_id)
        VALUES(
            \"{$this->address}\",
            {$this->lat},
            {$this->lng},
            \"{$this->datetime}\",
            \"{$this->note}\",
            {$this->price},
            {$this->clientId},
            {$this->clientCardId}
        );
        ";

        $res = runSQLQuery($sql);

        if($res['id']) return $res['id'];
        else throw new Exception($res['error']);
    }

    private function savePackageReference($package) {
        // Build SQL
        $sql = "
        INSERT INTO nr_job_packages(
            event_package_id,
            event_id
        )
        VALUES(
            $package,
            {$this->id}
        );";

        $res = runSQLQuery($sql);

        if($res['response'] !== true) throw new Exception($res['error']);

        $this->packages[] = $package;

        $sql =
        "SELECT r.role_name, pr.role_amount_required
            FROM nr_package_roles as pr
            LEFT JOIN nr_job_roles as r ON r.id = pr.role_id
            WHERE pr.package_id = $package";
        
        $res = runSQLQuery($sql);

        if($res['response'] !== true) throw new Exception($res['error']);

        foreach($res['data'] as $requirement) {
            (isset( $this->requirements[ $requirement['role_name'] ] )) ? $this->requirements[ $requirement['role_name'] ] += $requirement['role_amount_required'] : $this->requirements[ $requirement['role_name'] ] = $requirement['role_amount_required'];
        }
    }

    private function saveImageReference($filepath) {
        // Build SQL
        $sql = "
        INSERT INTO nr_job_references(
            event_reference_photo,
            event_id
        )
        VALUES(
            \"$filepath\",
            {$this->id}
        );
        ";

        $res = runSQLQuery($sql);

        if($res['response'] === true) {
            $this->references[] = $res['id'];
            return;
        }
        else {
            throw new Exception($res['error']);
        }
    }

    private function getCardId($user, $card) {
        $sql = 
        "SELECT *
            FROM nr_payment_cards
            WHERE card_token LIKE \"$card\"
            AND client_id = $user;
            ";

        $res = runSQLQuery($sql);

        if($res["data"][0]['id']) {
            return $res["data"][0]['id'];
        }
        else {
            throw new Exception($res["error"]);
        }
    }

    public function get($args) {
        extract($args);

        // Get event
        if(isset($userId)) {
            $sql =
            "SELECT id            
            FROM nr_jobs
            WHERE client_id = $userId
            ORDER BY event_datetime DESC;";

            $res = runSQLQuery($sql);

            if(!$res["response"] === true) {
                return $res;
            }
            
            $eventIds = $res["data"];
            foreach($eventIds as $eventId) {
                $event = new NREvent();
                $event->getSingle($eventId['id']);
                $events[] = $event;
            }

            return [
                "response" => true,
                "error" => null,
                "data" => $events
            ];
        }
        if(isset($jobId)) {
            $event = new NREvent();
            $event->getSingle($jobId);

            return [
                "response" => true,
                "error" => null,
                "data" => $event
            ];
        }
    }

    public function cancel($args) {
        extract($args);

        $sql = "DELETE FROM nr_artist_jobs
            WHERE artist_id = $userId
            AND event_id = $id;";

        return runSQLQuery($sql);
    }

    public function getSingle($id) {
        $this->id = $id;

        $sql = 
        "SELECT 
            j.id, 
            j.event_address, 
            j.event_lat, 
            j.event_lng, 
            j.event_datetime, 
            j.event_note, 
            j.event_price, 
            j.client_id, 
            j.client_card_id,
            GROUP_CONCAT(p.event_package_id) as event_package_id
            FROM nr_jobs as j
            LEFT JOIN nr_job_packages as p ON j.id = p.event_id
            WHERE j.id = {$this->id}
            GROUP BY j.id;";

        extract(runSQLQuery($sql)["data"][0]);

        // Save basic properties
        $this->address = $event_address;
        $this->lat = $event_lat;
        $this->lng = $event_lng;
        $this->datetime = (new Datetime($event_datetime))->format(Datetime::ATOM);
        $this->note = $event_note;
        $this->price = $event_price;
        $this->clientId = $client_id;
        $this->clientCardId = $client_card_id;
        
        // Create package array, accounting for single package jobs
        if(strpos($event_package_id, ",") > -1) $event_package_id = explode(",", $event_package_id);
        else $event_package_id = [$event_package_id];
        $this->packages = $event_package_id;

        if(in_array(3, $this->packages)) $this->getHours();
        
        // Get requirement properties
        for($i = 0; $i < count($this->packages); $i++) {
            try {
                $this->packages[$i] = $this->getPackageRequirements($this->packages[$i]);
            }
            catch(Exception $e) {
                return [
                    "response" => false,
                    "error_code" => 107,
                    "error" => $e
                ];
            }
        }

        // Get event image references
        try {
            $this->getReferences();
        }
        catch(Exception $e) {
            return [
                "response" => false,
                "error_code" => 107,
                "error" => $e
            ];
        }

        // Get event artists
        try {
            $this->getEventArtists();
        }
        catch(Exception $e) {
            return [
                "response" => false,
                "error_code" => 107,
                "error" => $e
            ];
        }

        // Get ratings for event
        try {
            $this->getRatings();
        }
        catch(Exception $e) {
            error_log($e);
        }
    }

    private function getRatings() {
        // Get artist ratings
        $sql =
        "SELECT artist_id, client_id, rating
            FROM nr_artist_ratings
            WHERE event_id = {$this->id};";

        $res = runSQLQuery($sql);

        $this->ratings["artists"] = [];

        if(!isset($res["data"])) return;

        foreach($res["data"] as $rating) {
            $this->ratings["artists"][ $rating["artist_id"] ][] = $rating["rating"];
        }

        // Get client ratings
        $sql =
        "SELECT artist_id, client_id, rating
            FROM nr_client_ratings
            WHERE event_id = {$this->id};";

        $res = runSQLQuery($sql);

        $this->ratings["clients"] = [];

        if(!isset($res["data"])) return;

        foreach($res["data"] as $rating) {
            $this->ratings["clients"][ $rating["client_id"] ][] = $rating["rating"];
        }
    }

    private function getHours() {
        $sql =
        "SELECT event_hours_booked as hours
            FROM nr_job_extra_hours
            WHERE event_id = {$this->id};";

        $res = runSQLQuery($sql);

        $this->extraHours = $res["data"][0]["hours"];
    }

    private function getEventArtists() {
        $sql =
        "SELECT artist_id
            FROM nr_artist_jobs
            WHERE event_id = {$this->id};";

        $res = runSQLQuery($sql);
        if($res['response'] !== true) throw new Exception($res['error']);
        if(!isset($res["data"])) return;

        foreach($res['data'] as $artistId) {
            $artist = new NRArtist();
            $artist->get(["userId" => $artistId['artist_id']]);

            // Remove irrelevant information
            unset($artist->usernameHash);
            unset($artist->bookings);
            unset($artist->fcmTokens);
            unset($artist->fcmTopics);
            unset($artist->locations);
            unset($artist->receipts);
            unset($artist->stripe_account_token);

            $this->fulfillment[$artist->role["name"]]++;
            $this->artists[] = $artist;
        }
    }

    private function getReferences() {
        $sql =
        "SELECT id, event_reference_photo as photo
            FROM nr_job_references
            WHERE event_id = {$this->id};";

        $res = runSQLQuery($sql);
        if($res["response"] === true) {
            $this->references = $res["data"];
        }
    }

    private function getPackageRequirements($id) {
        $sql =
        "SELECT p.id, p.package_name, p.package_description, pr.role_id, pr.role_amount_required, r.role_name
            FROM nr_packages as p
            LEFT JOIN nr_package_roles as pr ON p.id = pr.package_id
            LEFT JOIN nr_job_roles as r ON r.id = pr.role_id
            WHERE p.id = $id;";

        $res = runSQLQuery($sql);

        if($res["response"] === true) {
            $requirements = $res["data"];
            $package = $requirements[0];

            foreach($requirements as $requirement) {
                $this->fulfillment[ $requirement['role_name'] ] = 0;
                (isset( $this->requirements[ $requirement['role_name'] ] )) ? $this->requirements[ $requirement['role_name'] ] += $requirement['role_amount_required'] : $this->requirements[ $requirement['role_name'] ] = $requirement['role_amount_required'];
            }

            return [
                "id" => $id,
                "name" => $package["package_name"],
                "description" => $package["package_description"]
            ];
        }
        else {
            throw new Exception($res['error']);
        }
    }

    public function getNew($args) {
        extract($args);

        $artist = new NRArtist();
        $artist->get(["id" => $userId]);

        $sql =
        "SELECT id, loc_lat as lat, loc_lng as lng
            FROM nr_artist_locations
            WHERE artist_id = {$artist->id};";
        
        $res = runSQLQuery($sql);

        if($res["response"] !== true || !isset($res["data"])) {
            return[
                "response" => false,
                "error_code" => 611,
                "error" => "Artist has no locations saved."
            ];
        }

        $locations = $res["data"];

        foreach($locations as $location) {

            // Set degree distance finder object with a range of 30km
            $distance = new DegreeDistanceFinder(JOB_DISTANCE);
            $distance->lat = $location["lat"];
            $distance->lng = $location["lng"];
    
            // Get lat/lng range
            $latRange = $distance->latRange();
            $lngRange = $distance->lngRange();

            // Get events and packages that are in the future
            $sql = 
            "SELECT id
                FROM nr_jobs
                WHERE event_datetime >= CURDATE()
                AND event_lat < {$latRange['max']}
                AND event_lat > {$latRange['min']}
                AND event_lng < {$lngRange['max']}
                AND event_lng > {$lngRange['min']}
                ORDER BY event_datetime DESC;";

            $res = runSQLQuery($sql);

            if($res["response"] !== true || $res["response"] === true && !isset($res["data"])) {
                return[
                    "response" => false,
                    "error_code" => 611,
                    "error" => "No nearby events."
                ];
            }

            // Merge events near this location to event list
            foreach($res["data"] as $eventId) {
                $eventList[] = $eventId['id'];
            }
        }

        // After loop prevent overlap
        if(!isset($eventList)) return;
        $eventList = array_unique($eventList);
        $events = [];

        // Loop through IDs
        foreach($eventList as $id) {
            $event = new NREvent();
            $event->getSingle($id);

            // Save event if artist is needed
            foreach($event->requirements as $role => $required) {
                $roleNeeded = false;
                $alreadyAccepted = false;

                // If the role being check is the artists role and the requirement is greater than whats fulfilled, save event
                if($role === $artist->role["name"] && $event->requirements[$role] > $event->fulfillment[$role]) {
                    $roleNeeded = true;
                }
                foreach($event->artists as $eventArtist) {
                    if($eventArtist->id === $artist->id) {
                        $alreadyAccepted = true;
                    }
                }
                if($roleNeeded && !$alreadyAccepted) $events[] = $event;
            }
        }

        return[
            "response" => true,
            "error" => null,
            "data" => $events
        ];
    }

    public static function getPackages() {
        $sql = 
        "SELECT id, package_name as name, package_description as description, ROUND(package_price, 2) as price
            FROM nr_packages;";

        return runSQLQuery($sql);
    }

    public static function saveClientAttendance($args) {
        extract($args);

        ($attendance) ? $attendance = 1 : $attendance = 0;

        $sql = 
        "INSERT INTO nr_job_client_attendance(
            event_id, 
            client_id,
            attendance
        )
        VALUES(
            $eventId, 
            $userId,
            $attendance
        );";

        return runSQLQuery($sql);
    }

    public static function saveArtistAttendance($args) {
        extract($args);

        ($attendance) ? $attendance = 1 : $attendance = 0;

        $sql = 
        "INSERT INTO nr_job_artist_attendance(
            event_id,
            artist_id,
            attendance
        )
        VALUES(
            $eventId, 
            $userId,
            $attendance
        );";

        return runSQLQuery($sql);
    }

    public static function getRecentlyCompletedEvents($args) {
        extract($args);

        switch($type) {
            case "client":
                // Get recently completed, unpaid jobs for client
                $sql = 
                "SELECT j.id 
                    FROM nr_jobs as j 
                    LEFT JOIN nr_client_receipts as r ON j.id = r.event_id
                    WHERE r.event_id IS NULL
                    AND TIMESTAMPDIFF(MINUTE, NOW(), j.event_datetime) <= 0
                    AND j.client_id = $userId;";

                $data = runSQLQuery($sql);

                if(!isset($data["data"])) return $data;

                // Set count before events get removed
                $count = count($data["data"]);

                for($i = 0; $i < $count; $i++) {
                    // Check if attendance already sent 
                    $sql = 
                    "SELECT * FROM nr_job_artist_attendance 
                        WHERE event_id = {$data["data"][$i]["id"]} 
                        AND client_id = $userId;";
                        
                    $check = runSQLQuery($sql);
                    
                    if(isset($check["data"])) {
                        unset($data["data"][$i]);
                        continue;
                    }

                    $event = new NREvent();
                    $event->getSingle($data["data"][$i]["id"]);
                    $data["data"][$i] = $event;
                }

                // Reindex events array after possible event deletion
                $data["data"] = array_values($data["data"]);
                
                return $data;

            case "artist":
                // Get recently completed, unpaid jobs for artist where the artist hasn't confirmed the event
                $sql = 
                "SELECT j.id 
                    FROM nr_jobs as j 
                    LEFT JOIN nr_client_receipts as r ON j.id = r.event_id
                    LEFT JOIN nr_artist_jobs as a ON j.id = a.event_id
                    WHERE r.event_id IS NULL
                    AND TIMESTAMPDIFF(MINUTE, NOW(), j.event_datetime) <= 0
                    AND a.artist_id = 2;";

                $data = runSQLQuery($sql);

                if(!isset($data["data"])) return $data;

                // Set count before events get removed
                $count = count($data["data"]);

                for($i = 0; $i < $count; $i++) {
                    // Check if attendance already sent 
                    $sql = 
                    "SELECT * FROM nr_job_client_attendance 
                        WHERE event_id = {$data["data"][$i]["id"]} 
                        AND artist_id = $userId;";

                    $check = runSQLQuery($sql);

                    if(isset($check["data"])) {
                        unset($data["data"][$i]);
                        continue;
                    }

                    $event = new NREvent();
                    $event->getSingle($data["data"][$i]["id"]);

                    $data["data"][$i] = $event;
                }

                // Reindex events array after possible event deletion
                $data["data"] = array_values($data["data"]);
                
                return $data;
        }
    }

    public function update($args) {
        extract($args);

        if(isset($address)) {
            $sql =
            "UPDATE nr_jobs
            SET address = \"$address\"
            WHERE id = $jobId;";
    
            $res = runSQLQuery($sql);
            if($res["response"] !== true) {
                return $res;
            }
        }
        if(isset($datetime)) {
            $sql =
            "UPDATE nr_jobs
            SET datetime = \"$datetime\"
            WHERE id = $jobId;";
    
            $res = runSQLQuery($sql);
            if($res["response"] !== true) {
                return $res;
            }
        }
        if(isset($cardId)) {
            $sql =
            "UPDATE nr_jobs
            SET client_card_id = $cardId
            WHERE id = $jobId;";
    
            $res = runSQLQuery($sql);
            if($res["response"] !== true) {
                return $res;
            }
        }
        return $res;
    }

    public function apply($args) {
        extract($args);

        $event = new NREvent();
        $event->getSingle($id);

        $artist = new NRArtist();
        $artist->get(["id" => $userId]);

        $sql = 
        "INSERT INTO nr_artist_jobs(event_id, artist_id)
            VALUES(
                \"{$event->id}\",
                \"{$artist->id}\"
            );";

        foreach($event->artists as $eventArtist) {
            if($eventArtist->id === $artist->id) {
                return [
                    "response" => false,
                    "error_code" => 107,
                    "error" => "Artist has already accepted this booking"
                ];
            }
        }
        
        // Save event if artist is needed
        foreach($event->requirements as $role => $required) {
            // If the role being check is the artists role and the requirement is greater than whats fulfilled, save event
            if($role === $artist->role["name"] && $event->requirements[$role] > $event->fulfillment[$role]) {
                // Run SQL
                $res = runSQLQuery($sql);
                
                // Send client notification
                if($res["response"] === true) {
                    $notif = [
                        "to" => "/topics/event-{$event->id}-client",
                        "priority" => 'high',
                        "data" => [
                            "title" => "New Artist",
                            "message" => "Artist {$artist->username} has accepted the booking at {$event->address}",
                            'content-available'  => '1',
                            "image" => 'logo'
                        ]
                    ];
                    $fcm = new NRFCM();
                    $fcm->send($notif, FCM_NOTIFICATION_ENDPOINT);
                }

                // Return SQL response
                return $res;
            }
        }

        // If no role had an open position
        return [
            "response" => false,
            "error_code" => 107,
            "error" => "This booking is no longer available"
        ];
    }

    public static function artistRating($args) {
        extract($args);

        $sql = 
        "INSERT INTO nr_artist_ratings(
            event_id, 
            client_id, 
            artist_id, 
            rating
        )
        VALUES(
            $eventId,
            $userId,
            $artistId,
            $rating
        );";

        return runSQLQuery($sql);
    }

    public static function clientRating($args) {
        extract($args);

        $sql = 
        "INSERT INTO nr_client_ratings(
            event_id, 
            client_id, 
            artist_id, 
            rating
        )
        VALUES(
            $userId,
            $artistId,
            $eventId,
            $rating
        );";

        return runSQLQuery($sql);

    }

    public function delete($args) {
        extract($args);

        $sql = 
        "DELETE from nr_jobs
        WHERE id = $jobId
        AND client_id = $userId;";

        return runSQLQuery($sql);
    }

    private function randomString($length = 32) {
        // Create random string with current date salt for uniqueness
        return date('Y-m-d-H-i-s').bin2hex(random_bytes($length));;
    }
}

?>