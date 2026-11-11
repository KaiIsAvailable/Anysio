<?php

/**
 * 将字符串转换为大写（示例）
 */
if (!function_exists('toupper')) {
    function toupper($string)
    {
        return mb_strtoupper($string, 'UTF-8');
    }
}

if (!function_exists('get_status_color')) {
    // Use the badge palette for standalone text, optionally matching its background.
    function get_status_color(?string $status, bool $badgeBackground = false): string
    {
        if (!$badgeBackground) {
            return match (get_status_badge($status, textOnly: true)) {
                'text-amber-800' => 'text-yellow-500',
                'text-emerald-800' => 'text-green-600',
                'text-rose-800' => 'text-red-600',
                default => get_status_badge($status, textOnly: true),
            };
        }

        preg_match('/\bbg-[a-z]+-\d+\b/', get_status_badge($status), $matches);

        // Full class names allow Tailwind to include these utilities in the build.
        return match ($matches[0] ?? '') {
            'bg-amber-100' => 'text-amber-100',
            'bg-emerald-100' => 'text-emerald-100',
            'bg-rose-100' => 'text-rose-100',
            'bg-blue-100' => 'text-blue-100',
            'bg-indigo-100' => 'text-indigo-100',
            'bg-gray-100' => 'text-gray-100',
            'bg-purple-100' => 'text-purple-100',
            default => 'text-slate-100',
        };
    }
}

/**
 * date format = dd/mm/yyyy
 */
if (!function_exists('dateFormat')) {
    function dateFormat($date)
    {
        return \Carbon\Carbon::parse($date)->format('d/m/Y');
    }
}

if (!function_exists('sysDateFormat')) {
    function sysDateFormat($date)
    {
        return \Carbon\Carbon::parse($date)->format('Y-m-d');
    }
}

if (!function_exists('ic_to_gender')) {
    /**
     * 根据身份证号码判断性别
     * 
     * @param string|int $ic 身份证号码
     * @return string 'male' 或 'female'
     */
    function ic_to_gender($ic)
    {
        // 1. 去除可能存在的空格和连字符（如 950101-14-5123 变成 950101145123）
        $cleanIc = str_replace(['-', ' '], '', $ic);

        // 2. 如果非空，获取最后一位字符
        if (!empty($cleanIc)) {
            $lastChar = substr($cleanIc, -1);

            // 3. 转换为数字并判断：能被 2 整除（余数为 0）是双数，否则是单数
            if (is_numeric($lastChar)) {
                return ((int)$lastChar % 2 === 0) ? 'female' : 'male';
            }
        }

        // 如果传入的数据不合法，返回未知或默认值
        return 'unknown'; 
    }
} // <-- Correctly closed ic_to_gender here

/**
 * Get effective user ID for staff-to-admin mapping
 */
if (!function_exists('get_effective_user')) {
    function get_effective_user()
    {
        $user = \Illuminate\Support\Facades\Auth::user();
        if (!$user) return null;

        if ($user->role === 'staff') {
            $staff = \App\Models\Staff::where('user_id', $user->id)->first();
            
            if ($staff && $staff->user_mgnt_id) {
                // Find the user_management record directly
                $userMgmt = \App\Models\UserManagement::find($staff->user_mgnt_id);
                
                // If it exists and has a user relationship, return that parent user
                if ($userMgmt && $userMgmt->user) {
                    return $userMgmt->user;
                }
            }
        }

        return $user;
    }
}

/**
 * Color of status
 */
/**
 * Get the Tailwind CSS badge classes for any entity status.
 */
if (!function_exists('get_status_badge')) {
    function get_status_badge(?string $status, bool $textOnly = false): string
    {
        $status = strtolower(trim($status ?? ''));

        $classes = match ($status) {
            // General / System Statuses & Completed Checkouts
            'active', 'occupied', 'paid', 'completed' => 'bg-emerald-100 text-emerald-800 border border-emerald-200',
            'pending', 'vacant'  => 'bg-amber-100 text-amber-800 border border-amber-200',
            'expired', 'maintenance', 'inactive' => 'bg-rose-100 text-rose-800 border border-rose-200',
            
            // Lease Specific Statuses
            'new'           => 'bg-blue-100 text-blue-800 border border-blue-200',
            'current'           => 'bg-blue-100 text-blue-800 border border-blue-200',
            'renew'         => 'bg-indigo-100 text-indigo-800 border border-indigo-200',
            
            // Check out can be amber while in-progress, or green when finalized/paid
            'check out'     => 'bg-amber-100 text-amber-800 border border-amber-200',
            'checked out'   => 'bg-emerald-100 text-emerald-800 border border-emerald-200',
            
            'end'           => 'bg-gray-100 text-gray-800 border border-gray-200',
            
            // Pending Action State
            'pending action' => 'bg-purple-100 text-purple-800 border border-purple-200',
            
            // Default Fallback
            default => 'bg-slate-100 text-slate-800 border border-slate-200',
        };

        if ($textOnly) {
            preg_match('/\btext-[a-z]+-\d+\b/', $classes, $matches);
            return $matches[0] ?? 'text-slate-800';
        }

        return $classes;
    }
}
