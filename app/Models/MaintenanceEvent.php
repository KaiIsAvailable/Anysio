<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUlids;

class MaintenanceEvent extends Model
{
    use HasUlids;
    protected $guarded = [];
    protected $casts = ['changes' => 'array'];
}
