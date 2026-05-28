<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        \App\Models\Client::class => \App\Policies\ClientPolicy::class,
        \App\Models\Fournisseur::class => \App\Policies\FournisseurPolicy::class,
        \App\Models\Employe::class => \App\Policies\EmployePolicy::class,
        \App\Models\Produit::class => \App\Policies\ProduitPolicy::class,
        \App\Models\User::class => \App\Policies\UserPolicy::class,
        \App\Models\Parametre::class => \App\Policies\ParametrePolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        $this->registerPolicies();

        Gate::define('super_admin-only', function ($user) {
            return $user?->isSuperAdmin() || $user?->isManagerOfCurrentSuccursale();
        });
    }
}
