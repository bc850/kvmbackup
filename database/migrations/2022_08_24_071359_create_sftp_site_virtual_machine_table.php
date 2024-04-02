<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSftpSiteVirtualMachineTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('sftp_site_virtual_machine', function (Blueprint $table) {
            // set a second param for the foreign key and the primary in order to avoid this error:
            // SQLSTATE[42000]: Syntax error or access violation: 1059 Identifier name
            $table->bigInteger('virtual_machine_id')->unsigned()->index();
            $table->foreign('virtual_machine_id', 'vm_id')->references('id')->on('virtual_machines')->onDelete('cascade');
            $table->bigInteger('sftp_site_id')->unsigned()->index();
            $table->foreign('sftp_site_id', 'sftp_id')->references('id')->on('sftp_sites')->onDelete('cascade');
            $table->primary(['virtual_machine_id', 'sftp_site_id'], "sv_primary");
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('sftp_site_virtual_machine');
    }
}
