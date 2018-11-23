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
    public $datetime;
    public $packageId;
    public $note;
    public $clientNo;
    public $clientId;
    public $clientCardId;
    public $references = [];
    public $artists = [];

    public function save($args) {
        error_log(print_r($args["photos"], true));

        // Get arguments
        extract($args);

        // Format datetime
        $datetime = date("Y-m-d H:i:s", strtotime($datetime));

        // Build sql
        $sql = "
        INSERT INTO nr_jobs(
            event_address, 
            event_datetime,
            event_note,
            client_id,
            client_card_id)
        VALUES(
            \"$address\",
            \"$datetime\",
            \"$note\",
            $userId,
            $cardId
        );
        ";

        $res = runSQLQuery($sql);

        $eventId = $res['id'];

        foreach($packages as $package) {
            try { 
                $this->savePackageReference($package, $eventId);
            }
            catch(Exception $e) {
                $res['error'] .= "\n".$e;
            }
        }

        $filepathArray = [];

        // Save images
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

        if (count($filepathArray) > 0) {
            // Begin image optimization
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

        return $res;
    }

    private function savePackageReference($package, $event) {
        // Build SQL
        $sql = "
        INSERT INTO nr_job_packages(
            event_package_id,
            event_id
        )
        VALUES(
            $package,
            $event
        );";

        $res = runSQLQuery($sql);

        if($res['response'] == true) {
            return;
        }
        else {
            throw new Exception($res['error']);
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

        if($res['response'] == true) {
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

    private function randomString($length = 32) {
        // Create random string with current date salt for uniqueness
        return date('Y-m-d-H-i-s').bin2hex(random_bytes($length));;
    }
}

?>