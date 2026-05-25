# PWA Offline-First — Spec Design
*Date : 2026-05-21 — Auteur : Trystan Byabuze*

---

## 1. Contexte et objectif

PrimeGest doit fonctionner sans réseau sur mobile (Chrome/Safari) pour 6 modules critiques :
**caisse, mouvement-stock, produits, journal, tiers (clients + fournisseurs), transfert/succursales**.

L'utilisateur ouvre `primegest.app` → ajoute à l'écran d'accueil → utilise l'app en plein écran, même hors ligne.

### Existant (à conserver)
- `public/sw.js` — Service Worker cache assets + pages Inertia (stale-while-revalidate)
- `public/manifest.webmanifest` — manifest installable
- `stores/useOfflineStore.ts` — état online/offline/sync
- `composables/useOfflineQueue.ts` — queue IndexedDB pour mutations en attente
- `composables/useLocalDB.ts` — lecture locale Tauri (SQLite via invoke)
- API `/api/sync/pull` et `/api/sync/push` opérationnelles

### Ce qui manque
- **Dexie.js** : base IndexedDB structurée pour stocker les données des 6 modules côté web
- **Lecture offline web** : `useLocalDB` renvoie `[]` si pas Tauri — à corriger avec Dexie
- **Sync pull initial** : peupler Dexie au premier login et à chaque retour online
- **Écriture offline** : écrire dans Dexie + queue, pousser au retour réseau

---

## 2. Architecture

```
┌─────────────────────────────────────────────────────┐
│                   Pages Vue.js                      │
│  useOfflineData(module)                             │
│  ┌─────────────────────────────────────────────┐   │
│  │  si online  → axios API  +  écriture Dexie  │   │
│  │  si offline → lecture Dexie + queue mutation │   │
│  └─────────────────────────────────────────────┘   │
└──────────────────┬──────────────────────────────────┘
                   │
        ┌──────────▼──────────┐
        │   PrimeGestDB       │  ← Dexie.js (IndexedDB)
        │  (db/primegest.ts)  │
        │  tables: produits,  │
        │  clients, fournisseurs,│
        │  caisses, journals, │
        │  mouvement_stocks,  │
        │  transferts,        │
        │  succursales,       │
        │  sync_queue         │
        └──────────┬──────────┘
                   │ online?
        ┌──────────▼──────────┐
        │  useSyncManager.ts  │  ← orchestrateur pull/push
        │  pull: /api/sync/pull│
        │  push: /api/sync/push│
        └─────────────────────┘
```

---

## 3. Base de données Dexie — `resources/js/db/primegest.ts`

```typescript
class PrimeGestDB extends Dexie {
  produits!: Table<ProduitLocal>
  clients!: Table<ClientLocal>
  fournisseurs!: Table<FournisseurLocal>
  caisses!: Table<CaisseLocal>
  journals!: Table<JournalLocal>
  mouvement_stocks!: Table<MouvementStockLocal>
  transferts!: Table<TransfertLocal>
  succursales!: Table<SuccursaleLocal>
  sync_queue!: Table<SyncQueueItem>  // mutations en attente
  sync_meta!: Table<SyncMeta>        // last_sync_at par table
}
```

Index Dexie par table :
- `produits` → `uuid, updated_at, deleted_at`
- `clients, fournisseurs` → `uuid, updated_at, deleted_at`
- `caisses` → `uuid, date_operation, succursale_id, updated_at`
- `journals` → `uuid, dateHeure_operation, succursale_id, updated_at`
- `mouvement_stocks` → `uuid, produit_id, succursale_id, updated_at`
- `transferts` → `uuid, statut, updated_at`
- `succursales` → `uuid, updated_at`
- `sync_queue` → `++id, status, table_name, created_at`
- `sync_meta` → `table_name` (clé primaire)

---

## 4. Composable `useOfflineData` — API unifiée par module

```typescript
// Remplace les appels axios dans les pages pour les 6 modules
const { items, loading, create, update } = useOfflineData('produits')
```

Comportement :
- **online** → `axios.get()` + mise à jour Dexie en arrière-plan
- **offline** → `db.produits.toArray()` depuis Dexie
- `create/update` → écrit dans Dexie + enqueue dans `sync_queue` si offline

---

## 5. Sync Manager — `useSyncManager.ts`

### Pull (serveur → Dexie)
Appelé : au login, au retour online, toutes les 5 min si online

```
GET /api/sync/pull?since={last_sync_unix}&device_id={id}
→ reçoit delta par table
→ upsert dans Dexie par uuid
→ met à jour sync_meta.last_sync_at
```

### Push (Dexie queue → serveur)
Appelé : au retour online, après chaque écriture offline

```
sync_queue WHERE status='pending'
→ POST /api/sync/push { operations: [...] }
→ marquer synced dans sync_queue
```

### Résolution conflit
Last-Write-Wins par `updated_at` — identique au desktop.

---

## 6. Service Worker — modifications `public/sw.js`

Ajout d'une stratégie pour les appels API GET des 6 modules :

```
GET /api/* → NetworkFirst avec timeout 3s
  → si réseau KO → répondre 503 JSON { offline: true }
  → le composable useOfflineData bascule sur Dexie
```

Les mutations (POST/PUT/DELETE) ne sont pas interceptées par le SW —
elles sont gérées côté app (`useOfflineData` → `sync_queue` Dexie).

---

## 7. Modules concernés et pages à modifier

| Module | Page | Données offline |
|---|---|---|
| Produits | `/produits` | liste + recherche |
| Caisse | `/caisse` | liste + ajout entrée/sortie |
| Mouvement-stock | `/mouvement-stocks` | liste + nouvelle vente/achat |
| Journal | `/journals` | liste du jour |
| Tiers | `/tiers` | clients + fournisseurs |
| Transfert | `/transferts` | liste |
| Succursales | `/succursales` | liste |

---

## 8. Indicateur visuel

Composant `SyncIndicator.vue` existant — à brancher sur `useOfflineStore` :
- 🟢 En ligne + synchronisé
- 🟡 En ligne + X opérations en attente
- 🔴 Hors ligne — données locales

---

## 9. Plan d'implémentation (ordre)

1. `npm install dexie` + créer `db/primegest.ts`
2. Migrer `useOfflineQueue.ts` → utiliser Dexie `sync_queue` (supprimer raw IDB)
3. Créer `useSyncManager.ts` (pull + push)
4. Créer `useOfflineData.ts` (lecture/écriture unifiée)
5. Modifier SW pour API NetworkFirst
6. Brancher les 7 pages sur `useOfflineData`
7. Pull initial au login

---

## 10. Ce qui ne change pas

- `useLocalDB.ts` (Tauri) — conservé tel quel pour le desktop
- `manifest.webmanifest` — déjà correct
- Structure de l'API sync — `/api/sync/pull` et `/api/sync/push` inchangées
- `useOfflineStore.ts` — conservé, juste branché sur `useSyncManager`
