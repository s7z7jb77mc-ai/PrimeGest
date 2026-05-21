import Dexie, { type Table } from 'dexie'

// ── Types ──────────────────────────────────────────────────────────────────

export interface ProduitRow {
    uuid: string
    nom: string
    prix_vente: number
    prix_achat: number
    quantite?: number
    categorie?: string | null
    seuil_stock?: number | null
    updated_at: number  // timestamp unix
    deleted_at?: number | null
    synced?: boolean
    [key: string]: any
}

export interface ClientRow {
    uuid: string
    nom_client: string
    numero_telephone?: string | null
    adresse?: string | null
    updated_at: number
    deleted_at?: number | null
    [key: string]: any
}

export interface FournisseurRow {
    uuid: string
    nom_entreprise_fournisseur: string
    adresse?: string | null
    reduction_pourcentage?: number
    updated_at: number
    deleted_at?: number | null
    [key: string]: any
}

export interface CaisseRow {
    uuid: string
    date_operation?: string | null
    description?: string | null
    entree: number
    sortie: number
    solde?: number | null
    type_operation?: string | null
    succursale_id?: number | null
    updated_at: number
    deleted_at?: number | null
    [key: string]: any
}

export interface JournalRow {
    uuid: string
    type?: string
    description?: string | null
    montant: number
    dateHeure_operation?: string | null
    produit_id?: number | null
    succursale_id?: number | null
    updated_at: number
    deleted_at?: number | null
    [key: string]: any
}

export interface MouvementStockRow {
    uuid: string
    type?: string
    quantite: number
    prix_unitaire?: number | null
    prix_total?: number | null
    commentaire?: string | null
    nom_produit?: string | null
    produit_id?: number | null
    succursale_id?: number | null
    updated_at: number
    deleted_at?: number | null
    [key: string]: any
}

export interface TransfertRow {
    uuid: string
    type?: string
    montant?: number | null
    statut?: string | null
    from_succursale_id?: number | null
    to_succursale_id?: number | null
    updated_at: number
    deleted_at?: number | null
    [key: string]: any
}

export interface SuccursaleRow {
    uuid: string
    nom: string
    adresse?: string | null
    updated_at: number
    deleted_at?: number | null
    [key: string]: any
}

export interface SyncQueueItem {
    id?: number
    table_name: string
    record_id: string
    operation: 'create' | 'update' | 'delete'
    payload: string        // JSON stringifié
    status: 'pending' | 'synced' | 'error'
    client_sync_version: number
    created_at: number
}

export interface SyncMeta {
    table_name: string    // clé primaire
    last_sync_at: number  // timestamp unix
}

// ── Base Dexie ─────────────────────────────────────────────────────────────

class PrimeGestDB extends Dexie {
    produits!: Table<ProduitRow, string>
    clients!: Table<ClientRow, string>
    fournisseurs!: Table<FournisseurRow, string>
    caisses!: Table<CaisseRow, string>
    journals!: Table<JournalRow, string>
    mouvement_stocks!: Table<MouvementStockRow, string>
    transferts!: Table<TransfertRow, string>
    succursales!: Table<SuccursaleRow, string>
    sync_queue!: Table<SyncQueueItem, number>
    sync_meta!: Table<SyncMeta, string>

    constructor() {
        super('primegest_offline')

        // v1 : sync_queue seul (existant — raw IDB, migré automatiquement par Dexie)
        this.version(1).stores({
            sync_queue: '++id, status',
        })

        // v2 : ajout de toutes les tables de données
        this.version(2).stores({
            sync_queue:      '++id, status, table_name, created_at',
            sync_meta:       'table_name',
            produits:        'uuid, updated_at, deleted_at',
            clients:         'uuid, updated_at, deleted_at',
            fournisseurs:    'uuid, updated_at, deleted_at',
            caisses:         'uuid, date_operation, succursale_id, updated_at',
            journals:        'uuid, dateHeure_operation, succursale_id, updated_at',
            mouvement_stocks:'uuid, produit_id, succursale_id, updated_at',
            transferts:      'uuid, statut, updated_at',
            succursales:     'uuid, updated_at',
        })
    }
}

export const db = new PrimeGestDB()
