<?php

namespace App\Utilities;

use App\Utilities\BackendLogger;
use App\Models\Disk;

class FileManagementProcessor {
    public $backend_logger;
    public $backup_file_extensions;
    public $delete_by_day;
    public $machine_name;
    
    public function __construct() {
        $this->machine_name = php_uname("n");
    }

    public function manage_disk_space(Disk $disk) {
        $disk_free_space_percentage = self::calculate_free_disk_space_percentage($disk->mount_point);
        $this->backend_logger->write_log("info", ":disk_utility: `{$this->machine_name}->{$disk->mount_point}`: Percentage of disk free space is {$disk_free_space_percentage}%", true);
        if ($disk_free_space_percentage < $disk->warning_percentage) {
            $this->backend_logger->write_log("warning", ":disk_utility: `{$this->machine_name}->{$disk->mount_point}`: Warning, percentage of disk free space is below " . number_format($disk->warning_percentage, 2) . "%", true);
        }
        
        // maintenance percentage is maximum level of disk usage we want to maintain
        if ($disk_free_space_percentage < $disk->maintenance_percentage) {
            $this->backend_logger->write_log("warning", ":disk_utility: `{$this->machine_name}->{$disk->mount_point}`: Percentage of disk free space has fallen below {$disk->maintenance_percentage}%. Managing disk space.", true);
            
            // get files from the root backup directory
            $all_backup_files = [];
            $sorted_backup_files = [];
            foreach ($this->backup_file_extensions as $extension) {
                $files = self::glob_recursive("{$disk->backup_files_root_dir}*{$extension}");
                $all_backup_files = array_merge($all_backup_files, $files);
            }

            // put full path in the key and basename of file as the value
            // use simple asort to sort the array in ascending order while keeping full filepath via the key intact
            foreach ($all_backup_files as $file) {
                $sorted_backup_files[$file] = basename($file);
            }

            asort($sorted_backup_files);

            while ($disk_free_space_percentage < $disk->baseline_percentage) {
                $first_file = true;
                $date_string = "";

                // if $this->delete_by_day is true, we are going to remove an entire days worth of files based on date prefix in the basename
                // then we will check to see if disk space percentages are satisifed.. if not, repeat
                if ($this->delete_by_day) {
                    foreach ($sorted_backup_files as $full_file_path => $base_file) {
                        if ($first_file) {
                            $first_file = false;
                            $date_string = substr($base_file, 0, 10);
                            $this->backend_logger->write_log("info", ":disk_utility: Removing entire day's worth of files for `{$date_string}`", true);
                        }
                        
                        if (stripos($base_file, $date_string) !== false) {
                            $this->backend_logger->write_log("info", ":disk_utility: Unlinking {$base_file}", true);
                            unlink($full_file_path);
                            
                            // shift first key/value pair off the array while preserving other key/value pairs
                            array_shift($sorted_backup_files);

                            continue;
                        }

                        break;
                    }

                    // re-calculate disk space
                    $disk_free_space_percentage = self::calculate_free_disk_space_percentage($disk->mount_point);
                    continue;
                }

                // deleting files one by one until condition is met
                $this->backend_logger->write_log("info", ":disk_utility: Unlinking " . basename(current($sorted_backup_files)) , true);
                unlink(array_key_first($sorted_backup_files));
                array_shift($sorted_backup_files);

                // re-calculate disk space
                $disk_free_space_percentage = self::calculate_free_disk_space_percentage($disk->mount_point);
            }
        }
    }

    public static function archive_files(BackendLogger $backend_logger, $files, $destination, $slack_logging = false, $rename = false, $unlink_files = false) {
        foreach ($files as $file) {
            // copies file from source to destination
            if (!$rename) {
                $backend_logger->write_log("info", ":data_copy: Copying `" . basename($file) . "` to `{$destination}`", $slack_logging);
                if (!copy($file, $destination . basename($file))) {
                    $backend_logger->write_log("error", ":alert: Failed to copy `{$file}` to `{$destination}`", $slack_logging);
                    throw new Exception("Failed to copy {$file}.");
                }
        
                $backend_logger->write_log("info", ":white_check_mark: `" . basename($file) . "` copied to `{$destination}`", $slack_logging);
                
                // unlinks source file if true
                if ($unlink_files) {
                    $backend_logger->write_log("info", "Unlinking {$file}");
                    unlink($file);
                }

                continue;
            }

            // moves file from source to destination
            $backend_logger->write_log("info", ":data_move: Moving `" . basename($file) . "` to `{$destination}`", $slack_logging);
            rename($file, $destination . basename($file));
            $backend_logger->write_log("info", ":white_check_mark: `" . basename($file) . "` moved to `{$destination}`", $slack_logging);
        }
    }

    public static function gzip_files($files) {
        foreach ($files as $file) {
            shell_exec("gzip $file");
        }
    }

    // Does not support flag GLOB_BRACE        
    public static function glob_recursive($pattern, $flags = 0) {
        $files = glob($pattern, $flags);
        foreach (glob(dirname($pattern).'/*', GLOB_ONLYDIR|GLOB_NOSORT) as $dir) {
            $files = array_merge($files, self::glob_recursive($dir.'/'.basename($pattern), $flags));
        }
        return $files;
    }

    public static function calculate_free_disk_space_percentage($disk_mount_point) {
        return number_format((disk_free_space($disk_mount_point) / disk_total_space($disk_mount_point)) * 100, 2);
    }

    // include a file extension if you want to gzip only certain file types
    public static function pigz_files($files, $extension = null) {
        if ($extension == null) {
            foreach ($files as $file) {
                shell_exec("pigz $file");
            }

            return;
        }

        foreach ($files as $files) {
            $path_parts = pathinfo($file);

            if ($path_parts["extension"] == $extension) {
                shell_exec("pigz $file");
            }
        }
    }

    public static function tar_files($files, $filename) {
        $files_to_tar = implode(" ", $files);
        shell_exec("tar -cf " . $filename . " " . $files_to_tar);
    }

    public static function unlink_files($files) {
        foreach ($files as $file) {
            unlink($file);
        }
    }
}