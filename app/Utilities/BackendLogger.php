<?php

namespace App\Utilities;

use Illuminate\Support\Facades\Log;

class BackendLogger {
    public $logger;
    public $prefix;
    public $slack_logging;

    public function __construct() {}

    public function write_log($log_type, $message, $send_to_slack = false) {
        $this->logger->$log_type($message);
        if ($this->slack_logging && $send_to_slack) {
            if (isset($this->prefix) && $this->prefix != "") {
                Log::channel("slack")->$log_type($this->prefix . ": " . $message);
            } else {
                Log::channel("slack")->$log_type($message);
            }
        }
    }
}