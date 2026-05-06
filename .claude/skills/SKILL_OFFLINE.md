# Skill : Offline-First PrimeGest

Lire d'abord : docs/OFFLINE_FIRST_SPEC.md

## Règles à ne JAMAIS oublier

1. Tous les modèles doivent avoir HasUuid + SyncObservable
2. SyncQueue ne doit PAS avoir SyncObservable (boucle infinie)
3. Jamais d'id local dans les payloads sync — uuid uniquement
4. Le SyncWorker envoie par batch de 50 dans l'ordre chronologique
5. La résolution de conflit = Last-Write-Wins par updated_at
6. updated_at doit être présent sur TOUTES les tables
7. succursale_id et entreprise_id doit être présent sur TOUTES les tables métier

## Checklist avant chaque nouveau modèle

- [ ] uuid défini dans $fillable
- [ ] use HasUuid dans le modèle
- [ ] use SyncObservable dans le modèle
- [ ] succursale_id dans la migration
- [ ] synced et device_id dans la migration
- [ ] updated_at dans la migration
- [ ] Index sur (entreprise_id, succursale_id, uuid)

## Colonnes système obligatoires sur toutes les tables métier
uuid, entreprise_id, succursale_id, synced, device_id, created_at, updated_at