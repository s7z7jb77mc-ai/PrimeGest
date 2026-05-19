<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\Entreprise;
use Inertia\Inertia;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        // 1. Validation
        $credentials = $request->validate([
            'company_name' => 'required|string',
            'email'        => 'required|string|email',
            'password'     => 'required|string',
        ]);

        // 2. Vérifier si l’entreprise existe
        $entreprise = Entreprise::where('name', $credentials['company_name'])->first();

        if (! $entreprise) {
            return back()->withErrors([
                'company_name' => 'Entreprise inconnue. Vérifiez le nom saisi.',
            ])->onlyInput('company_name', 'email');
        }

        // 3. Vérifier user lié à cette entreprise
        $user = User::where('email', $credentials['email'])
                    ->where('entreprise_id', $entreprise->id)
                    ->first();

        if (! $user) {
            return back()->withErrors([
                'email' => 'Cet utilisateur n’appartient pas à cette entreprise.',
            ])->onlyInput('email');
        }

        // 4. Vérifier mot de passe
        if (! Hash::check($credentials['password'], $user->password)) {
            return back()->withErrors([
                'password' => 'Mot de passe incorrect.',
            ])->onlyInput('email');
        }

        // 5. Authentifier l’utilisateur
        Auth::login($user, $request->boolean('remember'));

        // 6. Redirection Inertia
        return Inertia::location(route('dashboard'));
    }
}
