<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Entreprise;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;
use Inertia\Inertia;
use Inertia\Response;

class RegisteredUserController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Auth/Register');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'entreprise_name'    => 'required|string|max:255',
            'entreprise_email'   => 'nullable|email|max:255',
            'entreprise_phone'   => 'nullable|string|max:50',
            'entreprise_address' => 'nullable|string|max:255',
            'admin_name'         => 'required|string|max:255',
            'email'              => 'required|string|lowercase|email|max:255|unique:' . User::class,
            'password'           => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        // Créer l'entreprise avec essai gratuit 7 jours (plan pro)
        $entreprise = Entreprise::create([
            'name'            => $validated['entreprise_name'],
            'slug'            => Str::slug($validated['entreprise_name']),
            'email'           => $validated['entreprise_email'] ?? $validated['email'],
            'phone'           => $validated['entreprise_phone'] ?? null,
            'address'         => $validated['entreprise_address'] ?? null,
            'plan'            => 'pro',
            'plan_expires_at' => now()->addDays(7),
            'sync_version'    => 1,
        ]);

        // Créer le super admin lié à cette entreprise
        $user = User::create([
            'name'          => $validated['admin_name'],
            'email'         => $validated['email'],
            'password'      => Hash::make($validated['password']),
            'role'          => 'super_admin',
            'entreprise_id' => $entreprise->id,
        ]);

        // Lier l'admin à l'entreprise
        $entreprise->update(['user_id' => $user->id]);

        // Enregistrer la période d'essai
        Subscription::create([
            'entreprise_id' => $entreprise->id,
            'plan'          => 'pro',
            'amount'        => 0,
            'devise'        => 'USD',
            'status'        => 'trial',
            'starts_at'     => now(),
            'expires_at'    => now()->addDays(7),
        ]);

        event(new Registered($user));

        Auth::login($user);

        return redirect(route('dashboard', absolute: false));
    }
}
