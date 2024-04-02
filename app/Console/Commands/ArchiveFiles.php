<?php

namespace App\Console\Commands;

use App\Models\Disk;
use App\Utilities\BackendLogger;
use App\Utilities\FileManagementProcessor;
use Illuminate\Console\Command;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

class ArchiveFiles extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'kvmbackup:archive_files';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Archives files.';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $all_backup_files = [];
        $backend_logger = new BackendLogger();
        $backend_logger->logger = new Logger(php_uname("n") . "->archive_files");
        $backend_logger->slack_logging = true;
        $backup_file_extensions = [".qcow2", ".sha1"];
        $disks = Disk::all();
        foreach ($disks as $disk) {
            $backend_logger->logger->pushHandler(new StreamHandler($disk->log_dir . date("Y-m") . "-backup_log.log", Logger::DEBUG));
            $backend_logger->write_log("info", "Starting File Archiver.", false);
            foreach ($backup_file_extensions as $extension) {
                $files = FileManagementProcessor::glob_recursive("{$disk->mount_point}/sftp/*{$extension}");
                $all_backup_files = array_merge($all_backup_files, $files);
            }
            FileManagementProcessor::archive_files($backend_logger, $all_backup_files, $disk->backup_files_root_dir, true, true);
        }
    }
}
