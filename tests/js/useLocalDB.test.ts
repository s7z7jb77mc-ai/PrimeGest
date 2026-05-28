/**
 * Tests de la couche de lecture locale (useLocalDB) — chemin web/mobile (Dexie).
 *
 * En environnement Vitest (jsdom), __TAURI_INTERNALS__ n'est pas défini,
 * donc useLocalDB utilise automatiquement le chemin Dexie (IndexedDB).
 */
import { beforeEach, afterEach, describe, expect, it } from 'vitest'
import { db } from '@/db/primegest'

// ── setup / teardown ──────────────────────────────────────────────────────

beforeEach(async () => {
    await db.clients.clear()
    await db.produits.clear()
    await db.fournisseurs.clear()
    await db.caisses.clear()
    await db.succursales.clear()
    await db.transferts.clear()
    await db.stocks.clear()
    await db.journals.clear()
})

afterEach(async () => {
    await db.clients.clear()
    await db.produits.clear()
    await db.fournisseurs.clear()
    await db.caisses.clear()
    await db.stocks.clear()
    await db.journals.clear()
})

// ── helpers ───────────────────────────────────────────────────────────────

const now = Math.floor(Date.now() / 1000)

function makeClient(overrides: Record<string, any> = {}) {
    return {
        uuid:             crypto.randomUUID(),
        nom_client:       'Client Test',
        numero_telephone: '0999000001',
        adresse:          'Kinshasa',
        updated_at:       now,
        deleted_at:       null,
        ...overrides,
    }
}

function makeProduit(overrides: Record<string, any> = {}) {
    return {
        uuid:        crypto.randomUUID(),
        nom:         'Aspirine 500mg',
        prix_vente:  1000,
        prix_achat:  500,
        quantite:    100,
        updated_at:  now,
        deleted_at:  null,
        ...overrides,
    }
}

function makeFournisseur(overrides: Record<string, any> = {}) {
    return {
        uuid:                       crypto.randomUUID(),
        nom_entreprise_fournisseur: 'Pharma SARL',
        adresse:                    'Goma',
        reduction_pourcentage:      5,
        updated_at:                 now,
        deleted_at:                 null,
        ...overrides,
    }
}

// ─────────────────────────────────────────────────────────────────────────
// getClients — lecture web (Dexie)
// ─────────────────────────────────────────────────────────────────────────

describe('getClients — web (Dexie)', () => {
    it('retourne les enregistrements non-supprimés', async () => {
        const { useLocalDB } = await import('@/composables/useLocalDB')
        await db.clients.bulkPut([
            makeClient({ nom_client: 'Alice' }),
            makeClient({ nom_client: 'Bob' }),
        ])

        const results = await useLocalDB().getClients()
        expect(results).toHaveLength(2)
    })

    it('exclut les enregistrements avec deleted_at non null', async () => {
        const { useLocalDB } = await import('@/composables/useLocalDB')
        await db.clients.bulkPut([
            makeClient({ nom_client: 'Actif' }),
            makeClient({ nom_client: 'Supprimé', deleted_at: now - 3600 }),
        ])

        const results = await useLocalDB().getClients()
        const noms = results.map((c: any) => c.nom_client)
        expect(noms).toContain('Actif')
        expect(noms).not.toContain('Supprimé')
    })

    it('filtre par search (insensible à la casse)', async () => {
        const { useLocalDB } = await import('@/composables/useLocalDB')
        await db.clients.bulkPut([
            makeClient({ nom_client: 'Jean Dupont' }),
            makeClient({ nom_client: 'Marie Curie' }),
        ])

        const results = await useLocalDB().getClients({ search: 'jean' })
        expect(results).toHaveLength(1)
        expect((results[0] as any).nom_client).toBe('Jean Dupont')
    })

    it('respecte le paramètre limit', async () => {
        const { useLocalDB } = await import('@/composables/useLocalDB')
        await db.clients.bulkPut([
            makeClient({ nom_client: 'C1' }),
            makeClient({ nom_client: 'C2' }),
            makeClient({ nom_client: 'C3' }),
        ])

        const results = await useLocalDB().getClients({ limit: 2 })
        expect(results).toHaveLength(2)
    })

    it('respecte le paramètre offset', async () => {
        const { useLocalDB } = await import('@/composables/useLocalDB')
        await db.clients.bulkPut([
            makeClient({ nom_client: 'Premier' }),
            makeClient({ nom_client: 'Deuxième' }),
            makeClient({ nom_client: 'Troisième' }),
        ])

        const results = await useLocalDB().getClients({ limit: 10, offset: 1 })
        expect(results).toHaveLength(2)
    })

    it('retourne un tableau vide si la table est vide', async () => {
        const { useLocalDB } = await import('@/composables/useLocalDB')
        const results = await useLocalDB().getClients()
        expect(results).toEqual([])
    })
})

// ─────────────────────────────────────────────────────────────────────────
// getProduits — lecture web (Dexie)
// ─────────────────────────────────────────────────────────────────────────

describe('getProduits — web (Dexie)', () => {
    it('retourne les produits non-supprimés', async () => {
        const { useLocalDB } = await import('@/composables/useLocalDB')
        await db.produits.bulkPut([
            makeProduit({ nom: 'Paracétamol' }),
            makeProduit({ nom: 'Ibuprofène', deleted_at: now - 100 }),
        ])

        const results = await useLocalDB().getProduits()
        const noms = results.map((p: any) => p.nom)
        expect(noms).toContain('Paracétamol')
        expect(noms).not.toContain('Ibuprofène')
    })

    it('retourne les champs attendus', async () => {
        const { useLocalDB } = await import('@/composables/useLocalDB')
        await db.produits.put(makeProduit({ nom: 'Aspirine', prix_vente: 1500, prix_achat: 700 }))

        const results = await useLocalDB().getProduits()
        expect(results[0]).toMatchObject({ nom: 'Aspirine', prix_vente: 1500, prix_achat: 700 })
    })
})

// ─────────────────────────────────────────────────────────────────────────
// getFournisseurs — lecture web (Dexie)
// ─────────────────────────────────────────────────────────────────────────

describe('getFournisseurs — web (Dexie)', () => {
    it('retourne les fournisseurs non-supprimés', async () => {
        const { useLocalDB } = await import('@/composables/useLocalDB')
        await db.fournisseurs.bulkPut([
            makeFournisseur({ nom_entreprise_fournisseur: 'Pharma SARL' }),
            makeFournisseur({ nom_entreprise_fournisseur: 'Supprimé SARL', deleted_at: now - 1 }),
        ])

        const results = await useLocalDB().getFournisseurs()
        const noms = results.map((f: any) => f.nom_entreprise_fournisseur)
        expect(noms).toContain('Pharma SARL')
        expect(noms).not.toContain('Supprimé SARL')
    })
})

// ─────────────────────────────────────────────────────────────────────────
// getCaisses — lecture web (Dexie)
// ─────────────────────────────────────────────────────────────────────────

describe('getCaisses — web (Dexie)', () => {
    it('retourne les opérations de caisse non-supprimées', async () => {
        const { useLocalDB } = await import('@/composables/useLocalDB')
        await db.caisses.bulkPut([
            {
                uuid:            crypto.randomUUID(),
                date_operation:  '2026-01-15',
                description:     'Vente',
                entree:          50000,
                sortie:          0,
                solde:           50000,
                updated_at:      now,
                deleted_at:      null,
            },
            {
                uuid:            crypto.randomUUID(),
                date_operation:  '2026-01-14',
                description:     'Archivée',
                entree:          10000,
                sortie:          0,
                solde:           10000,
                updated_at:      now,
                deleted_at:      now - 60,
            },
        ])

        const results = await useLocalDB().getCaisses()
        expect(results).toHaveLength(1)
        expect((results[0] as any).description).toBe('Vente')
    })
})

// ─────────────────────────────────────────────────────────────────────────
// getSuccursales — lecture web (Dexie)
// ─────────────────────────────────────────────────────────────────────────

describe('getSuccursales — web (Dexie)', () => {
    it('retourne les succursales non-supprimées', async () => {
        const { useLocalDB } = await import('@/composables/useLocalDB')
        await db.succursales.bulkPut([
            { uuid: crypto.randomUUID(), nom: 'Goma', updated_at: now, deleted_at: null },
            { uuid: crypto.randomUUID(), nom: 'Fermée', updated_at: now, deleted_at: now - 1 },
        ])

        const results = await useLocalDB().getSuccursales()
        const noms = results.map((s: any) => s.nom)
        expect(noms).toContain('Goma')
        expect(noms).not.toContain('Fermée')
    })
})

// ─────────────────────────────────────────────────────────────────────────
// getTransferts — lecture web (Dexie)
// ─────────────────────────────────────────────────────────────────────────

describe('getTransferts — web (Dexie)', () => {
    it('retourne les transferts non-supprimés', async () => {
        const { useLocalDB } = await import('@/composables/useLocalDB')
        await db.transferts.bulkPut([
            { uuid: crypto.randomUUID(), type: 'caisse', montant: 100000, statut: 'pending', updated_at: now, deleted_at: null },
            { uuid: crypto.randomUUID(), type: 'stock',  montant: 0,      statut: 'annule',  updated_at: now, deleted_at: now - 1 },
        ])

        const results = await useLocalDB().getTransferts()
        expect(results).toHaveLength(1)
        expect((results[0] as any).type).toBe('caisse')
    })
})

// ─────────────────────────────────────────────────────────────────────────
// getProduitsCount
// ─────────────────────────────────────────────────────────────────────────

describe('getProduitsCount', () => {
    it('compte uniquement les produits non-supprimés', async () => {
        const { useLocalDB } = await import('@/composables/useLocalDB')
        await db.produits.bulkPut([
            makeProduit(),
            makeProduit(),
            makeProduit({ deleted_at: now - 1 }),
        ])

        const result = await useLocalDB().getProduitsCount()
        expect(result.count).toBe(2)
    })

    it('retourne 0 si aucun produit', async () => {
        const { useLocalDB } = await import('@/composables/useLocalDB')
        const result = await useLocalDB().getProduitsCount()
        expect(result.count).toBe(0)
    })
})

// ─────────────────────────────────────────────────────────────────────────
// getValeurStock
// ─────────────────────────────────────────────────────────────────────────

describe('getValeurStock', () => {
    it('calcule quantite × prix_achat pour chaque stock', async () => {
        const { useLocalDB } = await import('@/composables/useLocalDB')
        await db.stocks.bulkPut([
            { uuid: crypto.randomUUID(), produit_id: 1, quantite: 10, prix_achat: 500, updated_at: now, deleted_at: null },
            { uuid: crypto.randomUUID(), produit_id: 2, quantite: 5,  prix_achat: 200, updated_at: now, deleted_at: null },
        ])

        const result = await useLocalDB().getValeurStock()
        // 10 × 500 + 5 × 200 = 5000 + 1000 = 6000
        expect(result.valeur_stock).toBe(6000)
    })

    it('exclut les stocks avec deleted_at', async () => {
        const { useLocalDB } = await import('@/composables/useLocalDB')
        await db.stocks.bulkPut([
            { uuid: crypto.randomUUID(), produit_id: 1, quantite: 10, prix_achat: 500, updated_at: now, deleted_at: null },
            { uuid: crypto.randomUUID(), produit_id: 2, quantite: 100, prix_achat: 999, updated_at: now, deleted_at: now - 1 },
        ])

        const result = await useLocalDB().getValeurStock()
        expect(result.valeur_stock).toBe(5000)
    })

    it('retourne 0 si aucun stock', async () => {
        const { useLocalDB } = await import('@/composables/useLocalDB')
        const result = await useLocalDB().getValeurStock()
        expect(result.valeur_stock).toBe(0)
    })
})

// ─────────────────────────────────────────────────────────────────────────
// getJournalSumByType
// ─────────────────────────────────────────────────────────────────────────

describe('getJournalSumByType', () => {
    it('somme les entrées et sorties correctement', async () => {
        const { useLocalDB } = await import('@/composables/useLocalDB')
        await db.journals.bulkPut([
            { uuid: crypto.randomUUID(), type: 'entree', montant: 10000, updated_at: now, deleted_at: null },
            { uuid: crypto.randomUUID(), type: 'entree', montant: 5000,  updated_at: now, deleted_at: null },
            { uuid: crypto.randomUUID(), type: 'sortie', montant: 3000,  updated_at: now, deleted_at: null },
        ])

        const result = await useLocalDB().getJournalSumByType()
        expect(result.entree).toBe(15000)
        expect(result.sortie).toBe(3000)
    })

    it('exclut les journals supprimés', async () => {
        const { useLocalDB } = await import('@/composables/useLocalDB')
        await db.journals.bulkPut([
            { uuid: crypto.randomUUID(), type: 'entree', montant: 10000, updated_at: now, deleted_at: null },
            { uuid: crypto.randomUUID(), type: 'entree', montant: 99999, updated_at: now, deleted_at: now - 1 },
        ])

        const result = await useLocalDB().getJournalSumByType()
        expect(result.entree).toBe(10000)
    })

    it('retourne 0/0 si aucun journal', async () => {
        const { useLocalDB } = await import('@/composables/useLocalDB')
        const result = await useLocalDB().getJournalSumByType()
        expect(result.entree).toBe(0)
        expect(result.sortie).toBe(0)
    })
})
