<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use App\Models\Entreprise;
use App\Models\User;

class PasswordResetLinkController extends Controller
{
    /**
     * Display the password reset link request view.
     */
    public function create(): Response
    {
        return Inertia::render('Auth/ForgotPassword', [
            'status' => session('status'),
        ]);
    }

    /**
     * Handle an incoming password reset link request.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => 'required|email',
            'company_name' => 'required|string',
        ]);

        $entreprise = Entreprise::where('name', $request->company_name)->first();
        if (! $entreprise) {
            throw ValidationException::withMessages([
                'company_name' => ['Cette entreprise n\'existe pas.'],
            ]);
        }
        $user = null;
        $user = User::where('email', $request->email)
            ->where('entreprise_id', $entreprise->id)
            ->where('role', 'super_admin')
            ->first();

        if (! $user) {
            throw ValidationException::withMessages([
                'email' => ['Ce compte n\'est pas super admin.'],
            ]);
        }

        $token = Password::broker()->createToken($user);
        $user->sendPasswordResetNotification($token);

        return back()->with('status', 'Un lien de réinitialisation de mot de passe a été envoyé à votre mail.');
    }
}
