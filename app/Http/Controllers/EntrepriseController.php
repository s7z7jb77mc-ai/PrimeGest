<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Entreprise;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
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
            'entreprise_name' => 'required|string|max:255|unique:entreprises,name',
            'entreprise_email' => 'required|email|unique:entreprises,email',
            'entreprise_phone' => 'nullable|string|max:20',
            'entreprise_address' => 'nullable|string|max:255',
            'admin_name' => 'required|string|max:255',
            'admin_email' => 'required|email|unique:users,email',
            'admin_password' => 'required|string|min:6|confirmed',
        ]);

        // Créer l’utilisateur admin
        $admin = User::create([
            'name' => $validated['admin_name'],
            'email' => $validated['admin_email'],
            'password' => Hash::make($validated['admin_password']),
            'role' => 'super_admin'
        ]);

        // Créer l’entreprise
        $entreprise = Entreprise::create([
            'name' => $validated['entreprise_name'],
            'email' => $validated['entreprise_email'],
            'phone' => $validated['entreprise_phone'] ?? null,
            'address' => $validated['entreprise_address'] ?? null,
            'uuid' => Str::uuid(),
            'slug' => Str::slug($validated['entreprise_name']),
            'user_id' => $admin->id
        ]);

        // Assigner l’entreprise à l’utilisateur admin
        $admin->update(['entreprise_id' => $entreprise->id]);

        return redirect()->route('login')->with('success', 'Entreprise créée avec succès. Connectez-vous.');
    }
}
