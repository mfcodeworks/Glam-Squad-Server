<?php
/**
 * Class: NRChat
 * Author: MF Softworks <mf@nygmarosebeauty.com>
 * Date Created: 11/02/2019
 * Date Edited: 11/02/2019
 * Description:
 * Chat class for realtime chat communication using Twilio API
 */

use Twilio\Rest\Client;

require_once "database-interface.php";

class NRChat {
    private $client;

    public function __construct() {
        $this->client = new Client(TWILIO_API_KEY, TWILIO_API_SECRET, TWILIO_SID);
    }
}

?>