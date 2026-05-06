---
name: gen-migration
description: Generate Laravel migration + factory + model relationship for a new table
disable-model-invocation: true
---

## Objectif
Générer en une seule action : la migration Artisan, le factory, et le boilerplate de relation dans le modèle.

## Étapes
1. Créer `database/migrations/YYYY_MM_DD_HHMMSS_create_{table}_table.php`
2. Créer `database/factories/{Model}Factory.php`
3. Ajouter la relation `hasMany` / `belongsTo` dans les modèles concernés
4. Respecter le scoping `entreprise_id` sur toutes les tables tenant-owned

## Contraintes
- Toujours inclure `entreprise_id` (foreign key) sur les tables métier
- Utiliser `$table->foreignId('entreprise_id')->constrained()->cascadeOnDelete()`
- Le factory doit utiliser `Entreprise::factory()` pour l'association