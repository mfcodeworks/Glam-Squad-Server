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
            $package,
            \"$note\",
            $clients,
            $userId,
            $cardId
        );
        ";

        return [
            $sql,
            runSQLQuery($sql)
        ];
    }
}

?>