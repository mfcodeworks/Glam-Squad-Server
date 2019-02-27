<?php
/**
 * Class: Queue
 * Author: MF Softworks <mf@nygmarosebeauty.com>
 * Date Created: 27/02/2019
 * Description:
 * Queue class to offload low-priority tasks in PHP
 */

require_once "database-interface.php";

use Enqueue\AmqpLib\AmqpConnectionFactory;

class Queue {
    // properties
    public $context;
    
    public function __construct() {
        // Return new queue object
        $this->context = (new AmqpConnectionFactory(ENQUEUE_OPTIONS))->createContext();
    }

}
?>