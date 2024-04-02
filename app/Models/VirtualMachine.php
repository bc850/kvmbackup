<?php

namespace App\Models;

use App\Models\SftpSite;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VirtualMachine extends Model
{
    use HasFactory;

    public function sftp_sites()
    {
        return $this->belongsToMany(SftpSite::class);
    }
}
