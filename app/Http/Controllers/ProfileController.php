<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;
use App\Models\Parametre;
use Inertia\Inertia;

class ProfileController extends Controller
{
    public function index()
    {
        $user = Auth::user()?->load('employe');
        $parametres = null;
        if ($user) {
            $parametres = Parametre::where('entreprise_id', $user->entreprise_id)->first();
        }

        return Inertia::render('Profile/Index', [
            'user' => $user,
            'parametres' => $parametres,
        ]);
    }
}
