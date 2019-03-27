<?php
/**
 * File: EmailQueue
 * Author: MF Softworks <mf@nygmarosebeauty.com>
 * Date Created: 27/02/2019
 * Description:
 * Queue initiator to listen and consume queues asynchronously
 */

// Paths
define('PROJECT_ROOT', dirname(dirname(__FILE__)));
define('PROJECT_CONFIG', PROJECT_ROOT . '/config/');
define('PROJECT_LIB', PROJECT_ROOT . '/vendor/');
define('PROJECT_INC', PROJECT_ROOT . '/src/');

// Require classes
require_once PROJECT_CONFIG . "config.php";
require_once PROJECT_LIB . "autoload.php";
require_once PROJECT_INC . "DegreeDistanceFinder.php";
require_once PROJECT_INC . "Mailer.php";
require_once PROJECT_INC . "NRChat.php";
require_once PROJECT_INC . "NRArtist.php";
require_once PROJECT_INC . "NRClient.php";
require_once PROJECT_INC . "NREvent.php";
require_once PROJECT_INC . "NRFCM.php";
require_once PROJECT_INC . "NRImage.php";
require_once PROJECT_INC . "NRSpaces.php";
require_once PROJECT_INC . "NRPackage.php";
require_once PROJECT_INC . "database-interface.php";

use Enqueue\AmqpLib\AmqpConnectionFactory;
use Enqueue\AmqpLib\AmqpContext;

/**
 * Inititate queue
 */
twilioQueue();

function twilioQueue(){
    // Create consumer
    $context = (new AmqpConnectionFactory(ENQUEUE_OPTIONS))->createContext();
    $queue = $context->createQueue('twilio_register');
    $context->declareQueue($queue);
    $consumer = $context->createConsumer($queue);

    while(true) {
        // Get message
        $message = $consumer->receive($timeout = 10);

        if($message) {
            // DEBUG: Measure exec time
            $time_start = microtime(true);

            // Extract args
            $args = json_decode($message->getBody(), true);
            extract($args);

            // Register user with Twilio
            try {
                $twilioUser = (new NRChat)->register($id, $username, $type);
                // Check Twilio SID and save
                if($twilioUser->sid) {
                    $sql = "UPDATE nr_{$type}s
                        SET twilio_sid = \"{$twilioUser->sid}\"
                        WHERE id = {$id}";
                    runSQLQuery($sql);
                }
            } catch (Exception $e) {
                error_log($e);
            }

            // Acknowledge
            $consumer->acknowledge($message);

            // DEBUG: Measure exec time
            $time_end = microtime(true);
            $execution_time = ($time_end - $time_start);
            error_log("Twilio Register Execution Time: $execution_time s");
        }
    }
}

?>