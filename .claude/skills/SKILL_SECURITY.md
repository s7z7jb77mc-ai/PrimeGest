# Skill : Sécurité PrimeGest

## Rôles utilisateur (ordre décroissant de permissions)
super_admin → tout voir, tout faire sur toute l'entreprise
manager     → tout faire sur SA succursale uniquement
l admin     → est un employé de l'entreprise qui est affecté dans l entreprise, il a acces aux pages que le super admin ou manager lui donne, il est ecrit et lit
caissier    → caisse uniquement de sa succursale

## Middleware à appliquer sur chaque route

```php
Route::middleware([
    'auth:sanctum',     // authentifié
    'entreprise',       // entreprise_id résolu
    'succursale.scope',     // filtre par succursale_id selon le rôle
    'plan.limit:ventes' // vérifie les limites du plan
])->group(function () {
    // routes protégées
});
```

## Règle succursaleScope — OBLIGATOIRE sur tous les contrôleurs

Un manager NE PEUT PAS voir les données d'une autre succursale.
Toujours filtrer par branch_id quand le rôle est 'manager' :

```php
// Dans chaque Query — utiliser le scope automatique
public function scopeSuccursaleScoped($query)
{
    $user = auth()->user();
    if ($user->role === 'manager') {
        $query->where('Succursale_id', $user->branch_id);
    }
    return $query;
}
```

## Confirmation par mot de passe (transferts)
Les transferts inter-succursales exigent Hash::check() côté API.
Jamais côté frontend. Toujours via POST HTTPS.

## Plans Freemium — limites à vérifier
Free     : clients≤15, fournisseurs≤15, produits≤100, users=3
Premium  : users≤10, tout illimité sauf succursales
Branches : users≤20, succursales illimitées, tout illimité

Lire config/plan_limits.php pour les valeurs exactes.