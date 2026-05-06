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
    nom_produit: string
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

// ── Options de requête ─────────────────────────────────────────────────────────

export interface QueryOptions {
    search?: string
    limit?: number
    offset?: number
    includeDeleted?: boolean
}

// ── Fonction générique de lecture ──────────────────────────────────────────────

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

// ── Agrégats ───────────────────────────────────────────────────────────────────

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

// ── API publique ───────────────────────────────────────────────────────────────

export function useLocalDB() {
    return {
        /** Indique si on est dans Tauri (local DB disponible) */
        isAvailable: isTauri,

        // Lectures typées par entité
        getProduits: (opts?: QueryOptions) => queryLocal<ProduitLocal>('produits', opts),
        getClients: (opts?: QueryOptions) => queryLocal<ClientLocal>('clients', opts),
        getFournisseurs: (opts?: QueryOptions) => queryLocal<FournisseurLocal>('fournisseurs', opts),
        getFactures: (opts?: QueryOptions) => queryLocal<FactureLocal>('factures', opts),
        getMouvementsStock: (opts?: QueryOptions) => queryLocal<MouvementStockLocal>('mouvement_stocks', opts),
        getJournals: (opts?: QueryOptions) => queryLocal<JournalLocal>('journals', opts),
        getEmployes: (opts?: QueryOptions) => queryLocal<EmployeLocal>('employes', opts),

        // Agrégats pour le dashboard
        getProduitsCount: () => queryAggregate('produits', 'count'),
        getValeurStock: () => queryAggregate('produits', 'valeur_stock'),
        getFacturesTotaux: () => queryAggregate('factures', 'totaux'),
        getJournalSumByType: () => queryAggregate('journals', 'sum_by_type'),
        getMouvementsSumByType: () => queryAggregate('mouvement_stocks', 'sum_by_type'),
        getClientsCount: () => queryAggregate('clients', 'count'),
        getEmployesCount: () => queryAggregate('employes', 'count'),
    }
}
