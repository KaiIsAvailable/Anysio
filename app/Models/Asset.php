<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUlids;

class Asset extends Model
{
    use HasUlids;

    protected $fillable = ['user_id', 'name'];

    public function rooms()
    {
        return $this->belongsToMany(Room::class, 'asset_room')
                    ->using(RoomAsset::class)
                    ->withPivot('condition', 'last_maintenance', 'remark');
    }
}
