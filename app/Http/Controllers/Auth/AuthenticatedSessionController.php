<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use App\Http\Requests\Auth\LoginRequest;
use Inertia\Inertia;
use App\Models\User;
use App\Models\Entreprise;

class AuthenticatedSessionController extends Controller
{
    /**
     * Affiche la page de connexion
     */
    public function create()
    {
        // Correspond au composant resources/js/Pages/Auth/Login.vue
        return Inertia::render('Auth/Login');
    }

    /**
     * Gère la tentative de connexion
     */
    public function store(Request $request)
    {
        $companyName = trim((string) $request->input('company_name', ''));
        if ($companyName === '') {
            $loginRequest = LoginRequest::createFrom($request);
            $loginRequest->setContainer(app())->setRedirector(app('redirect'));
            $loginRequest->authenticate();
            $request->session()->regenerate();
            return redirect()->intended('/dashboard');
        }

        $credentials = $request->validate([
            'company_name' => 'required|string',
            'email' => 'required|string|email',
            'password' => 'required|string',
        ]);

        $entreprise = Entreprise::where('name', $credentials['company_name'])->first();
        if (! $entreprise) {
            return back()->withErrors([
                'company_name' => 'Cette entreprise n\'existe pas.',
            ])->onlyInput('company_name', 'email');
        }

        $user = User::where('email', $credentials['email'])
            ->where('entreprise_id', $entreprise->id)
            ->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            return back()->withErrors([
                'email' => 'Email non reconnu ou mot de passe incorrect.',
            ])->onlyInput('company_name', 'email');
        }

        Auth::login($user, $request->boolean('remember'));
        $request->session()->regenerate();
        return redirect()->to('/dashboard');
    }

    /**
     * Déconnexion
     */
    public function destroy(Request $request)
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }

}
