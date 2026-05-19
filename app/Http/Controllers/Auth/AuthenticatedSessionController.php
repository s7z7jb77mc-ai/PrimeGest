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
use App\Models\Succursale;

class AuthenticatedSessionController extends Controller
{
    /**
     * Affiche la page de connexion
     */
    public function create()
    {
        return Inertia::render('Auth/Login');
    }

    /**
     * Gère la tentative de connexion.
     *
     * Connexion auto vers succursale :
     * Si l'utilisateur est lié à un employé qui a une succursale_id,
     * on le redirige automatiquement vers sa succursale après connexion.
     */
    public function store(Request $request)
    {
        $companyName = trim((string) $request->input('company_name', ''));

        // ── Connexion sans nom d'entreprise (super admin / dev) ──────────
        if ($companyName === '') {
            $loginRequest = LoginRequest::createFrom($request);
            $loginRequest->setContainer(app())->setRedirector(app('redirect'));
            $loginRequest->authenticate();
            $request->session()->regenerate();

            // Connexion auto succursale même sans company_name
            return $this->redirectAfterLogin($request);
        }

        // ── Connexion avec nom d'entreprise ──────────────────────────────
        $credentials = $request->validate([
            'company_name' => 'required|string',
            'email'        => 'required|string|email',
            'password'     => 'required|string',
        ]);

        $entreprise = Entreprise::where('name', $credentials['company_name'])->first();
        if (!$entreprise) {
            return back()->withErrors([
                'company_name' => 'Cette entreprise n\'existe pas.',
            ])->onlyInput('company_name', 'email');
        }

        $user = User::where('email', $credentials['email'])
            ->where('entreprise_id', $entreprise->id)
            ->first();

        if (!$user || !Hash::check($credentials['password'], $user->password)) {
            return back()->withErrors([
                'email' => 'Email non reconnu ou mot de passe incorrect.',
            ])->onlyInput('company_name', 'email');
        }

        Auth::login($user, $request->boolean('remember'));
        $request->session()->regenerate();

        return $this->redirectAfterLogin($request);
    }

    /**
     * Redirige l'utilisateur après connexion.
     *
     * Logique :
     * 1. Super admin → dashboard central (pas de succursale forcée)
     * 2. Utilisateur lié à un employé qui a une succursale_id → succursale auto
     * 3. Sinon → dashboard normal
     */
    private function redirectAfterLogin(Request $request)
    {
        $user = Auth::user();

        // Super admin : dashboard central, pas de redirection succursale
        if ($user->isSuperAdmin()) {
            return redirect()->intended('/dashboard');
        }

        // Charger l'employé lié à l'utilisateur
        $employe = $user->employe;

        if ($employe && !empty($employe->succursale_id)) {
            // Vérifier que la succursale appartient bien à l'entreprise
            $succursale = Succursale::where('id', $employe->succursale_id)
                ->where('entreprise_id', $user->entreprise_id)
                ->where('active', true)
                ->first();

            if ($succursale) {
                // ✅ Stocker la succursale en session → même logique que SuccursaleController::show()
                session(['succursale_id' => $succursale->id]);
                return redirect('/dashboard');
            }
        }

        // Pas de succursale → dashboard normal
        return redirect()->intended('/dashboard');
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