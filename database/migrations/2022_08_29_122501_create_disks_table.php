<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateDisksTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('disks', function (Blueprint $table) {
            $table->id();
            $table->string("mount_point");
            $table->string("backup_files_root_dir");
            $table->string("log_dir");
            $table->double("baseline_percentage", 4, 2);
            $table->double("maintenance_percentage", 4, 2);
            $table->double("warning_percentage", 4, 2);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('disks');
    }
}
