<?php

namespace App\Console\Commands;

use App\Models\Disk;
use App\Utilities\BackendLogger;
use App\Utilities\FileManagementProcessor;
use Illuminate\Console\Command;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

class ManageDiskSpace extends Command {
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'kvmbackup:mds';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command to manage disk space by removing old backup files.';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct() {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle() {
        $machine_name = php_uname("n");
        $fmp = new FileManagementProcessor();
        $fmp->backup_file_extensions = [".qcow2", ".sha1", ".gz", ".gpg"];
        $fmp->delete_by_day = true;
        $backend_logger = new BackendLogger();
        $disks = Disk::all();
        $backend_logger->logger = new Logger($machine_name . "->mds");
        $backend_logger->slack_logging = true;
        
        foreach ($disks as $disk) {
            $backend_logger->logger->pushHandler(new StreamHandler($disk->log_dir . date("Y-m") . "-backup_log.log", Logger::DEBUG));
            // $backend_logger->write_log("info", "Managing disk space for `{$disk->mount_point}` on `{$machine_name}`", true);
            $fmp->backend_logger = $backend_logger;
            $fmp->manage_disk_space($disk);
        }

        // $backend_logger->write_log("info", "Disk space managed for `{$disk->mount_point}` on `{$machine_name}`", true);
    }
}
