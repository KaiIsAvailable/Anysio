<?php
namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    public function index(Request $request)
    {
        $logs = AuditLog::with(['user', 'auditable']) 
                ->latest()
                ->paginate(10)
                ->withQueryString();

        return view('adminSide.auditLog.index', compact('logs'));
    }
}