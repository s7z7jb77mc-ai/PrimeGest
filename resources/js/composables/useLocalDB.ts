/**
 * useLocalDB — Interface TypeScript vers les tables SQLite locales (Tauri).
 *
 * Utilisé par les pages Vue pour lire des données en mode offline.
 * Si pas dans Tauri, retourne un tableau vide (les pages lisent via Inertia normalement).
 */

const isTauri = typeof window !== 'undefined' && '__TAURI_INTERNALS__' in window

async function tauriInvoke<T>(cmd: string, args: Record<string, unknown> = {}): Promise<T> {
    const { invoke } = await import('@tauri-apps/api/core')
    return invoke<T>(cmd, args)
}

// ── Types des entités locales ──────────────────────────────────────────────────

export interface ProduitLocal {
    uuid: string
    nom: string
    prix_vente: number
    prix_achat: number
    quantite: number
    categorie: string | null
    sync_version: number
    updated_at: number
    deleted_at: number | null
}

export interface ClientLocal {
    uuid: string
    nom_client: string
    numero_telephone: string | null
    adresse: string | null
    sync_version: number
    updated_at: number
    deleted_at: number | null
}

export interface FournisseurLocal {
    uuid: string
    nom_entreprise_fournisseur: string
    adresse: string | null
    reduction_pourcentage: number
    sync_version: number
    updated_at: number
    deleted_at: number | null
}

export interface FactureLocal {
    uuid: string
    total_ht: number
    total_tva: number
    total_ttc: number
    montant_paye: number
    statut: 'en_attente' | 'payee' | 'annulee'
    client_id: number | null
    sync_version: number
    updated_at: number
    deleted_at: number | null
}

export interface MouvementStockLocal {
    uuid: string
    type: 'entree' | 'sortie'
    quantite: number
    prix_unitaire: number | null
    prix_total: number | null
    commentaire: string | null
    produit_id: number | null
    sync_version: number
    updated_at: number
    deleted_at: number | null
}

export interface JournalLocal {
    uuid: string
    type: 'entree' | 'sortie'
    description: string | null
    montant: number
    dateHeure_operation: string | null
    produit_id: number | null
    sync_version: number
    updated_at: number
    deleted_at: number | null
}

export interface EmployeLocal {
    uuid: string
    nom: string
    prenom: string | null
    poste: string | null
    salaire_base: number
    telephone: string | null
    email: string | null
    date_embauche: string | null
    statut: string
    sync_version: number
    updated_at: number
    deleted_at: number | null
}

export interface SuccursaleLocal {
    uuid: string
    nom: string
    adresse: string | null
    manager_user_id: number | null
    active: boolean
    sync_version: number
    updated_at: number
    deleted_at: number | null
}

export interface CaisseLocal {
    uuid: string
    date_operation: string | null
    description: string | null
    entree: number
    sortie: number
    solde: number | null
    type_operation: string | null
    succursale_id: number | null
    sync_version: number
    updated_at: number
    deleted_at: number | null
}

export interface ParametreLocal {
    uuid: string
    nom_entreprise: string | null
    adresse: string | null
    email: string | null
    telephone: string | null
    devise: string | null
    langue: string | null
    rccm: string | null
    identifiant_national: string | null
    numero_impot: string | null
    tva: number
    reduction_accordee: number
    theme: string | null
    message_remerciement: string | null
    multi_succursales: boolean
    seuil_alerte: number | null
    logo_path: string | null
    logo_position: string | null
    sync_version: number
    updated_at: number
    deleted_at: number | null
}

// ── Options de requête ─────────────────────────────────────────────────────────

export interface QueryOptions {
    search?: string
    limit?: number
    offset?: number
    includeDeleted?: boolean
}

// ── Lecture Tauri (SQLite via invoke) ─────────────────────────────────────────

async function queryLocal<T>(entity: string, opts: QueryOptions = {}): Promise<T[]> {
    if (!isTauri) return []
    try {
        const jsonStrings = await tauriInvoke<string[]>('query_local', {
            entity,
            search: opts.search ?? null,
            limit: opts.limit ?? 50,
            offset: opts.offset ?? 0,
            includeDeleted: opts.includeDeleted ?? false,
        })
        return jsonStrings.map((s) => JSON.parse(s) as T)
    } catch (e) {
        console.warn(`[LocalDB] Erreur query_local(${entity}):`, e)
        return []
    }
}

async function queryAggregate(entity: string, aggregate: string): Promise<Record<string, number>> {
    if (!isTauri) return {}
    try {
        return await tauriInvoke<Record<string, number>>('query_local_aggregate', {
            entity,
            aggregate,
        })
    } catch (e) {
        console.warn(`[LocalDB] Erreur aggregate(${entity}/${aggregate}):`, e)
        return {}
    }
}

// ── Lecture Dexie (IndexedDB web) ─────────────────────────────────────────────

async function dexieQuery<T>(table: any, opts: QueryOptions = {}): Promise<T[]> {
    try {
        let collection = table.filter((row: any) => !row.deleted_at)
        const all: T[] = await collection.toArray()
        const limit = opts.limit ?? 200
        const offset = opts.offset ?? 0
        let result = all.slice(offset, offset + limit)
        if (opts.search) {
            const q = opts.search.toLowerCase()
            result = result.filter((row: any) =>
                Object.values(row).some(v => String(v ?? '').toLowerCase().includes(q))
            )
        }
        return result
    } catch (e) {
        console.warn('[LocalDB] Erreur Dexie:', e)
        return []
    }
}

// ── API publique ───────────────────────────────────────────────────────────────

export function useLocalDB() {
    // Disponible dans Tauri ET dans le navigateur web (via Dexie)
    const isAvailable = true

    if (isTauri) {
        return {
            isAvailable,
            getProduits:       (opts?: QueryOptions) => queryLocal<ProduitLocal>('produits', opts),
            getClients:        (opts?: QueryOptions) => queryLocal<ClientLocal>('clients', opts),
            getFournisseurs:   (opts?: QueryOptions) => queryLocal<FournisseurLocal>('fournisseurs', opts),
            getFactures:       (opts?: QueryOptions) => queryLocal<FactureLocal>('factures', opts),
            getMouvementsStock:(opts?: QueryOptions) => queryLocal<MouvementStockLocal>('mouvement_stocks', opts),
            getJournals:       (opts?: QueryOptions) => queryLocal<JournalLocal>('journals', opts),
            getEmployes:       (opts?: QueryOptions) => queryLocal<EmployeLocal>('employes', opts),
            getSuccursales:    (opts?: QueryOptions) => queryLocal<SuccursaleLocal>('succursales', opts),
            getCaisses:        (opts?: QueryOptions) => queryLocal<CaisseLocal>('caisses', opts),
            getParametres:     (opts?: QueryOptions) => queryLocal<ParametreLocal>('parametres', opts),
            getTransferts:     (opts?: QueryOptions) => queryLocal<any>('transferts', opts),
            getProduitsCount:     () => queryAggregate('produits', 'count'),
            getValeurStock:       () => queryAggregate('produits', 'valeur_stock'),
            getFacturesTotaux:    () => queryAggregate('factures', 'totaux'),
            getJournalSumByType:  () => queryAggregate('journals', 'sum_by_type'),
            getMouvementsSumByType:() => queryAggregate('mouvement_stocks', 'sum_by_type'),
            getClientsCount:      () => queryAggregate('clients', 'count'),
            getEmployesCount:     () => queryAggregate('employes', 'count'),
        }
    }

    // Web PWA — lecture depuis Dexie
    // Import dynamique pour éviter que Dexie soit bundlé dans le SW
    const getDexieDB = () => import('@/db/primegest').then(m => m.db)

    return {
        isAvailable,
        getProduits:       async (opts?: QueryOptions) => dexieQuery<ProduitLocal>((await getDexieDB()).produits, opts),
        getClients:        async (opts?: QueryOptions) => dexieQuery<ClientLocal>((await getDexieDB()).clients, opts),
        getFournisseurs:   async (opts?: QueryOptions) => dexieQuery<FournisseurLocal>((await getDexieDB()).fournisseurs, opts),
        getFactures:       async (_opts?: QueryOptions) => [] as FactureLocal[],
        getMouvementsStock:async (opts?: QueryOptions) => dexieQuery<MouvementStockLocal>((await getDexieDB()).mouvement_stocks, opts),
        getJournals:       async (opts?: QueryOptions) => dexieQuery<JournalLocal>((await getDexieDB()).journals, opts),
        getEmployes:       async (_opts?: QueryOptions) => [] as EmployeLocal[],
        getSuccursales:    async (opts?: QueryOptions) => dexieQuery<SuccursaleLocal>((await getDexieDB()).succursales, opts),
        getCaisses:        async (opts?: QueryOptions) => dexieQuery<CaisseLocal>((await getDexieDB()).caisses, opts),
        getParametres:     async (_opts?: QueryOptions) => [] as ParametreLocal[],
        getTransferts:     async (opts?: QueryOptions) => dexieQuery<any>((await getDexieDB()).transferts, opts),
        getProduitsCount:      async () => ({} as Record<string, number>),
        getValeurStock:        async () => ({} as Record<string, number>),
        getFacturesTotaux:     async () => ({} as Record<string, number>),
        getJournalSumByType:   async () => ({} as Record<string, number>),
        getMouvementsSumByType:async () => ({} as Record<string, number>),
        getClientsCount:       async () => ({} as Record<string, number>),
        getEmployesCount:      async () => ({} as Record<string, number>),
    }
}
