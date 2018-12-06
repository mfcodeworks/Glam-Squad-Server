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
require_once "class-resmushit.php";

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
    public $requirements = [];
    public $fulfillment = [];
    public $artists = [];
    
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

        try {
            $this->clientCardId = $this->getCardId($userId, $card);
        }
        catch(Exception $e) {
            return [
                "response" => false,
                "error" => $e
            ];
        }

        try{
            $this->id = $this->saveEventMeta();
        }
        catch(Exception $e) {
            return [
                "response" => false,
                "error" => $e
            ];
        }

        foreach($packages as $package) {
            try { 
                $this->savePackageReference($package);
            }
            catch(Exception $e) {
                return [
                    "response" => false,
                    "error" => $e
                ];
            }
        }

        foreach($this->requirements as $role => $amount) {
            $this->fulfillment[$role] = 0;
        }

        // Save images
        $filepathArray = [];
        if (isset($photos)) {
            foreach ($photos as $photo) {
                try {
                    $filepath = $this->saveImageBlob($photo);
                    $filepathArray[] = $filepath;
                    $this->saveImageReference($filepath, $eventId);
                }
                catch(Exception $e) {
                    $res['error'] .= "\n".$e;
                }
            }
        }

        // Do image optimization
        if (count($filepathArray) > 0) {
            $ch = curl_init("https://glam-squad-db.nygmarosebeauty.com/smush.php");
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $filepathArray);
            curl_setopt($ch, CURLOPT_TIMEOUT, 1); 
            curl_setopt($ch, CURLOPT_HEADER, 0);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, false);
            curl_setopt($ch, CURLOPT_FORBID_REUSE, true);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 1);
            curl_setopt($ch, CURLOPT_DNS_CACHE_TIMEOUT, 10); 
            curl_setopt($ch, CURLOPT_FRESH_CONNECT, true);
            curl_exec($ch);
            curl_close($ch);
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
            INNER JOIN nr_job_roles as r ON r.id = pr.role_id
            WHERE pr.package_id = $package";
        
        $res = runSQLQuery($sql);

        if($res['response'] !== true) throw new Exception($res['error']);

        foreach($res['data'] as $requirement) {
            (isset( $this->requirements[ $requirement['role_name'] ] )) ? $this->requirements[ $requirement['role_name'] ] += $requirement['role_amount_required'] : $this->requirements[ $requirement['role_name'] ] = $requirement['role_amount_required'];
        }
    }

    private function saveImageReference($filepath, $eventId) {
        // Build SQL
        $sql = "
        INSERT INTO nr_job_references(
            event_reference_photo,
            event_id
        )
        VALUES(
            \"$filepath\",
            $eventId
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

    private function saveImageBlob($blob) {
        // Skip empty blobs
        if($blob == "") return;

        // Get type from image/png or image/jpeg
        $type = explode("/",explode(";",$blob)[0])[1];

        // Validate file type
        if(!in_array($type, ['jpg', 'jpeg', 'png', 'gif']))
            throw new Exception("Invalid image type: $type. Not an image.");

        // Get data and decode
        $data = base64_decode(explode(",",explode(";",$blob)[1])[1]);

        // Create random filename
        $filepath = MEDIA_PATH . $this->randomString() . "." . $type;

        // Put file contents
        if(file_put_contents($filepath, $data) != false)
            return $filepath;

        throw new Exception("Couldn't save blob to file: $filepath.");
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
            return [
                "response" => true,
                "error" => null,
                "data" => $event->getSingle($jobId)
            ];
        }

        /* CHNAGED: Updated to use OOP
        for($i = 0; $i < count($events["data"]); $i++) {
            $jobId = $events["data"][$i]["id"];

            $sql = 
            "SELECT p.id, p.package_name as name, p.package_description as description, p.package_price as price 
            FROM nr_packages as p 
            INNER JOIN nr_job_packages as j ON p.id = j.event_package_id 
            WHERE j.event_id = $jobId;";

            $packages = runSQLQuery($sql);

            $events["data"][$i]["packages"] = $packages["data"];

            $events["data"][$i]["datetime"] = (new Datetime($events["data"][$i]["datetime"]))->format(Datetime::ATOM);
        }
        */
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
            INNER JOIN nr_job_packages as p ON j.id = p.event_id
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
        
        // Get requirement properties
        for($i = 0; $i < count($this->packages); $i++) {
            try {
                $this->packages[$i] = $this->getPackageRequirements($this->packages[$i]);
            }
            catch(Exception $e) {
                return [
                    "response" => false,
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
                "error" => $e
            ];
        }
    }

    private function getEventArtists() {
        $sql =
        "SELECT artist_id
            FROM nr_artist_jobs
            WHERE event_id = {$this->id};";

        $res = runSQLQuery($sql);
        if($res['response'] !== true) throw new Exception($res['error']);

        foreach($res['data'] as $artistId) {
            $artist = new NRArtist;
            $artist->get(["userId" => $artistID]);
            $this->fulfillment[$artist->role]++;
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
            INNER JOIN nr_package_roles as pr ON p.id = pr.package_id
            INNER JOIN nr_job_roles as r ON r.id = pr.role_id
            WHERE p.id = $id;";

        $res = runSQLQuery($sql);

        if($res["response"] === true) {
            $requirements = $res["data"];
            $package = $requirements[0];

            foreach($requirements as $requirement) {
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
        $artist->get(["userId" => $userId]);

        $sql =
        "SELECT id, loc_lat as lat, loc_lng as lng
            FROM nr_artist_locations
            WHERE artist_id = {$artist->id};";
        
        $res = runSQLQuery($sql);

        if($res["response"] !== true) {
            return[
                "response" => false,
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

            if($res["response"] !== true) {
                return[
                    "response" => false,
                    "error" => "No nearby events."
                ];
            }

            // Merge events near this location to event list
            foreach($res["data"] as $eventId) {
                $eventList[] = $eventId['id'];
            }
        }

        // After loop prevent overlap
        array_unique($eventList);

        // Loop through IDs
        foreach($eventList as $id) {
            $event = new NREvent();
            $event->getSingle($id);

            // Save event if artist is needed
            foreach($event->requirements as $role => $required) {
                // If the role being check is the artists role and the requirement is greater than whats fulfilled, save event
                if($role === $artist->role && $event->requirements[$role] > $event->fulfillment[$role]) $events[] = $event;
            }
        }

        return[
            "response" => true,
            "error" => null,
            "data" => $events
        ];
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