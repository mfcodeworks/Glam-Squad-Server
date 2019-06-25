<?php

class Timer {
    private $start;
    private $end;

    public function begin() {
        // Set start time
        $this->start = microtime(true);
    }

    public function end() {
        // Set end time
        $this->end = microtime(true) - $this->start;
    }

    public function __toString() {
        // If no end call yet, save end as time difference now
        if(!$this->end) $this->end();

        // Print time difference in seconds
        return "{$this->end} s";
    }
}

?>
