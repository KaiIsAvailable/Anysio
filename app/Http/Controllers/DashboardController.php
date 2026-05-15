<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Owners;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        $isProfileIncomplete = false; // 默认资料是完整的

        // 1. 判断是否是 ownerAdmin
        if ($user->role === 'ownerAdmin') {
            // 2. 查出 owners 表的记录
            $ownerProfile = Owners::where('user_id', $user->id)->first();

            // 3. 在后台进行精准的 NULL 和 'null' 字符串检测
            if ($ownerProfile) {
                $fieldsToCheck = [
                    $ownerProfile->company_name,
                    $ownerProfile->ic_number,
                    $ownerProfile->phone_number, // 如果数据库是 phone 记得改名
                    $ownerProfile->gender
                ];

                foreach ($fieldsToCheck as $value) {
                    if (is_null($value) || $value === 'null' || trim($value) === '') {
                        $isProfileIncomplete = true;
                        break; // 只要有一个漏填，立刻标记为不完整，跳出循环
                    }
                }
            } else {
                // 如果连 owners 记录都没有，那肯定是不完整的
                $isProfileIncomplete = true; 
            }
        }

        // 4. 将结果传给 Blade 视图
        return view('dashboard', compact('isProfileIncomplete'));
    }
}