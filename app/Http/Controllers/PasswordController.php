<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class PasswordController extends Controller
{
    public function request(): View
    {
        return view('password.request');
    }
}
