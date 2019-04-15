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
    public $blob;
    public $type;
    public $mime;
    public $image_data;
    public $subdir;

    public function __construct() {
        // If media directory doesn't exist, create it
        if (!is_dir(MEDIA_PATH)) mkdir(MEDIA_PATH, 0775, true);
    }

    public function getData($blob) {
        // Skip empty blobs
        if($blob === "" || $blob === null) return false;

        // Get mime section
        $mime = explode(";", $blob)[0];

        // Get type from image/png or image/jpeg
        $this->type = explode("/", $mime)[1];

        // Get proper mime
        $this->getMime();

        // Validate file type
        if(!in_array($this->type, ['jpg', 'jpeg', 'png', 'gif'])) throw new Exception("Invalid image type: {$this->type}.");

        // Get data and decode
        $this->blob = explode(",", explode(";",$blob)[1])[1];
        $this->data = base64_decode($this->blob);

        // Create random filename
        $this->filepath = MEDIA_PATH . "{$this->randomString()}.{$this->type}";

        // Put file contents
        if(file_put_contents($this->filepath, $this->data) !== false) return true;

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
            throw new Exception("Couldn't read URI: $uri.");
        }

        // Put file contents
        if(file_put_contents($this->filepath, $this->data) !== false) {
            // Create public path
            $this->getMime();
            return true;
        }

        throw new Exception("Couldn't save data to file: {$this->filepath}.");
    }

    public function uploadToSpaces() {
        $space = new NRSpaces();

        if(!$this->mime) $this->getMime();

        try {
            error_log("Uploading to spaces:\nFilepath: {$this->filepath}\nPrivacy: public\nSubdir: {$this->subdir}\nMime: {$this->mime}");
            return $space->queueUpload($this->filepath, "public", $this->subdir, $this->mime);
        } catch(Exception $e) {
            error_log($e);
            throw $e;
        }
    }

    public function upload() {
        $space = new NRSpaces();

        if(!$this->mime) $this->getMime();

        try {
            error_log("Uploading to spaces:\nFilepath: {$this->filepath}\nPrivacy: public\nSubdir: {$this->subdir}\nMime: {$this->mime}");
            return $space->upload($this->filepath, "public", $this->subdir, $this->mime);
        } catch(Exception $e) {
            error_log($e);
            throw $e;
        }
    }

    private function randomString($length = 32) {
        // Create random string with current date salt for uniqueness
        return date('Y-m-d-H-i-s').bin2hex(random_bytes($length));;
    }

    private function getMime() {
        $types = [
            'png' => 'image/png',
            'jpe' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'jpg' => 'image/jpeg',
            'gif' => 'image/gif',
            'bmp' => 'image/bmp',
            'ico' => 'image/vnd.microsoft.icon',
            'tiff' => 'image/tiff',
            'tif' => 'image/tiff',
            'svg' => 'image/svg+xml',
            'svgz' => 'image/svg+xml'
        ];

        $this->mime = $types[$this->type];
    }
}

?>