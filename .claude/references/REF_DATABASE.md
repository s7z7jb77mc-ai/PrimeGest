# RÉFÉRENCE : Base de données PrimeGest
# Consulté par Claude Code quand il a besoin du détail.
# Skill résumé : .claude/skills/SKILL_DATABASE.md

## 1. CONFIGURATION DOUBLE BDD

```php
// config/database.php
'connections' => [
    'sqlite' => [
        'driver'                  => 'sqlite',
        'database'                => storage_path('app/primegest.db'),
        'foreign_key_constraints' => true,
        'busy_timeout'            => 5000,
    ],
    'mysql' => [
        'driver'    => 'mysql',
        'host'      => env('CLOUD_DB_HOST', '127.0.0.1'),
        'port'      => env('CLOUD_DB_PORT', '3306'),
        'database'  => env('CLOUD_DB_DATABASE', 'primegest_prod'),
        'username'  => env('CLOUD_DB_USERNAME', 'primegest_user'),
        'password'  => env('CLOUD_DB_PASSWORD', ''),
        'charset'   => 'utf8mb4',
        'collation' => 'utf8mb4_unicode_ci',
        'strict'    => true,
        'engine'    => 'InnoDB',
    ],
],
```

## 2. SCHÉMA COMPLET DES TABLES

### Tables système

```sql
-- entreprises
entreprises : id, uuid, nom, plan (enum: free/premium/succursales),
              plan_expires_at, devise (default USD), logo,
              settings (JSON), storage_used_mb (default 0),
              created_at, updated_at

-- succursales
succursales : id, uuid, entreprise_id, nom, adresse, manager_id,
              statut (enum: actif/inactif),
              synced, device_id, created_at, updated_at

-- users
users : id, uuid, entreprise_id, succursale_id, nom, prenom,
        email, password, role (enum: super_admin/manager/vendeur/caissier),
        statut (enum: actif/inactif/suspendu),
        remember_token, synced, device_id, created_at, updated_at

-- subscriptions (cloud uniquement — pas de synced/uuid)
subscriptions : id, entreprise_id, plan, montant (10,2),
                mode_paiement, reference, date_debut, date_fin,
                statut (enum: actif/expire/annule), created_at, updated_at
```

### Tables métier

```sql
-- produits
produits : id, uuid, entreprise_id, succursale_id,
           code, nom, categorie, unite,
           prix_achat (15,2), prix_vente (15,2),
           quantite (int default 0), seuil_alerte (int default 5),
           statut (enum: actif/inactif),
           synced, device_id, created_at, updated_at

-- mouvement_stocks
mouvement_stocks : id, uuid, entreprise_id, succursale_id, produit_id,
                   type (enum: entree/sortie/ajustement/transfert),
                   quantite (int), prix_unitaire (15,2),
                   quantite_avant (int), quantite_apres (int),
                   reference_id, commentaire, user_id,
                   synced, device_id, created_at, updated_at

-- tiers (clients + fournisseurs fusionnés)
tiers : id, uuid, entreprise_id, succursale_id,
        type (enum: client/fournisseur),
        nom, telephone, email, adresse,
        solde (15,2 default 0), notes,
        statut (enum: actif/inactif/bloque),
        synced, device_id, created_at, updated_at

-- ventes
ventes : id, uuid, entreprise_id, succursale_id,
         reference (unique par entreprise), tiers_id (nullable),
         user_id, montant_ht (15,2), tva (5,2 default 0),
         montant_ttc (15,2), remise (15,2 default 0),
         mode_paiement (enum: cash/credit/mobile/autre),
         statut (enum: paye/credit/annule), notes,
         vendu_at (timestamp),
         synced, device_id, created_at, updated_at

-- vente_lignes
vente_lignes : id, uuid, vente_id, produit_id,
               quantite (int), prix_unitaire (15,2),
               remise_ligne (15,2 default 0), total_ligne (15,2),
               synced, device_id, created_at, updated_at

-- caisses
caisses : id, uuid, entreprise_id, succursale_id,
          date_operation, description,
          entree (15,2 default 0), sortie (15,2 default 0),
          solde (15,2), type (enum: vente/achat/salaire/transfert/autre),
          reference_id, user_id,
          synced, device_id, created_at, updated_at

-- dettes
dettes : id, uuid, entreprise_id, succursale_id, tiers_id,
         type (enum: client/fournisseur), origine_id (nullable),
         montant_total (15,2), montant_paye (15,2 default 0),
         solde (15,2),
         echeance (date nullable),
         statut (enum: en_attente/partiel/solde/retard),
         synced, device_id, created_at, updated_at

-- dette_paiements
dette_paiements : id, uuid, dette_id, montant (15,2),
                  mode_paiement, notes, paye_at,
                  synced, device_id, created_at, updated_at

-- employes
employes : id, uuid, entreprise_id, succursale_id,
           nom, prenom, email, telephone, poste,
           salaire_base (15,2), date_embauche,
           statut (enum: actif/inactif),
           synced, device_id, created_at, updated_at

-- fiches_paie
fiches_paie : id, uuid, entreprise_id, succursale_id, employe_id,
              mois (varchar), annee (int),
              salaire_base (15,2), primes (15,2), retenues (15,2),
              salaire_brut (15,2), net_a_payer (15,2),
              statut (enum: en_attente/paye),
              date_paiement (date nullable),
              synced, device_id, created_at, updated_at

-- journals
journals : id, uuid, entreprise_id, succursale_id, produit_id (nullable),
           type (enum: entree/sortie/vente/achat/autre),
           montant (15,2), description, dateheure_operation, user_id,
           synced, device_id, created_at, updated_at

-- transfers (inter-succursales)
transfers : id, uuid, entreprise_id,
            succursale_source_id, succursale_dest_id,
            type (enum: argent/stock),
            montant (15,2 nullable), produit_id (nullable),
            quantite (int nullable),
            statut (enum: en_attente/confirme/annule),
            confirme_par (user_id nullable), confirme_at (timestamp nullable),
            synced, device_id, created_at, updated_at

-- taches
taches : id, uuid, entreprise_id, succursale_id,
         titre, description (text nullable),
         assigne_a (user_id), cree_par (user_id),
         priorite (enum: basse/normale/haute/urgente),
         statut (enum: a_faire/en_cours/termine),
         echeance (date nullable),
         synced, device_id, created_at, updated_at

-- messages
messages : id, uuid, entreprise_id, succursale_id,
           sender_id, receiver_id (nullable), groupe_id (nullable),
           contenu (text), type (enum: texte/fichier/alerte),
           lu (boolean default false),
           synced, device_id, created_at, updated_at
```

### Tables offline uniquement (pas de synced/uuid)

```sql
-- sync_queue
sync_queue : id, device_id, table_name, record_uuid,
             succursale_uuid (nullable),
             operation (enum: insert/update/delete),
             payload (json), checksum (varchar 64),
             status (enum: pending/syncing/done/conflict),
             attempts (tinyint default 0),
             error_message (nullable), synced_at (nullable),
             created_at

-- conflict_log
conflict_log : id, table_name, record_uuid,
               local_payload (json), cloud_payload (json),
               resolved (boolean default false),
               created_at
```

## 3. ENUMS PHP 8.1

```php
// app/Enums/RoleEnum.php
enum RoleEnum: string {
    case SuperAdmin = 'super_admin';
    case Manager    = 'manager';
    case Vendeur    = 'vendeur';
    case Caissier   = 'caissier';
}

// app/Enums/PlanEnum.php
enum PlanEnum: string {
    case Free        = 'free';
    case Premium     = 'premium';
    case Succursales = 'succursales';
}

// app/Enums/StatutVenteEnum.php
enum StatutVenteEnum: string {
    case Paye   = 'paye';
    case Credit = 'credit';
    case Annule = 'annule';
}
```

## 4. CONVENTIONS DE NOMMAGE

| Élément         | Convention               | Exemple                  |
|-----------------|--------------------------|--------------------------|
| Tables          | snake_case pluriel FR    | ventes, fiches_paie      |
| Colonnes        | snake_case FR            | montant_ttc, succursale_id |
| Clés étrangères | table_singulier_id       | produit_id, succursale_id |
| Modèles         | PascalCase singulier     | Vente, FichePaie         |
| Relations BelongsTo | camelCase singulier  | $vente->tiers()          |
| Relations HasMany   | camelCase pluriel    | $vente->lignes()         |

## 5. ORDRE DES SEEDERS

```php
// Respecter l'ordre des dépendances FK
$this->call([
    EntrepriseSeeder::class,   // 1
    SucursaleSeeder::class,    // 2
    UserSeeder::class,         // 3 (besoin succursale)
    ProduitSeeder::class,      // 4
    TiersSeeder::class,        // 5
    VenteSeeder::class,        // 6 (besoin produits + tiers)
]);
```

## 6. REQUÊTES OPTIMISÉES FRÉQUENTES

```php
// Top produits vendus
DB::table('vente_lignes')
    ->join('ventes',   'vente_lignes.vente_id',   '=', 'ventes.id')
    ->join('produits', 'vente_lignes.produit_id', '=', 'produits.id')
    ->where('ventes.entreprise_id', $entrepriseId)
    ->where('ventes.statut', 'paye')
    ->selectRaw('produits.nom, produits.uuid,
                 SUM(vente_lignes.quantite) as quantite_vendue,
                 SUM(vente_lignes.total_ligne) as chiffre_affaires')
    ->groupBy('produits.id', 'produits.nom', 'produits.uuid')
    ->orderByDesc('quantite_vendue')
    ->limit(10)
    ->get();

// Ventes par succursale (dashboard centralisé)
Vente::where('entreprise_id', $id)
    ->selectRaw('succursale_id, SUM(montant_ttc) as total, COUNT(*) as nombre')
    ->with('succursale:id,nom')
    ->groupBy('succursale_id')
    ->orderByDesc('total')
    ->get();

// Produits sous le seuil d'alerte
Produit::where('entreprise_id', $id)
    ->whereColumn('quantite', '<=', 'seuil_alerte')
    ->where('statut', 'actif')
    ->with('succursale:id,nom')
    ->select(['uuid', 'nom', 'quantite', 'seuil_alerte', 'succursale_id'])
    ->get();
```
