<?php
/**
 * Class: NRFCM
 * Author: MF Softworks <mf@nygmarosebeauty.com>
 * Date Created: 13/11/2018
 * Date Edited: 13/11/2018
 * Description:
 * FCM class to communicate with FCM server
 */

require_once "database-interface.php";

define("fcmKey", "AIzaSyAwxC-XPBcbfQxVzmHzwPNQCWCuM-TiAoc");
define("fcmEndpoint", "https://fcm.googleapis.com/fcm/send");

class NRFCM {
    // properties
    private $fcmId;

    public function registerFcmId($fcmId, $userId) {
        // Build SQL
        $sql = "
        INSERT INTO nr_client_fcm_tokens(fcm_token, client_id)
        VALUES(\"$fcmId\", $userId);
        ";

        return runSQLQuery($sql);
    }

    public function getFcmId($type, $options = []) {
        // Switch request type
        switch($type) {
            case "client":
            default:
                // Build SQL
                $sql = "
                SELECT *
                FROM nr_client_fcm_tokens;
                ";

                return runSQLQuery($sql);
                break;
        }
    }
}

?>