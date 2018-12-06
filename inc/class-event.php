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
            "SELECT j.id, j.event_address as address, j.event_datetime as datetime, j.event_note as note, j.event_price as price, j.client_id as userId, j.client_card_id as cardId, GROUP_CONCAT(p.event_package_id) as packages             
            FROM nr_jobs as j 
            INNER JOIN nr_job_packages as p ON p.event_id = j.id 
            WHERE j.client_id = $userId
            GROUP BY j.id
            ORDER BY datetime DESC;";
        }
        if(isset($jobId)) {
            $sql =
            "SELECT j.id, j.event_address as address, j.event_datetime as datetime, j.event_note as note, j.event_price as price, j.client_id as userId, j.client_card_id as cardId, GROUP_CONCAT(p.event_package_id) as packages             
            FROM nr_jobs as j 
            INNER JOIN nr_job_packages as p ON p.event_id = j.id 
            WHERE j.id = $jobId
            GROUP BY j.id
            ORDER BY datetime DESC;";
        }

        $events = runSQLQuery($sql);

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

        return $events;
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
            GROUP_CONCAT(p.event_package_id)
            FROM nr_jobs as j
            INNER JOIN nr_job_packages as p ON j.id = p.event_id
            WHERE j.id = $id
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
        $this->packages = explode(",", $event_package_id);
        
        // Get requirement properties
        for($i = 0; $i < count($packages); $i++) {
            try {
                $this->getPackageRequirements($this->$packages[$i]);
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
            $this->requirements[$artist->role]--;
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
        "SELECT p.id, p.package_name, pr.role_id, pr.role_amount_required, r.role_name
            FROM nr_packages as p
            INNER JOIN nr_package_roles as pr ON p.id = pr.package_id
            INNER JOIN nr_job_roles as r ON r.id = pr.role_id
            WHERE p.id = $id;";

        $res = runSQLQuery($sql);

        if($res["response"] === true) {
            $requirements = $res["data"];

            foreach($requirements as $requirement) {
                (isset( $this->requirements[ $requirement['role_name'] ] )) ? $this->requirements[ $requirement['role_name'] ] += $requirement['role_amount_required'] : $this->requirements[ $requirement['role_name'] ] = $requirement['role_amount_required'];
            }
        }
        else {
            throw new Exception($res['error']);
        }
    }

    public function getNew() {
        /**
         * Select future events:
         * SELECT j.id as event_id
         * FROM nr_jobs as j
         * WHERE j.event_datetime >= CURDATE()
         * 
         * Select future events and their packages:
         * SELECT j.id as event_id, GROUP_CONCAT(jp.event_package_id) as event_packages
         * FROM nr_jobs as j 
         * INNER JOIN nr_job_packages as jp ON j.id = jp.event_id 
         * WHERE j.event_datetime >= CURDATE() 
         * GROUP BY j.id;
         */

        // Get events and packages that are in the future
        $sql = 
        "SELECT j.id as event_id, GROUP_CONCAT(jp.event_package_id) as event_packages
        FROM nr_jobs as j 
        INNER JOIN nr_job_packages as jp ON j.id = jp.event_id 
        WHERE j.event_datetime >= CURDATE() 
        GROUP BY j.id;
        ;";

        $data = runSQLQuery($sql);

        // Save events to a variable
        $events = $data["data"];
        
        // Loop through every event
        for($i = 0; $i < count($events); $i++) {
            // Make packages into array
            $events[$i]["event_packages"] = explode(",", $events[$i]["event_packages"]);

            // Save single event
            $event = $events[$i];

            // Loop through event packages
            foreach($event["event_packages"] as $package) {
                // Get the package requirements
                $sql = 
                "SELECT role_id as role, role_amount_required as required
                FROM nr_package_roles
                WHERE package_id = $package;";

                $obj = runSQLQuery($sql);

                $requirement = $obj["data"][0];

                // Count the artists in this role assigned to event
                $sql =
                "SELECT COUNT(a.role_id) as fulfilled
                FROM nr_artists as a
                INNER JOIN nr_artist_jobs as ja ON ja.artist_id = a.id
                INNER JOIN nr_jobs as j ON j.id = ja.event_id
                WHERE a.role_id = {$requirement['role_id']}
                AND ja.event_id = {$event['id']};
                ";

                $obj = runSQLQuery($sql);

                $fulfilled = $obj["data"][0];

                // If the artists are less than what's required, add a requirement for this role
                if($fulfilled["fulfilled"] < $requirement["required"]) {
                    // TODO: Handle role requirement
                    $event["requirement"][] = $requirement["role"];
                }
            }

            if(isset($event["requirement"])) {
                $events[$i] = $event;
            }
            else {
                unset($events[$i]);
            }
        }
        return $events;
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