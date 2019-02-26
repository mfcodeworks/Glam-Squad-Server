<?php
/**
 * Class: NRResmushIt
 * Author: MF Softworks <mf@nygmarosebeauty.com>
 * Date Created: 20/11/2018
 * Description:
 * ResmushIt class to communicate with Resmush.it image optimization API
 */

class NRResmushIt {
    public function __construct($filepath) {
        // get file uri
        $fileURI = str_replace(MEDIA_PATH, MEDIA_URI, $filepath);

        // get json of resmushit optimization response
        $optimized = json_decode(file_get_contents(RESMUSHIT . $fileURI));

        // log error
        if(isset($optimized->error)) {
            error_log("Error:\nFilepath: $filepath.\nFile URI: $fileURI.\n" . $optimized->error);
            return false;
        }

        // get/put optimized file
        $newFile = file_get_contents($optimized->dest);

        if(!$newFile) {
            error_log("Error:\nFilepath: $filepath.\nFile URI: $fileURI.\nError saving file after optimizarion.");
            return false;
        }

        file_put_contents($filepath, $newFile);
        error_log("Optimized file $filepath with Resmush.it API.");
    }
}

?>