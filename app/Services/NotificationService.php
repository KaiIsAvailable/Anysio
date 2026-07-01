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
        $sender = User::find($senderId);
        $validRecipientIds = User::query()
            ->whereIn('id', $recipientIds)
            ->when($sender->role !== 'super-admin', function ($query) use ($sender) {
                return $this->applyOwnershipFilter($query, $sender, 'id');
            })
            ->pluck('id')
            ->toArray();

        if (empty($validRecipientIds)) return;

        return DB::transaction(function () use ($senderId, $type, $title, $data, $validRecipientIds) {
            $notification = Notification::create([
                'id'         => Str::ulid(),
                'created_by' => $senderId,
                'type'       => $type,
                'title'      => $title,
                'data'       => $data,
            ]);

            $recipients = collect($validRecipientIds)->map(fn($userId) => [
                'id'              => Str::ulid(),
                'notification_id' => $notification->id,
                'user_id'         => $userId, // 确保这里的值是有效的
                'created_at'      => now(),
                'updated_at'      => now(),
            ])->toArray();

            NotificationRecipient::insert($recipients);
            return $notification;
        });
    }
}