<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSftpSitesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('sftp_sites', function (Blueprint $table) {
            $table->id();
            $table->string("name");
            $table->string("host");
            $table->integer("port");
            $table->string("username");
            $table->string("password");
            $table->string("private_key");
            $table->boolean("tx");
            $table->boolean("rx");
            $table->string("remote_sub_directory");
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
        Schema::dropIfExists('sftp_sites');
    }
}
