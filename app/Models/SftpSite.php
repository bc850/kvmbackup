<?php

namespace App\Models;

use App\Models\VirtualMachine;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SftpSite extends Model
{
    use HasFactory;

    public function virtual_machines()
    {
        return $this->belongsToMany(VirtualMachine::class);
    }
}
