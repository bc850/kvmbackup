<?php

namespace App\Console\Commands;

use App\Utilities\BackendLogger;
use App\Utilities\SftpProcessor;
use App\Models\VirtualMachine;
use Illuminate\Console\Command;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

class VMSftp extends Command {
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'kvmbackup:vmsftp';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'This command starts the virtual machine SFTP transfer script.';

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
        $sftp_processor = new SftpProcessor();
        $backend_logger = new BackendLogger();
        $vms = VirtualMachine::all();
        $backend_logger->logger = new Logger(php_uname("n") . "->vmsftp");
        $backend_logger->slack_logging = true;
        $backend_logger->write_log("info", "Starting SFTP backups.", false);
        
        foreach ($vms as $vm) {
            $sftp_processor->vm = $vm;
            //$backend_logger->prefix = $vm->name;
            $backend_logger->logger->pushHandler(new StreamHandler($vm->log_dir . date("Y-m") . "-backup_log.log", Logger::DEBUG));
            $sftp_processor->backend_logger = $backend_logger;
            $sftp_processor->sftp_process();
        }

        $backend_logger->write_log("info", "End of SFTP backup script.", false);
    }
}
