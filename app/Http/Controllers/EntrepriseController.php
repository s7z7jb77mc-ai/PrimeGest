<?php

namespace App\Http\Controllers;

use App\Mail\EntrepriseCreatedMail;
use App\Models\User;
use App\Models\Entreprise;
use Illuminate\Http\Request;
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

    public function store(Request $request)
    {
        $validated = $request->validate([
            'entreprise_name'    => 'required|string|max:255|unique:entreprises,name',
            'entreprise_email'   => 'required|email|unique:entreprises,email',
            'entreprise_phone'   => 'nullable|string|max:20',
            'entreprise_address' => 'nullable|string|max:255',
            'admin_name'         => 'required|string|max:255',
            'admin_email'        => 'required|email|unique:users,email',
            'admin_password'     => 'required|string|min:6|confirmed',
        ]);

        // ── Créer l'utilisateur Super Admin ───────────────────────────
        $admin = User::create([
            'name'     => $validated['admin_name'],
            'email'    => $validated['admin_email'],
            'password' => Hash::make($validated['admin_password']),
            'role'     => 'super_admin',
        ]);

        // ── Créer l'entreprise ────────────────────────────────────────
        $entreprise = Entreprise::create([
            'name'    => $validated['entreprise_name'],
            'email'   => $validated['entreprise_email'],
            'phone'   => $validated['entreprise_phone'] ?? null,
            'address' => $validated['entreprise_address'] ?? null,
            'uuid'    => Str::uuid(),
            'slug'    => Str::slug($validated['entreprise_name']),
            'user_id' => $admin->id,
        ]);

        // ── Lier l'admin à l'entreprise ───────────────────────────────
        $admin->update(['entreprise_id' => $entreprise->id]);

        // ── Envoyer le mail de bienvenue ──────────────────────────────
        // On envoie à l'email de l'admin ET à l'email de l'entreprise
        // si différents. On attrape les exceptions pour ne pas bloquer
        // l'inscription si le mailer est mal configuré.
        try {
            $mail = new EntrepriseCreatedMail(
                entrepriseName: $entreprise->name,
                super_adminName: $admin->name,
                super_adminEmail: $admin->email,
            );

            // Mail principal → super admin
            Mail::to($admin->email)->send($mail);

            // Mail copie → email entreprise si différent
            if ($entreprise->email && $entreprise->email !== $admin->email) {
                Mail::to($entreprise->email)->send(new EntrepriseCreatedMail(
                    entrepriseName: $entreprise->name,
                    super_adminName: $admin->name,
                    super_adminEmail: $admin->email,
                ));
            }
        } catch (\Throwable $e) {
            // On logue l'erreur mais on ne bloque pas l'inscription
            Log::warning('Mail de bienvenue non envoyé : ' . $e->getMessage(), [
                'entreprise' => $entreprise->name,
                'admin_email' => $admin->email,
            ]);
        }

        return redirect()->route('login')
            ->with('success', 'Entreprise créée avec succès. Un email de bienvenue vous a été envoyé. Connectez-vous.');
    }
}