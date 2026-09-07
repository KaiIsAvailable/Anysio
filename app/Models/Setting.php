<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Traits\Auditable;

class Setting extends Model
{
    use Auditable;
    
    protected $fillable = [
        'user_id',
        'key',
        'value',
        'is_active',
    ];

    protected $casts = [
        'value' => 'array',
        'is_active' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Get all settings for a user, merged with config defaults.
     */
    public static function getResolvedSettings($userId)
    {
        $defaultConfigs = config('settings', []);
        $dbSettings = self::where('user_id', $userId)->get()->keyBy('key');

        $resolved = [];

        foreach ($defaultConfigs as $key => $defaultValues) {
            $dbSetting = $dbSettings->get($key);

            $resolved[$key] = [
                'is_active' => $dbSetting ? (bool) $dbSetting->is_active : true,
                // Merge DB json values with config defaults so missing inner keys are safe
                'value' => array_merge($defaultValues, $dbSetting->value ?? []),
            ];
        }

        return $resolved;
    }
}