# SKILL : Base de données PrimeGest
# Lire avant toute migration, modèle ou requête Eloquent.
# Référence complète : .claude/references/REF_DATABASE.md

## 1. CONFIGURATION

```php
// config/database.php
'default' => env('DB_CONNECTION', 'sqlite'),
// sqlite = local offline | mysql = cloud production
```

## 2. RÈGLE UUID — OBLIGATOIRE SUR TOUTES LES TABLES

Chaque table a DEUX identifiants :
- `id`   → PK locale SQLite uniquement, jamais envoyé à l'API
- `uuid` → identifiant global, utilisé dans l'API et la sync

## 3. TEMPLATE DE MIGRATION

```php
Schema::create('nom_table', function (Blueprint $table) {
    $table->id();
    $table->uuid('uuid')->unique();
    $table->unsignedBigInteger('entreprise_id');
    $table->unsignedBigInteger('succursale_id')->nullable();
    // colonnes métier ici
    $table->boolean('synced')->default(false);
    $table->string('device_id', 64)->nullable();
    $table->timestamps(); // updated_at obligatoire pour la sync

    $table->foreign('entreprise_id')->references('id')->on('entreprises')->onDelete('cascade');
    $table->foreign('succursale_id')->references('id')->on('succursales')->onDelete('set null');

    $table->index(['entreprise_id', 'succursale_id']);
    $table->index(['uuid']);
    $table->index(['synced', 'device_id']);
});
```

## 4. TEMPLATE DE MODÈLE

```php
class NomModele extends Model
{
    use HasUuid, SyncObservable, ScopeSuccursale;

    protected $fillable = [
        'uuid', 'entreprise_id', 'succursale_id',
        /* colonnes métier */
        'synced', 'device_id',
    ];

    protected $casts = [
        'synced'     => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function entreprise(): BelongsTo
    {
        return $this->belongsTo(Entreprise::class);
    }

    public function succursale(): BelongsTo
    {
        return $this->belongsTo(Succursale::class);
    }
}
```

## 5. EXCEPTION IMPORTANTE

Le modèle `SyncQueue` ne doit PAS avoir `SyncObservable` → boucle infinie.

## 6. CHECKLIST AVANT CHAQUE NOUVEAU MODÈLE

```
- [ ] uuid dans $fillable
- [ ] use HasUuid
- [ ] use SyncObservable
- [ ] use ScopeSuccursale
- [ ] succursale_id dans la migration
- [ ] synced + device_id dans la migration
- [ ] timestamps() présent
- [ ] Index sur (entreprise_id, succursale_id) et (uuid)
```

## 7. RÈGLES ABSOLUES

```
✓ uuid sur toutes les tables métier
✓ succursale_id sur toutes les tables métier
✓ entreprise_id sur toutes les tables métier
✓ updated_at sur toutes les tables (résolution conflits sync)
✓ Transactions DB::transaction() pour opérations multi-tables
✓ Eager loading with() systématique — zéro N+1 toléré
✓ Pagination sur toutes les listes API

✗ Jamais l'id local dans les payloads API (toujours uuid)
✗ Jamais ->get() sans pagination sur les listes
✗ Jamais SyncObservable sur SyncQueue
```

Schéma complet des 15 tables → voir REF_DATABASE.md
