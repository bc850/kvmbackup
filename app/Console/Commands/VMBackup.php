<?php

namespace App\Console\Commands;

use App\Utilities\BackendLogger;
use App\Utilities\BackupProcessor;
use App\Models\VirtualMachine;
use Illuminate\Console\Command;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

class VMBackup extends Command {
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'kvmbackup:vmbackup';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'This command starts the virtual machine backup script.';

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
        $backend_logger = new BackendLogger();
        $backup_processor = new BackupProcessor();
        $vms = VirtualMachine::where("perform_backup", true)->get();
        $backend_logger->logger = new Logger(php_uname("n") . "->vmbackup");
        $backend_logger->slack_logging = true;
        $backend_logger->write_log("info", "Starting virtual machine backups.", true);
        
        foreach ($vms as $vm) {
            $backup_processor->vm = $vm;
            $backup_processor->checksumfile = $vm->sftp_location . date("Y-m-d") . "-" . $vm->name . ".sha1";
            $backup_processor->file = $vm->vm_location . $vm->name . ".qcow2";
            $backup_processor->newfile = $vm->sftp_location . date("Y-m-d") . "-" . $vm->name . ".qcow2";
            $backend_logger->logger->pushHandler(new StreamHandler($vm->log_dir . date("Y-m") . "-backup_log.log", Logger::DEBUG));
            $backup_processor->backend_logger = $backend_logger;
            $backup_processor->vm_process();
        }

        $backend_logger->write_log("info", "End backup script.", true);
    }
}
