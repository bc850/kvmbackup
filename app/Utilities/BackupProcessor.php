<?php

namespace App\Utilities;

use App\Utilities\FileManagementProcessor;
use Exception;

class BackupProcessor {
    public $backend_logger;
    public $checksumfile;
    public $file;
    public $newfile;
    public $sha1_checksum;
    public $virsh_list = "virsh list --all";
    public $virsh_shutdown = "virsh shutdown";
    public $virsh_shutdown_mode = "--mode acpi";
    public $virsh_start = "virsh start";
    public $vm;

    public function __construct() {}

    public function vm_process() {
        $this->vm_shutdown();
        $this->vm_copy();
        $this->vm_compress();
        $this->vm_start();
    }

    public function vm_compress() {
        // $this->checksumfile = .sha1 in sftp folder
        // $this->newfile = .qcow2 in sftp folder

        $files = [$this->checksumfile, $this->newfile];
        $path_parts = pathinfo($this->newfile);
        $filename = $path_parts["dirname"] . "/" . $path_parts["filename"] . ".tar";
        $this->backend_logger->write_log("info", ":compression: Starting file compression. Creating tarball.", true);
        FileManagementProcessor::tar_files($files, $filename);
        $this->backend_logger->write_log("info", "Unlinking files.", true);
        FileManagementProcessor::unlink_files($files);
        $this->backend_logger->write_log("info", "Zipping files.", true);
        FileManagementProcessor::pigz_files([$filename]);
        $this->backend_logger->write_log("info", ":compression: Compression complete.", true);
    }

    public function vm_copy() {
        $condition = true;
        $counter = 0;
        while ($condition) {
            $output = shell_exec($this->virsh_list);
            $parts = explode("\n", $output);
            foreach ($parts as $part) {
                if (
                    stripos($part, $this->vm->name) != false &&
                    stripos($part, "running") != false
                ) {
                    $counter++;
                    $this->backend_logger->write_log("info", $this->vm->name . " is still running. Waiting and trying again (Attempt {$counter}).");
                    if ($counter == 10) {
                        $this->vm_shutdown();
                    }
                    usleep(10000000); // wait 10 seconds
                } else if (
                    stripos($part, $this->vm->name) != false &&
                    stripos($part, "shut off") != false
                ) {
                    $this->backend_logger->write_log("info", "Creating SHA1 checksum.");
                    try {
                        $this->sha1_checksum = sha1_file($this->file);
                    } catch (Exception $e) {
                        $this->backend_logger->write_log("error", ":alert: Failed to create SHA1 checksum for {$this->vm->name}.", true);
                        $this->backend_logger->write_log("error", $e->getMessage());
                    }
                    $this->backend_logger->write_log("info", "SHA1 checksum created.");
                    $this->backend_logger->write_log("info", ":data_copy: Copying {$this->vm->name}", true);

                    if (file_exists($this->newfile)) {
                        $this->backend_logger->write_log("info", ":shield: {$this->vm->name} copy already exists, skipping.", true);
                        break 2;
                    } 
                    
                    if (!copy($this->file, $this->newfile)) {
                        $this->backend_logger->write_log("error", ":alert: Failed to copy {$this->vm->name}...", true);
                        throw new Exception("Failed to copy {$this->vm->name}.");
                    }

                    // check that the checksums match, write to file if equal
                    $this->backend_logger->write_log("info", ":heavy_plus_sign: File copied, checking that checksums match.", true);
                    try {
                        $newfile_checksum = sha1_file($this->newfile);
                    } catch (Exception $e) {
                        $this->backend_logger->write_log("error", ":alert: Failed to create SHA1 checksum for copied file.", true);
                        $this->backend_logger->write_log("error", $e->getMessage());
                        throw new Exception($e->getMessage());
                    }

                    if ($this->sha1_checksum != $newfile_checksum) {
                        $this->backend_logger->write_log("critical", ":alert: :sha1: SHA1 checksums do not match!", true);
                        throw new Exception("SHA1 checksums do not match!");
                    }
                    
                    $this->backend_logger->write_log("info", "SHA1 checksums match. Writing checksum to file.");
                    file_put_contents($this->checksumfile, $this->sha1_checksum);

                    $this->backend_logger->write_log("info", ":white_check_mark: Successfully copied {$this->vm->name}", true);
                    $condition = false;
                    break;
                }
            }
        }
    }

    public function vm_shutdown() {
        $output = shell_exec($this->virsh_list);
        $parts = explode("\n", $output);
        foreach ($parts as $part) {
            if (
                stripos($part, $this->vm->name) != false &&
                stripos($part, "running") != false
            ) {
                $shutdown_output = shell_exec(
                    $this->virsh_shutdown . 
                    " {$this->vm->name}" . 
                    " {$this->virsh_shutdown_mode}"
                );
                $this->backend_logger->write_log("info", ":red_circle: Shutdown in progress: " . $shutdown_output, true);
                break;
            }
        }
    }

    public function vm_start() {
        if ($this->vm->auto_restart) {
            $this->backend_logger->write_log("info", ":large_green_circle: Restarting the virtual machine: {$this->vm->name}.", true);
            shell_exec($this->virsh_start . " " . $this->vm->name);
        }
    }
}