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
        // Get arguments
        extract($args);

        // Format datetime
        $datetime = date("Y-m-d H:i:s",strtotime($datetime));

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
            1,
            \"$note\",
            1,
            $userId,
            $cardId
        );
        ";

        $res = runSQLQuery($sql);

        $eventId = $res['id'];

        // Save images
        foreach($photos as $photo) {
            try {
                $filepath = $this->saveImageBlob($photo);
                $this->saveImageReference($filepath, $eventId);
            }
            catch(Exception $e) {
                $res['error'] .= "\n".$e;
            }
        }

        return $res;
    }

    private function saveImageReference($filepath, $eventId) {
        new NRResmushIt($filepath);

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