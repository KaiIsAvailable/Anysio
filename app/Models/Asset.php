<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUlids;

class Asset extends Model
{
    use HasUlids;

    protected $fillable = [
        'user_id', 
        'name',
        'category',
        'status',
    ];

    public function rooms()
    {
        return $this->belongsToMany(Room::class, 'asset_room')
                    ->using(RoomAsset::class)
                    ->withPivot('condition', 'last_maintenance', 'remark');
    }

    public function user()
    {
        // 假设你的 asset 表里的外键字段名是 user_id
        return $this->belongsTo(User::class, 'user_id');
    }
}
