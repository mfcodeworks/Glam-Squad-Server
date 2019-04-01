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
    public $id;
    public $name;
    public $description;
    public $price;
    public $roleRequirements = [];

    public function __construct() {

    }

    public function get($args) {
        extract($args);

        // Build SQL
        if(isset($id))
            $sql = "SELECT id, package_name as name, package_description as description, ROUND(package_price, 2) as price
            FROM nr_packages
            WHERE id = $id;";
        else
            $sql = "SELECT id, package_name as name, package_description as description, ROUND(package_price, 2) as price
            FROM nr_packages
            ORDER BY id ASC;";

        return runSQLQuery($sql);
    }

    public function save($args) {
        // Get values
        extract($args);

        // Build SQL
        $sql = "INSERT INTO nr_packages(
            package_name,
            package_description,
            package_price
        )
        VALUES(
            \"$name\",
            \"$description\",
            $price
        );
        ";

        return runSQLQuery($sql);
    }

    public function delete($args) {
        extract($args);

        if(!$id) return [
            "error_code" => 606,
            "error" => "Package ID missing."
        ];

        // Build SQL
        $sql = "DELETE FROM nr_packages
            WHERE id = $id;";

        return runSQLQuery($sql);
    }
}

?>