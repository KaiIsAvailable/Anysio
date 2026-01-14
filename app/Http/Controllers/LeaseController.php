<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class LeaseController extends Controller
{
    public function index(Request $request)
    {
        return view('adminSide.leases.index');
    }
}
