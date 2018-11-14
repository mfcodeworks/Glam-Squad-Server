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

define("MEDIA_PATH", "/srv/nr-glam-squad/media/");

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
        // Get arguments
        extract($args);

        // Format datetime
        $datetime = date("Y-m-d H:i:s",strtotime($datetime));

        // Save images
        foreach($photos as $photo) {
            try {
                $this->saveImageBlob($photo);
            }
            catch(Exception $e) {
                return($e);
            }
        }

        // Build sql
        $sql = "
        INSERT INTO nr_jobs(
            event_address, 
            event_datetime, 
            event_package_id, 
            event_note, 
            event_clients,
            client_id,
            client_card_id)
        VALUES(
            \"$address\",
            \"$datetime\",
            $package,
            \"$note\",
            $clients,
            $userId,
            $cardId
        );
        ";

        return runSQLQuery($sql);
    }

    private function saveImageBlob($blob) {
        // Skip empty blob
        if($blob == "") return;

        // Get type from image/png or image/jpeg
        $type = explode("/",explode(";",$blob)[0])[1];

        // Validate file type
        if(!in_array($type, ['jpg', 'jpeg', 'png', 'gif'])) {
            throw new Exception("Invalid image type: $type. Not an image.");
        }

        // Get data and decode
        $data = base64_decode(explode(",",explode(";",$blob)[1])[1]);

        // Create random filename
        $filename = $this->randomString();

        // Put file contents
        if(file_put_contents(MEDIA_PATH.$filename.".".$type, $data) !== false) {
            return true;
        }
        return false;
    }

    private function randomString($length = 64) {
        // Create random string with current date salt for uniqueness
        return date('Y-m-d-H-i-s').bin2hex(random_bytes($length));;
    }
}

?>