# ═══════════════════════════════════════════════════════════════════
# PROMPT AUDIT COMPLET — CLAUDE CODE / PRIMEGEST
# Analyse de sécurité, vulnérabilités, qualité et performance
# Coller dans le terminal Claude Code — ne modifier que la section
# PÉRIMÈTRE si vous voulez limiter l'analyse à un module précis
# ═══════════════════════════════════════════════════════════════════

Tu es un expert en sécurité et en audit de code Laravel + Vue.js.
Tu vas effectuer un audit complet du projet PrimeGest.

Avant de commencer, lis ces fichiers :
1. CLAUDE.md
2. .claude/skills/SKILL_DATABASE.md
3. .claude/skills/SKILL_API.md
4. .claude/skills/SKILL_SECURITY.md

---

## MISSION

Analyser l'intégralité du projet PrimeGest et produire un rapport
structuré couvrant : sécurité, vulnérabilités, qualité du code,
performance, et conformité aux conventions du projet.

---

## PÉRIMÈTRE D'ANALYSE

Analyser dans cet ordre :

### 1. Backend Laravel
- Tous les fichiers dans app/Http/Controllers/
- Tous les fichiers dans app/Http/Middleware/
- Tous les fichiers dans app/Models/
- Tous les fichiers dans app/Actions/
- Tous les fichiers dans app/Services/
- Tous les fichiers dans routes/api.php et routes/web.php
- Tous les fichiers dans database/migrations/
- Les fichiers config/ sensibles : auth.php, database.php, cors.php
- Le fichier .env.example (jamais .env)

### 2. Frontend Vue.js
- Tous les fichiers dans resources/js/pages/
- Tous les fichiers dans resources/js/components/
- Tous les fichiers dans resources/js/stores/
- Tous les fichiers dans resources/js/composables/

### 3. Configuration
- composer.json et package.json (dépendances)
- vite.config.ts
- Fichiers de configuration Nginx si présents

---

## GRILLE D'ANALYSE COMPLÈTE

Pour chaque fichier analysé, vérifier ces points :

### SÉCURITÉ CRITIQUE (priorité 1 — corriger immédiatement)

```
AUTHENTIFICATION & AUTORISATION
□ Toutes les routes API ont-elles le middleware auth:sanctum ?
□ Le middleware entreprise est-il appliqué partout ?
□ Le middleware succursale.scope est-il sur toutes les routes v1 ?
□ Un manager peut-il accéder aux données d'une autre succursale ?
□ Les tokens Sanctum ont-ils une expiration configurée ?
□ Le rate limiting est-il appliqué sur /login et /api ?

INJECTION & VALIDATION
□ Y a-t-il des requêtes SQL brutes non paramétrées ?
□ $request->all() est-il utilisé (risque mass assignment) ?
□ Les Form Requests valident-ils toutes les entrées utilisateur ?
□ Les fichiers uploadés sont-ils validés (type, taille, extension) ?
□ Y a-t-il des risques d'injection dans les noms de fichiers ?

DONNÉES SENSIBLES
□ Des mots de passe ou tokens sont-ils dans les logs ?
□ Des données sensibles sont-elles retournées dans les API responses ?
□ L'id local est-il exposé dans les réponses API (doit être uuid) ?
□ APP_DEBUG est-il false en production ?
□ Des clés API sont-elles hardcodées dans le code ?

TRANSFERTS INTER-SUCCURSALES
□ La confirmation par mot de passe est-elle vérifiée côté serveur ?
□ Hash::check() est-il utilisé (jamais comparaison directe) ?
□ Le mot de passe est-il transmis via HTTPS uniquement ?

XSS & CSRF
□ Les données utilisateur sont-elles échappées dans les vues Blade ?
□ La protection CSRF est-elle active sur les routes web ?
□ Vue.js utilise-t-il v-html avec des données utilisateur ?
```

### SÉCURITÉ IMPORTANTE (priorité 2 — corriger rapidement)

```
FREEMIUM & PLANS
□ Le middleware plan.limit est-il sur toutes les routes Freemium ?
□ Les limites sont-elles vérifiées côté serveur (pas seulement UI) ?
□ Un utilisateur Free peut-il contourner les limites via l'API ?
□ L'expiration du plan Premium est-elle vérifiée à chaque requête ?

OFFLINE & SYNC
□ Le checksum SHA256 est-il vérifié côté cloud avant insertion ?
□ SyncObservable est-il absent du modèle SyncQueue ?
□ L'id local est-il exclu des payloads de synchronisation ?
□ Les conflits de sync sont-ils loggés dans conflict_log ?

GESTION DES ERREURS
□ Les messages d'erreur exposent-ils des détails techniques ?
□ Les exceptions sont-elles toutes catchées proprement ?
□ Les stack traces sont-elles masquées en production ?
```

### QUALITÉ DU CODE (priorité 3)

```
CONVENTIONS PRIMEGEST
□ "branch" ou "company" utilisés au lieu de "succursale"/"entreprise" ?
□ Tous les modèles ont-ils HasUuid + SyncObservable + ScopeSuccursale ?
□ Y a-t-il de la logique dans les contrôleurs (doit être dans Actions) ?
□ response()->json() utilisé directement (doit être ApiResponse) ?
□ ->toArray() utilisé directement (doit être API Resource) ?
□ $request->all() utilisé (doit être $request->validated()) ?

BASE DE DONNÉES
□ Des tables manquent-elles uuid, succursale_id, synced, device_id ?
□ Des tables manquent-elles updated_at (critique pour la sync) ?
□ Des index manquent-ils sur entreprise_id, succursale_id, uuid ?
□ Des migrations n'ont-elles pas de méthode down() ?
□ Des requêtes N+1 sont-elles détectables ?

VUE.JS
□ Options API utilisée au lieu de Composition API + script setup ?
□ Des styles inline utilisés (interdit, Tailwind uniquement) ?
□ Des console.log() laissés dans le code ?
□ Les props sont-elles typées avec defineProps<> ?
□ planStore.canUse() est-il vérifié avant les actions Premium ?
```

### PERFORMANCE (priorité 4)

```
□ Des requêtes sans index sur les colonnes WHERE/ORDER BY ?
□ SELECT * utilisé sur de grandes tables ?
□ ->all() ou ->get() sans pagination sur les listes API ?
□ Des calculs PHP qui pourraient être des agrégations SQL ?
□ Le cache est-il utilisé pour le dashboard et les données statiques ?
□ Les composants Vue.js sont-ils chargés en lazy loading ?
□ Des dépendances npm inutiles dans package.json ?
□ Des packages Composer inutiles ou obsolètes ?
```

---

## FORMAT DU RAPPORT À PRODUIRE

Produis un rapport Markdown structuré exactement ainsi :

```markdown
# RAPPORT D'AUDIT PRIMEGEST
Date : [date]
Fichiers analysés : [nombre]

## RÉSUMÉ EXÉCUTIF
Score global : X/100
[2-3 phrases de synthèse]

## STATISTIQUES
| Catégorie        | Critique | Important | Mineur |
|------------------|----------|-----------|--------|
| Sécurité         | X        | X         | X      |
| Qualité code     | X        | X         | X      |
| Performance      | X        | X         | X      |
| Conventions      | X        | X         | X      |
| TOTAL            | X        | X         | X      |

---

## 🔴 PROBLÈMES CRITIQUES (corriger immédiatement)

### [CRITIQUE-001] Titre du problème
- **Fichier** : app/Http/Controllers/VenteController.php (ligne X)
- **Problème** : Description précise du problème
- **Risque** : Ce que ça permet à un attaquant / utilisateur malveillant
- **Code actuel** :
  ```php
  // code problématique
  ```
- **Correction** :
  ```php
  // code corrigé
  ```

[Répéter pour chaque problème critique]

---

## 🟠 PROBLÈMES IMPORTANTS

### [IMPORTANT-001] Titre
- **Fichier** : ...
- **Problème** : ...
- **Correction** : ...

[Répéter pour chaque problème important]

---

## 🟡 PROBLÈMES MINEURS

### [MINEUR-001] Titre
- **Fichier** : ...
- **Problème** : ...
- **Correction** : ...

---

## ✅ POINTS POSITIFS
[Ce qui est bien fait dans le projet]

---

## 📋 PLAN D'ACTION PRIORISÉ

### Semaine 1 — Critique
- [ ] [CRITIQUE-001] Description courte
- [ ] [CRITIQUE-002] Description courte

### Semaine 2 — Important
- [ ] [IMPORTANT-001] Description courte

### Semaine 3 — Mineur et optimisations
- [ ] [MINEUR-001] Description courte

---

## 🔧 CORRECTIONS PRÊTES À APPLIQUER
[Pour chaque problème critique, fournir le fichier corrigé complet
prêt à remplacer — pas juste un extrait]
```

---

## INSTRUCTIONS DE TRAVAIL

1. Commence par lire TOUS les fichiers du périmètre avant de rédiger
2. Ne saute aucune vérification de la grille d'analyse
3. Pour chaque problème trouvé, donne TOUJOURS le fichier exact et la ligne
4. Pour les problèmes critiques, fournis le code corrigé complet
5. Si un fichier est trop long, analyse-le par sections
6. Signale si un fichier attendu est absent (ex: middleware manquant)
7. Vérifie les dépendances dans composer.json pour les CVE connues
8. À la fin, demande-moi si je veux que tu appliques les corrections
   critiques une par une avec confirmation à chaque étape

Commence l'analyse maintenant.
