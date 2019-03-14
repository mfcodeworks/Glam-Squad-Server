<?php
/**
 * Class: NRAuth
 * Author: MF Softworks <mf@nygmarosebeauty.com>
 * Date Created: 13/03/2019
 * Description:
 * Auth class to handle authorization & authentication features
 */

class NRAuth {
    // Authorize user from user key
    public static function authorizeUser($key, $id, $type = "client") {
        switch($type) {
            case "client":
                $client = (new NRClient)->get(["id" => $id]);
                return self::verifyUserKey($key, $client["data"][0]["username"], $client["data"][0]["password"]);
                break;

            case "artist":
                $artist = (new NRArtist)->get(["id" => $id]);
                return self::verifyUserKey($key, $artist->username, $artist->getPassword());
                break;
        }
    }

    // Create user auth key from username hash with password hash salt
    public static function userAuthKey($username, $passwordHash) {
        return self::hashInput($passwordHash.$username);
    }

    // Verify user auth key
    public static function verifyUserKey($key, $username, $passwordHash) {
        return self::verifyInput($passwordHash.$username, $key);
    }
    
    // Hash input with Argon2 (PHP7.2+)
    public static function hashInput($input) {
        return password_hash($input, PASSWORD_ARGON2I, ARGON_CONFIG);
    }

    // Verify input hash
    public static function verifyInput($input, $hash) {
        return password_verify($input, $hash);
    }
}
?>