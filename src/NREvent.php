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
        error_log(print_r($args, true));

        // Get arguments
        extract($args);

        // Save properties
        $this->address = $address;
        $this->lat = $lat;
        $this->lng = $lng;
        $this->datetime = date("Y-m-d H:i:s", substr($datetime, 0, 10));
        $this->note = $note;
        $this->price = $price;
        $this->clientId = $userId;

        // Set event hours
        (isset($hours)) ? $this->extraHours = $hours : $this->extraHours = 0;

        try {
            $this->clientCardId = $this->getCardId($userId, $card);
        }
        catch(Exception $e) {
            error_log($e);
            return [
                "response" => false,
                "error_code" => 107,
                "error" => "Failed saving client card",
                "exception" => $e
            ];
        }

        try {
            $this->id = $this->saveEventMeta();
        }
        catch(Exception $e) {
            error_log($e);
            return [
                "response" => false,
                "error_code" => 107,
                "error" => "Failed saving event data",
                "exception" => $e
            ];
        }

        foreach($packages as $package) {
            try {
                $this->savePackageReference($package);
                if($package === 3) $this->saveEventHours();
            }
            catch(Exception $e) {
                $this->delete([
                    "eventId" => $this->id,
                    "id" => $this->clientId
                ]);
                error_log($e);
                return [
                    "response" => false,
                    "error_code" => 107,
                    "error" => "Failed saving event packages",
                    "exception" => $e
                ];
            }
        }

        foreach($this->requirements as $role => $amount) {
            $this->fulfillment[$role] = 0;
        }

        // Save images
        if (isset($photos)) {
            foreach ($photos as $eventPhoto) {
                try {
                    // Create photo object
                    $photo = new NRImage();
                    $photo->subdir = "GlamSquad/event/{$this->id}/images/";
                    error_log($photo->getData($eventPhoto));
                    error_log("Submitting to Spaces");
                    $spaces_path = $photo->uploadToSpaces();
                    $this->saveImageReference(SPACES_CDN . $spaces_path);
                }
                catch(Exception $e) {
                    $this->delete([
                        "eventId" => $this->id,
                        "id" => $this->clientId
                    ]);
                    error_log($e);
                    return [
                        "response" => false,
                        "error_code" => 107,
                        "error" => "Failed saving attached images",
                        "exception" => $e
                    ];
                }
            }
        }

        $fcm = new NRFCM();
        $notification = $fcm->sendEventNotification($this);

        if($notification["response"] === false) {
            $this->delete([
                "eventId" => $this->id,
                "id" => $this->clientId
            ]);
            error_log(print_r($notification, true));
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

        if(isset($res['id'])) return $res['id'];
        else throw new Exception(json_encode($res));
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

        if($res['response'] !== true) throw new Exception(json_encode($res));

        $this->packages[] = $package;

        // Skip role requirement for MUA extra hours
        if($package == 3) return;

        $sql =
        "SELECT r.role_name, pr.role_amount_required
            FROM nr_package_roles as pr
            LEFT JOIN nr_job_roles as r ON r.id = pr.role_id
            WHERE pr.package_id = $package";

        $res = runSQLQuery($sql);

        if($res['response'] !== true) throw new Exception(json_encode($res));

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
            throw new Exception(json_encode($res));
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
        $sql =
        "SELECT id
            FROM nr_jobs
            WHERE client_id = $id
            ORDER BY event_datetime DESC;";

        $res = runSQLQuery($sql);

        if($res["response"] !== true || !$res["data"]) {
            return $res;
        }

        $eventList = $res["data"];
        foreach($eventList as $event) {
            $events[] = (new NREvent)->getSingle($event["id"]);
        }

        return [
            "response" => true,
            "error" => null,
            "data" => $events
        ];
    }

    public function cancel($args) {
        extract($args);

        $artist = (new NRArtist())->get(["id" => $userId]);

        $sql = "DELETE FROM nr_artist_jobs
            WHERE artist_id = $userId
            AND event_id = $id;";

        $chat = (new NRChat())->removeFromChannel($artist->username, "artist", "event-$id");

        $this->getSingle($id);

        $response = runSQLQuery($sql);
        $response["clientId"] = $this->clientId;
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

        $res = runSQLQuery($sql)["data"][0];

        extract($res);

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

        return $this;
    }

    private function getRatings() {
        // Get artist ratings
        $sql =
        "SELECT artist_id, client_id, rating
            FROM nr_artist_ratings
            WHERE event_id = {$this->id};";

        $res = runSQLQuery($sql);

        $this->ratings["artists"] = [];

        if(isset($res["data"]))
            foreach($res["data"] as $rating)
                $this->ratings["artists"][ $rating["artist_id"] ][] = $rating["rating"];

        // Get client ratings
        $sql =
        "SELECT artist_id, client_id, rating
            FROM nr_client_ratings
            WHERE event_id = {$this->id};";

        $res = runSQLQuery($sql);

        $this->ratings["clients"] = [];

        if(isset($res["data"]))
            foreach($res["data"] as $rating)
                $this->ratings["clients"][ $rating["client_id"] ][] = $rating["rating"];
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
        if($res['response'] !== true) throw new Exception(json_encode($res));
        if(!isset($res["data"])) return;

        foreach($res['data'] as $artistId) {
            $artist = new NRArtist();
            $artist->get(["id" => $artistId['artist_id']]);

            // Remove irrelevant information
            unset($artist->key);
            unset($artist->bookings);
            unset($artist->fcmTokens);
            unset($artist->fcmTopics);
            unset($artist->locations);
            unset($artist->receipts);

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
        // Get roles with amount of people required
        $requirements = runSQLQuery(
            "SELECT p.id, p.package_name, p.package_description, pr.role_id, pr.role_amount_required, r.role_name
            FROM nr_packages as p
            LEFT JOIN nr_package_roles as pr ON p.id = pr.package_id
            LEFT JOIN nr_job_roles as r ON r.id = pr.role_id
            WHERE p.id = $id;"
        )["data"];

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

            error_log($sql);

            if(!isset($res["data"])) continue;

            // Merge events near this location to event list
            foreach($res["data"] as $eventId) {
                $eventList[] = $eventId['id'];
            }
        }

        // After loop prevent overlap
        if(!isset($eventList)) return [
            "response" => false,
            "error_code" => 611,
            "error" => "No nearby events."
        ];

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
            $id,
            $clientId,
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
            $id,
            $artistId,
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
                    AND j.client_id = $id;";

                $data = runSQLQuery($sql);

                if(!isset($data["data"])) return $data;

                // Set count before events get removed
                $count = count($data["data"]);

                for($i = 0; $i < $count; $i++) {
                    // Check if attendance already sent
                    $sql =
                    "SELECT * FROM nr_job_client_attendance
                        WHERE event_id = {$data["data"][$i]["id"]}
                        AND client_id = $id;";

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
                // Get recently completed, unpaid jobs for artist
                $sql =
                "SELECT j.id
                    FROM nr_jobs as j
                    LEFT JOIN nr_client_receipts as r ON j.id = r.event_id
                    LEFT JOIN nr_artist_jobs as a ON j.id = a.event_id
                    WHERE r.event_id IS NULL
                    AND TIMESTAMPDIFF(MINUTE, NOW(), j.event_datetime) <= 0
                    AND a.artist_id = $id;";

                $data = runSQLQuery($sql);

                if(!isset($data["data"])) return $data;

                // Set count before events get removed
                $count = count($data["data"]);

                for($i = 0; $i < $count; $i++) {
                    // Check if attendance already sent
                    $sql =
                    "SELECT * FROM nr_job_artist_attendance
                        WHERE event_id = {$data["data"][$i]["id"]}
                        AND artist_id = $id;";

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
            SET event_address = \"$address\"
            WHERE id = $id;";

            $res = runSQLQuery($sql);
            if($res["response"] !== true) {
                return $res;
            }
        }
        if(isset($datetime)) {
            $sql =
            "UPDATE nr_jobs
            SET event_datetime = \"$datetime\"
            WHERE id = $id;";

            $res = runSQLQuery($sql);
            if($res["response"] !== true) {
                return $res;
            }
        }
        if(isset($cardId)) {
            $sql =
            "UPDATE nr_jobs
            SET client_card_id = $cardId
            WHERE id = $id;";

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
                $res["clientId"] = $event->clientId;

                // Send client notification
                if($res["response"] === true) {
                    $notif = [
                        "to" => "/topics/event-{$event->id}-client",
                        "priority" => 'high',
                        "data" => [
                            "title" => "New Artist",
                            "message" => "{$artist->role['name']} {$artist->username} has accepted the booking at {$event->address}",
                            'content-available'  => '1',
                            "image" => 'logo'
                        ]
                    ];
                    $fcm = new NRFCM();
                    $fcm->send($notif, FCM_NOTIFICATION_ENDPOINT);
                }

                try {
                    // Add artist to event chat
                    $chat = (new NRChat())->addToChannel($artist->username, "artist", "event-{$event->id}");
                } catch(Exception $e) {
                    error_log($e);
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
            $id,
            $clientId,
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
            $id,
            $clientId,
            $artistId,
            $rating
        );";

        return runSQLQuery($sql);

    }

    public function delete($args) {
        extract($args);

        $sql =
        "DELETE from nr_jobs
            WHERE id = $eventId
            AND client_id = $id;";

        try {
            $chat = (new NRChat())->deleteChannel("event-$eventId");
        } catch (Exception $e) {
            error_log($e);
        }

        return runSQLQuery($sql);
    }

    public function requirementsFulfilled() {
        // TODO: Check if event has all requirements fulfilled
        foreach($this->requirements as $role => $required) {
            // If the role being check is the artists role and the requirement is greater than whats fulfilled, save event
            if($this->requirements[$role] > $this->fulfillment[$role]) {
                return false;
            }
        }
        // All requirements fulfilled, return true
        return true;
    }

    public function attendanceComplete() {
        // Set attendance requirement to 1 (Client)
        $attendanceRequirement = 1;

        // Add attendance requirement for each role required (Hair Stylist, MUA, etc.)
        foreach($this->requirements as $role => $required) {
            $attendanceRequirement+= $required;
        }

        // Count artist attendance
        $artistAttended = runSQLQuery(
            "SELECT COUNT(id) as attended
            FROM nr_job_artist_attendance
            WHERE event_id = {$this->id};"
        )["data"][0]["attended"];

        // Count client attendance
        $clientAttended = runSQLQuery(
            "SELECT COUNT(id) as attended
            FROM nr_job_client_attendance
            WHERE event_id = {$this->id};"
        )["data"][0]["attended"];

        // Return result of client+artist attendance equalling the attendance requirement (true/false)
        return (($clientAttended + $artistAttended) === $attendanceRequirement);
    }

    public function confirmationReminderSent() {
        // Check for reminder
        $query = runSQLQuery(
            "SELECT id
            FROM nr_job_confirmation_reminders
            WHERE event_id = {$this->id};"
        );

        // Return true if reminder has been sent
        return isset($query["data"][0]);
    }

    public function upcomingReminderSent() {
        // Check for reminder sent already
        $query = runSQLQuery(
            "SELECT id
            FROM nr_job_reminders
            WHERE event_id = {$this->id};"
        );

        // Return true if reminder has been sent
        return isset($query["data"][0]);
    }

    public function setConfirmationReminderSent() {
        // Set reminder as sent
        return runSQLQuery(
            "INSERT INTO nr_job_confirmation_reminders(event_id)
            VALUES({$this->id});"
        )["response"];
    }

    public function setUpcomingReminderSent() {
        // Set reminder as sent
        return runSQLQuery(
            "INSERT INTO nr_job_reminders(event_id)
            VALUES({$this->id});"
        )["response"];
    }

    public function setEventArtistProposalSent() {
        // Set event propsal as sent
        return runSQLQuery(
            "INSERT INTO nr_job_availability_reminders(event_id)
            VALUES({$this->id});"
        )["response"];
    }

    public function formatAddress() {
        // Format address for notifications
        $addressArray = explode(",", $this->address);
        $notifAddress = $addressArray[0] . "," . $addressArray[1];
        isset($addressArray[2]) ? $notifAddress .= "," . $addressArray[2] : null;
        return $notifAddress;
    }

    private function randomString($length = 32) {
        // Create random string with current date salt for uniqueness
        return date('Y-m-d-H-i-s').bin2hex(random_bytes($length));;
    }
}

?>
