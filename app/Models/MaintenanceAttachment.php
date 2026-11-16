<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use App\Traits\Auditable;

class MaintenanceAttachment extends Model
{
    use HasUlids, Auditable;
    protected $guarded = [];
    public function maintenance() { return $this->belongsTo(Maintenance::class); }
}
