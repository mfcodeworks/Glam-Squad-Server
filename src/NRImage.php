<?php
/**
 * Class: NRImage
 * Author: MF Softworks <mf@nygmarosebeauty.com>
 * Date Created: 11/12/2018
 * Description:
 * Image class to handle image reading and saving
 */
    
require_once "database-interface.php";

class NRImage {
    public $filepath;
    public $publicpath;
    public $blob;
    public $type;
    public $mime;
    public $image_data;

    public function __construct() {
        // If media directory doesn't exist, create it
        if (!is_dir(MEDIA_PATH)) mkdir(MEDIA_PATH, 0775, true);
    }

    public function getData($blob) {
        // Skip empty blobs
        if($blob === "" || $blob === null) return false;

        // Get mime
        $this->mime = explode(";", $blob)[0];

        // Get type from image/png or image/jpeg
        $this->type = explode("/", $this->mime)[1];

        // Validate file type
        if(!in_array($this->type, ['jpg', 'jpeg', 'png', 'gif'])) throw new Exception("Invalid image type: {$this->type}.");

        // Get data and decode
        $this->blob = explode(",", explode(";",$blob)[1])[1];
        $this->data = base64_decode($this->blob);

        // Create random filename
        $this->filepath = MEDIA_PATH . "{$this->randomString()}.{$this->type}";

        // Put file contents
        if(file_put_contents($this->filepath, $this->data) !== false) {
            // Create public path
            $this->publicpath = str_replace(MEDIA_PATH, MEDIA_URI, $this->filepath);
            return true;
        }

        // Throw file put error
        throw new Exception("Couldn't save blob to file: {$this->filepath}.");
    }

    public function getURI($uri) {
        // Seperate URI at dot (last dot is always filetype if file)
        $uriArray = explode(".", $uri);
        $final = count($uriArray);
        $this->type = $uriArray[$final];

        // Create random filename
        $this->filepath = MEDIA_PATH . "{$this->randomString()}.{$this->type}";

        // Get data
        $this->data = file_get_contents(urlencode($uri));

        if(!$this->data) {
            // Throw file read error
            throw new Exception("Couldn't read URI: {$uri}.");
        }
        
        // Put file contents
        if(file_put_contents($this->filepath, $this->data) !== false) {
            // Create public path
            $this->publicpath = str_replace(MEDIA_PATH, MEDIA_URI, $this->filepath);
            return true;
        }

        throw new Exception("Couldn't save data to file: {$this->filepath}.");
    }

    public static function optimizeImage($filepaths) {
        // Do image optimization
        $ch = curl_init("https://glam-squad-db.nygmarosebeauty.com/public/smush.php");
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $filepaths);
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

    private function randomString($length = 32) {
        // Create random string with current date salt for uniqueness
        return date('Y-m-d-H-i-s').bin2hex(random_bytes($length));;
    }
}

?>