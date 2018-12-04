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

class NRPackage {
    // property
    public $name;
    public $description;
    public $cost;
    
    public function __construct() {
        
    }

    public function get($id = null) {
        // Build SQL
        if($id)
            $sql = "SELECT * FROM nr_packages 
                WHERE id = $id;";
        else
            $sql = "SELECT * FROM nr_packages;";

        return runSQLQuery($sql);
    }

    public function save($args) {
        // Get values
        extract($args);

        // Build SQL
        $sql = "INSERT INTO nr_packages(
            package_name, 
            package_description, 
            package_cost
        );
        VALUES(
            \"$name\",
            \"$description\",
            $cost
        );
        ";

        return runSQLQuery($sql);
    }

    public function delete($id = null) {
        if(!$id)
            return;

        // Build SQL
        $sql = "DELETE FROM nr_packages
            WHERE id = $id;";

        return runSQLQuery($sql);
    }
}

?>