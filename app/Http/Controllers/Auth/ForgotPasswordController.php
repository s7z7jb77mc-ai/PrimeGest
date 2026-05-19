<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Inertia\Inertia;

class ForgotPasswordController extends Controller
{
    public function index()
    {
        return Inertia::render('Auth/ForgotPassword');
    }
}
