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