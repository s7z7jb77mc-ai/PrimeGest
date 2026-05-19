<?php
namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AuthController extends Controller
{
    public function showLogin()
    {
        if (auth('owner')->check()) return redirect('/owner/dashboard');
        return Inertia::render('Owner/Login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email'    => 'required|email',
            'password' => 'required',
        ]);

        if (!auth('owner')->attempt($credentials, $request->boolean('remember'))) {
            return back()->withErrors(['email' => 'Identifiants incorrects.']);
        }

        $request->session()->regenerate();
        return redirect('/owner/dashboard');
    }

    public function logout(Request $request)
    {
        auth('owner')->logout();
        $request->session()->invalidate();
        return redirect('/owner/login');
    }
}
