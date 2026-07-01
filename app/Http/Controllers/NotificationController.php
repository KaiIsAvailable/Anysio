<?php

namespace App\Http\Controllers;

use App\Traits\RoleBasedDataTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\NotificationRecipient;
use Illuminate\Support\Facades\Log;

class NotificationController extends Controller
{
    use RoleBasedDataTrait;
    public function index()
    {
        // 获取当前用户最近的通知，包含已读状态
        return NotificationRecipient::where('user_id', Auth::id())
            ->with('notification')
            ->latest()
            ->get()
            ->map(fn($r) => [
                'id'      => $r->id,
                'title'   => $r->notification->title,
                'data'    => $r->notification->data,
                'time'    => $r->created_at->diffForHumans(),
                'is_read' => !is_null($r->read_at), // 明确返回已读状态
            ]);
    }

    // 2. 标记某条通知已读
    public function markAsRead(Request $request, $id)
    {
        $recipient = NotificationRecipient::where('id', $id)->first();
        
        // 强制打印日志看看
        Log::info("Attempting to update read_at for ID: {$id}");
        
        $updated = $recipient->update(['read_at' => now()]);
        
        Log::info("Update result: " . ($updated ? 'Success' : 'Failed'));

        return response()->json(['success' => $updated]);
    }
}
