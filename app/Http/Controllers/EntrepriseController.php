<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Mail\EntrepriseCreatedMail;
use App\Models\Entreprise;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class EntrepriseController extends Controller
{
    public function create()
    {
        return inertia('Entreprise/Register');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'entreprise_name' => 'required|string|max:255|unique:entreprises,name',
            'entreprise_email' => 'required|email|unique:entreprises,email',
            'entreprise_phone' => 'nullable|string|max:20',
            'entreprise_address' => 'nullable|string|max:255',
            'admin_name' => 'required|string|max:255',
            'admin_email' => 'required|email|unique:users,email',
            'admin_password' => 'required|string|min:6|confirmed',
        ]);

        [$admin, $entreprise] = DB::transaction(function () use ($validated) {
            // Créer d'abord l'entreprise (sans user_id initial)
            $entreprise = Entreprise::create([
                'name' => $validated['entreprise_name'],
                'email' => $validated['entreprise_email'],
                'phone' => $validated['entreprise_phone'] ?? null,
                'address' => $validated['entreprise_address'] ?? null,
                'uuid' => Str::uuid(),
                'slug' => Str::slug($validated['entreprise_name']),
            ]);

            // Créer le super admin directement lié à l'entreprise
            $admin = User::create([
                'name' => $validated['admin_name'],
                'email' => $validated['admin_email'],
                'password' => Hash::make($validated['admin_password']),
                'role' => 'super_admin',
                'entreprise_id' => $entreprise->id,
            ]);

            // Lier l'entreprise à son admin
            $entreprise->update(['user_id' => $admin->id]);

            // Activer le plan Pro trial 2 jours
            $expiresAt = now()->addDays(2);

            $entreprise->update([
                'plan' => 'pro',
                'plan_expires_at' => $expiresAt,
            ]);

            Subscription::create([
                'entreprise_id' => $entreprise->id,
                'plan' => 'pro',
                'amount' => 0,
                'status' => 'trial',
                'payment_method' => 'trial',
                'payment_reference' => 'trial-2j-'.now()->format('YmdHis'),
                'starts_at' => now(),
                'expires_at' => $expiresAt,
            ]);

            return [$admin, $entreprise];
        });

        try {
            $mail = new EntrepriseCreatedMail(
                entrepriseName: $entreprise->name,
                super_adminName: $admin->name,
                super_adminEmail: $admin->email,
            );
            Mail::to($admin->email)->send($mail);

            if ($entreprise->email && $entreprise->email !== $admin->email) {
                Mail::to($entreprise->email)->send(new EntrepriseCreatedMail(
                    entrepriseName: $entreprise->name,
                    super_adminName: $admin->name,
                    super_adminEmail: $admin->email,
                ));
            }
        } catch (\Throwable $e) {
            Log::warning('Mail de bienvenue non envoyé : '.$e->getMessage(), [
                'entreprise' => $entreprise->name,
                'admin_email' => $admin->email,
            ]);
        }

        return redirect()->route('login')
            ->with('success', 'Entreprise créée avec succès. Connectez-vous avec votre email et mot de passe.');
    }
}
