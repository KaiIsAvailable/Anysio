<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class BulkOperation extends Model
{
    use HasUlids;

    protected $guarded = [];

    protected $hidden = ['payload'];

    protected function casts(): array
    {
        return ['payload' => 'encrypted:array', 'counts' => 'array', 'errors' => 'array', 'acknowledged_at' => 'datetime', 'total' => 'integer', 'processed' => 'integer', 'page' => 'integer'];
    }
}
