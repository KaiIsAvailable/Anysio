<?php
namespace App\Services;

use App\Models\Notification;
use App\Models\NotificationRecipient;
use App\Models\User;
use App\Traits\RoleBasedDataTrait;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class NotificationService
{
    use RoleBasedDataTrait;
    /**
     * @param int|string $senderId  执行动作的用户ID
     * @param string $type          通知类型 (如: 'lease_created')
     * @param string $title         通知标题
     * @param array $data           JSON 数据
     * @param array $recipientIds   接收者ID数组
     */
    public function send($senderId, $type, $title, $data, array $recipientIds)
    {
        if (!$senderId) $senderId = User::where('role', 'admin')->value('id');

        // ✅ Simply fetch all existing users whose IDs were passed in, 
        // without applying the restrictive ownership filter to the recipient list.
        $validRecipientIds = User::query()
            ->whereIn('id', $recipientIds)
            ->pluck('id')
            ->toArray();

        if (empty($validRecipientIds)) return;

        return DB::transaction(function () use ($senderId, $type, $title, $data, $validRecipientIds) {
            $notification = Notification::create([
                'id'         => Str::ulid(),
                'created_by' => $senderId, // null = System
                'type'       => $type,
                'title'      => $title,
                'data'       => $data,
            ]);

            $recipients = collect($validRecipientIds)->map(fn ($userId) => [
                'id'              => Str::ulid(),
                'notification_id' => $notification->id,
                'user_id'         => $userId,
                'created_at'      => now(),
                'updated_at'      => now(),
            ])->toArray();

            NotificationRecipient::insert($recipients);
            return $notification;
        });
    }
}