<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Entreprise;
use App\Models\Subscription;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PlanController extends Controller
{
    public function index()
    {
        $entreprises = Entreprise::with(['subscriptions' => function ($q) {
            $q->latest()->limit(1);
        }])
        ->orderBy('created_at', 'desc')
        ->get()
        ->map(fn($e) => [
            'id'              => $e->id,
            'name'            => $e->name,
            'plan'            => $e->plan,
            'plan_expires_at' => $e->plan_expires_at,
            'storage_used_mb' => $e->storage_used_mb,
            'created_at'      => $e->created_at->format('d/m/Y'),
        ]);

        $subscriptions = Subscription::with('entreprise')
            ->where('status', 'pending')
            ->latest()
            ->get()
            ->map(fn($s) => [
                'id'               => $s->id,
                'entreprise'       => $s->entreprise->name,
                'plan'             => $s->plan,
                'amount'           => $s->amount,
                'payment_method'   => $s->payment_method,
                'payment_reference'=> $s->payment_reference,
                'created_at'       => $s->created_at->format('d/m/Y H:i'),
            ]);

        return Inertia::render('Admin/Plans', [
            'entreprises'   => $entreprises,
            'subscriptions' => $subscriptions,
        ]);
    }

    public function activate(Request $request, Entreprise $entreprise)
    {
        $request->validate([
            'plan'     => 'required|in:free,premium,pro',
            'duration' => 'required|integer|min:1|max:12',
        ]);

        $entreprise->update([
            'plan'            => $request->plan,
            'plan_expires_at' => now()->addMonths($request->duration),
        ]);

        Subscription::create([
            'entreprise_id'     => $entreprise->id,
            'plan'              => $request->plan,
            'amount'            => $request->plan === 'premium' ? 7 : 10,
            'payment_method'    => $request->payment_method ?? 'manual',
            'payment_reference' => $request->payment_reference ?? 'admin',
            'status'            => 'confirmed',
            'starts_at'         => now(),
            'expires_at'        => now()->addMonths($request->duration),
            'confirmed_by'      => $request->user()->id,
        ]);

        return back()->with('success', "Plan {$request->plan} activé pour {$entreprise->name}");
    }

    public function downgrade(Entreprise $entreprise)
    {
        $entreprise->update([
            'plan'            => 'free',
            'plan_expires_at' => null,
        ]);

        return back()->with('success', "Retour au plan Free pour {$entreprise->name}");
    }
}
