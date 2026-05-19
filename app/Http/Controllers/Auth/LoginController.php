<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\Entreprise;

class LoginController extends Controller
{
    public function create()
    {
        return inertia('Auth/Login');
    }

    public function store(Request $request)
{
    $request->validate([
        'company_name' => 'required|string',
        'email' => 'required|email',
        'password' => 'required|string',
    ]);

    // 1. Vérifier que l'entreprise existe
    $entreprise = Entreprise::where('name', $request->company_name)->first();

    if (! $entreprise) {
        return back()->withErrors([
            'company_name' => 'Entreprise introuvable. Vérifiez le nom saisi.',
        ]);
    }

    // 2. Vérifier que l'utilisateur existe
    $user = User::where('email', $request->email)->first();

    if (! $user) {
        return back()->withErrors([
            'email' => 'Aucun utilisateur trouvé avec cet email.',
        ]);
    }

    // 3. Vérifier d'abord le mot de passe
    if (! Hash::check($request->password, $user->password)) {
        return back()->withErrors([
            'password' => 'Mot de passe incorrect.',
        ]);
    }

    // 4. Vérifier que l'utilisateur appartient bien à cette entreprise
    if (
        $entreprise->user_id !== $user->id &&  // pas super admin
        $user->entreprise_id !== $entreprise->id // pas employé de cette entreprise
    ) {
        return back()->withErrors([
            'email' => 'Cet utilisateur n’appartient pas à cette entreprise.',
        ]);
    }

    // 5. Authentifier l'utilisateur
    Auth::login($user, $request->boolean('remember'));

    // 6. Redirection après connexion
    return redirect()->intended('/dashboard');
}
 }
