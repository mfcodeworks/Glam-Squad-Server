<?php
/**
 * Class: NRSpaces
 * Author: MF Softworks <mf@nygmarosebeauty.com>
 * Date Created: 26/02/2019
 * Description:
 * Spaces class to communicate with DigitalOcean Spaces Object Storage
 */

require_once "database-interface.php";

class NRSpaces {
    // properties
    public $space;

    public function __construct() {
        // Construct Spaces Connection
        $this->space = new SpacesConnect(SPACES_KEY, SPACES_SECRET, SPACES_NAME, SPACES_REGION);
    }

    public function upload($path, $privacy = "public", $subdir = "GlamSquad/", $mime = "application/octet-stream") {
        // If the file path is a full path save as the Spaces subdir with the filename given
        if(strpos($path, "/") !== false) {
            // Seperate dirs
            $filePath = explode("/", $path);
            // Get filename index
            $index = count($filePath);
            // Set filename
            $filename = $filePath[($index-1)];
            // Append filename to subdir
            $subdir .= $filename;
        } else {
            // If file is not a filepath append the filename directly to subdir
            $subdir .= $path;
        }

        // Upload file with filepath, privacy of file, spaces subdir
        try {
            $this->space->UploadFile($path, $privacy, $subdir, $mime);
            return $subdir;
        } catch (Exception $e) {
            error_log(json_encode($e, JSON_PRETTY_PRINT));
            throw $e;
        }
    }

    public function delete($filepath) {
        return $this->spaces->DeleteObject($filepath);
    }
}
?>