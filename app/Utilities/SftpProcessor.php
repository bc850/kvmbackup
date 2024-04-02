<?php

namespace App\Utilities;

use App\Utilities\BackendLogger;
use App\Utilities\FileManagementProcessor;
use App\Models\SftpSite;
use App\Models\VirtualMachine;
use Exception;
use Monolog\Logger;
use phpseclib3\Crypt\PublicKeyLoader;
use phpseclib3\Net\SFTP;

class SftpProcessor {
    public $backend_logger;
    public $backup_days;
    public $slack_logging;
    public $vm;
    
    public function __construct() {}

    public static function sftp_connection(BackendLogger $backend_logger, $host, $port, $username, $password, $private_key) {
        if (isset($port) && $port != "") {
            $sftp = new SFTP($host, $port);
        } else {
            $sftp = new SFTP($host);
        }

        if (isset($private_key) && $private_key != "") {
            $private_key = PublicKeyLoader::load(file_get_contents($private_key));
            if (!$sftp->login($username, $private_key)) {
                $backend_logger->write_log("error", ":alert: SFTP login failed.", true);
                throw new Exception('SFTP login failed');
            }
        } else {
            if (!$sftp->login($username, $password)) {
                $backend_logger->write_log("error", ":alert: SFTP login failed.", true);
                throw new Exception('SFTP login failed');
            }
        }

        return $sftp;
    }

    public static function sftp_upload_files(BackendLogger $backend_logger, SFTP $sftp, $files, $sftp_site_name = null, $slack_logging = false) {
        foreach ($files as $file) {
            $uploading = ":transfer: Uploading `" . basename($file) . "`";
            isset($sftp_site_name) && $sftp_site_name != "" ? $backend_logger->write_log("info", $uploading . " to `{$sftp_site_name}`", $slack_logging) : $backend_logger->write_log("info", $uploading, $slack_logging);
            
            $sftp->put(basename($file), $file, SFTP::SOURCE_LOCAL_FILE);

            $uploaded = ":white_check_mark: `" . basename($file) . "` uploaded";
            isset($sftp_site_name) && $sftp_site_name != "" ? $backend_logger->write_log("info", $uploaded . " to `{$sftp_site_name}`", $slack_logging) : $backend_logger->write_log("info", $uploaded, $slack_logging);
        }
    }

    public function sftp_process() {
        $this->backend_logger->write_log("info", "Beginning SFTP processing for `{$this->vm->name}`", true);

        $today = date('l', strtotime("now"));
        $files = glob($this->vm->sftp_location . "*.*");
	    $this->backup_days = json_decode($this->vm->backup_days, true);
        $this->backend_logger->write_log("info", "Checking if today is a backup day.");
	    if (array_key_exists($today, $this->backup_days) && $this->backup_days[$today]) {
            $this->backend_logger->write_log("info", "{$today} is a backup day. Processing.");

            foreach ($this->vm->sftp_sites as $ss) {
                $this->backend_logger->write_log("info", ":cloud_upload: Sending files to `{$ss->name}`.", true);
                $sftp = self::sftp_connection($this->backend_logger, $ss->host, $ss->port, $ss->username, $ss->password, $ss->private_key);

                if ($sftp->isConnected()) {
                    $this->backend_logger->write_log("info", "SFTP connected to {$ss->name}.");
                    $sftp->chdir($ss->remote_sub_directory);
                    self::sftp_upload_files($this->backend_logger, $sftp, $files, $ss->name, true);
                    $sftp->disconnect();
                    FileManagementProcessor::archive_files($this->backend_logger, $files, $this->vm->backup_location, true, true);
                } else {
                    $this->backend_logger->write_log("error", ":alert: SFTP is not connected.", true);
                    throw new Exception("SFTP is not connected");
                }
            }
        }
    }
}
