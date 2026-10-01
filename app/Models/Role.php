<?php

namespace App\Models;

use Spatie\Permission\Models\Role as SpatieRole;
use Spatie\Permission\Exceptions\RoleAlreadyExists;
use Spatie\Permission\Guard;

class Role extends SpatieRole
{
    protected $guarded = [];

    public static function create(array $attributes = [])
    {
        $attributes['guard_name'] = $attributes['guard_name'] ?? Guard::getDefaultName(static::class);

        // Get user_id from attributes (can be null for global roles)
        $userId = $attributes['team_id'] ?? null;

        // Check if THIS specific user already has a role with this name
        $exists = static::where('name', $attributes['name'])
            ->where('guard_name', $attributes['guard_name'])
            ->where('team_id', $userId)
            ->exists();

        if ($exists) {
            throw RoleAlreadyExists::create($attributes['name'], $attributes['guard_name']);
        }

        return static::query()->create($attributes);
    }
}