<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class StaffController extends Controller
{
    public function index(Request $request)
    {
        return view('adminSide.userManagement.staff.index');
    }
}
