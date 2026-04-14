<script setup lang="ts">
import { ref, computed, watch, onMounted } from 'vue'
import { useForm, router, usePage } from '@inertiajs/vue3'
import { t as _t } from '@/lang'
import { useLang } from '@/composables/useLang'

const props = defineProps({
  stocks: Array,
  mouvements: Array,
  produits: Array,
  clients: Array,
  fournisseurs: Array,
  parametres: Object,
  alertesStock: Array,
})

const modalOpen = ref(false)
const mode = ref('entree')

const form = useForm({
  produit_id: null as number | null,
  type: 'entree',
  quantite: 1,
  prix_unitaire: 0,
  commentaire: '',
  payment_type: 'cash',
  client_phone: '',
  fournisseur_id: null as number | null,
  use_reduction: false,
})

const factureLines = ref<any[]>([])
const bonEntreeLines = ref<any[]>([])
const venteCredit = ref(false)
const achatCredit = ref(false)
const venteReduction = ref(false)
const achatReduction = ref(false)
const clientPhone = ref('')
const fournisseurId = ref<number | null>(null)
const draftKey = 'primegest_facture_draft'
const t = _t
const lang = useLang()
const page = usePage()
const pageProps = computed(() => (page.props as any) ?? {})
const multiSuccursales = computed(() =>
  !!pageProps.value.parametres?.multi_succursales || !!pageProps.value.has_succursales
)

const clientTrouve = computed(() => {
  const phone = String(clientPhone.value || '').trim()
  if (!phone) return null
  return (props.clients || []).find((c: any) => String(c.numero_telephone) === phone) || null
})

const fournisseurTrouve = computed(() => {
  if (!fournisseurId.value) return null
  return (props.fournisseurs || []).find((f: any) => Number(f.id) === Number(fournisseurId.value)) || null
})

const reductionClient = computed(() => Number((clientTrouve.value as any)?.reduction_accordee || 0))
const reductionFournisseur = computed(() => Number((fournisseurTrouve.value as any)?.reduction_obtenue || 0))

function openModalAs(m: string) {
  form.reset()
  mode.value = m
  form.type = m
  form.quantite = 1
  form.prix_unitaire = 0
  form.payment_type = 'cash'
  form.client_phone = ''
  form.fournisseur_id = null
  form.use_reduction = false
  venteCredit.value = false
  achatCredit.value = false
  venteReduction.value = false
  achatReduction.value = false
  clientPhone.value = ''
  fournisseurId.value = null
  bonEntreeLines.value = []
  modalOpen.value = true
}

// ✅ Récupère la quantité en stock du produit sélectionné
function getStock(produitId: number | null): number {
  if (!produitId) return 0
  const s = (props.stocks || []).find((s: any) => Number(s.produit_id) === Number(produitId))
  return s ? (Number((s as any).quantite) ?? 0) : 0
}

watch(venteReduction, (val) => { if (val) venteCredit.value = false })
watch(achatReduction, (val) => { if (val) achatCredit.value = false })
watch(venteCredit, (val) => { if (val) venteReduction.value = false })
watch(achatCredit, (val) => { if (val) achatReduction.value = false })

watch(() => form.produit_id, (newVal) => {
  if (!newVal) return
  const produit = (props.produits || []).find((p: any) => Number(p.id) === Number(newVal))
  if (!produit) return
  form.prix_unitaire = mode.value === 'entree'
    ? ((produit as any).prix_achat ?? 0)
    : ((produit as any).prix_vente ?? 0)
})

const prixTotal = computed(() => (Number(form.quantite) || 0) * (Number(form.prix_unitaire) || 0))

function submitMovement() {
  if (!form.produit_id) { alert('Sélectionne un produit.'); return }
  if ((Number(form.quantite) || 0) <= 0) { alert('Quantité invalide.'); return }

  form.use_reduction = (mode.value === 'sortie' && venteReduction.value) || (mode.value === 'entree' && achatReduction.value)
  form.payment_type = form.use_reduction
    ? 'reduction'
    : ((mode.value === 'sortie' && venteCredit.value) || (mode.value === 'entree' && achatCredit.value) ? 'credit' : 'cash')
  form.client_phone = mode.value === 'sortie' ? clientPhone.value : ''
  form.fournisseur_id = mode.value === 'entree' ? fournisseurId.value : null

  if (form.use_reduction && mode.value === 'sortie' && !form.client_phone) { alert('Numéro du client requis pour utiliser la réduction.'); return }
  if (form.use_reduction && mode.value === 'sortie' && !clientTrouve.value) { alert('Client introuvable pour ce numéro.'); return }
  if (form.use_reduction && mode.value === 'entree' && !form.fournisseur_id) { alert('Sélectionne un fournisseur pour utiliser la réduction.'); return }
  if (venteCredit.value && !form.client_phone) { alert('Numéro du client requis pour une vente à crédit.'); return }
  if (venteCredit.value && !clientTrouve.value) { alert('Client introuvable pour ce numéro.'); return }
  if (achatCredit.value && !form.fournisseur_id) { alert('Sélectionne un fournisseur pour un achat à crédit.'); return }

  form.post('/mouvement-stocks', {
    onSuccess: () => { modalOpen.value = false },
    onError: (errors) => { console.error(errors) }
  })
}

function buildLineFromForm() {
  if (!form.produit_id) { alert('Sélectionne un produit.'); return null }
  if ((Number(form.quantite) || 0) <= 0) { alert('Quantité invalide.'); return null }
  const produit = (props.produits || []).find((p: any) => Number(p.id) === Number(form.produit_id))
  return {
    produit_id: form.produit_id,
    quantite: Number(form.quantite),
    prix_unitaire: Number(form.prix_unitaire || 0),
    produit_nom: (produit as any)?.nom || 'Produit'
  }
}

function addToFacture() {
  const line = buildLineFromForm()
  if (!line) return
  factureLines.value.push(line)
  form.produit_id = null
  form.quantite = 1
  form.prix_unitaire = 0
  form.commentaire = ''
}

function clearFacture() { factureLines.value = [] }

function addToBonEntree() {
  if (!fournisseurId.value) { alert('Sélectionne un fournisseur pour le bon d\'entrée.'); return }
  const line = buildLineFromForm()
  if (!line) return
  bonEntreeLines.value.push(line)
  form.produit_id = null
  form.quantite = 1
  form.prix_unitaire = 0
  form.commentaire = ''
}

function clearBonEntree() { bonEntreeLines.value = [] }

function genererBonEntree() {
  if (!fournisseurId.value) { alert('Sélectionne un fournisseur pour le bon d\'entrée.'); return }
  let lignes = bonEntreeLines.value
  if (!lignes.length) {
    const line = buildLineFromForm()
    if (!line) return
    lignes = [line]
  }
  router.post('/mouvement-stocks/generer-bon-entree', {
    fournisseur_id: fournisseurId.value,
    lignes,
    payment_type: achatReduction.value ? 'reduction' : (achatCredit.value ? 'credit' : 'cash'),
    use_reduction: achatReduction.value,
  }, {
    onSuccess: () => { bonEntreeLines.value = []; modalOpen.value = false },
    onError: (errors: any) => {
      const message = errors?.fournisseur_id || errors?.lignes || 'Erreur lors de la génération du bon d\'entrée.'
      alert(message)
    }
  })
}

function genererFacture() {
  let lignes = factureLines.value
  if (!lignes.length) {
    const line = buildLineFromForm()
    if (!line) return
    lignes = [line]
  }
  const payload = {
    lignes,
    payment_type: venteReduction.value ? 'reduction' : (venteCredit.value ? 'credit' : 'cash'),
    use_reduction: venteReduction.value,
    client_phone: clientPhone.value || '',
  }
  if (venteCredit.value && !payload.client_phone) { alert('Numéro du client requis pour une vente à crédit.'); return }
  if (venteCredit.value && !clientTrouve.value) { alert('Client introuvable pour ce numéro.'); return }
  if (venteReduction.value && !payload.client_phone) { alert('Numéro du client requis pour utiliser la réduction.'); return }
  if (venteReduction.value && !clientTrouve.value) { alert('Client introuvable pour ce numéro.'); return }

  router.post('/mouvement-stocks/generer-facture', payload, {
    onSuccess: () => { factureLines.value = []; modalOpen.value = false },
    onError: (errors: any) => {
      const message = errors?.quantite || errors?.facture || errors?.lignes || errors?.produit_id || 'Erreur lors de la génération de la facture.'
      alert(message)
    }
  })
}

function saveFactureDraft() {
  localStorage.setItem(draftKey, JSON.stringify({ factureLines: factureLines.value, clientPhone: clientPhone.value }))
}

function restoreFactureDraft() {
  const raw = localStorage.getItem(draftKey)
  if (!raw) return false
  try {
    const payload = JSON.parse(raw)
    if (Array.isArray(payload.factureLines)) factureLines.value = payload.factureLines
    if (payload.clientPhone) clientPhone.value = payload.clientPhone
  } catch (e) { /* ignore */ }
  localStorage.removeItem(draftKey)
  return true
}

function goAddClient() {
  saveFactureDraft()
  router.get('/clients', { return_to: '/mouvement-stocks', phone: clientPhone.value || '' })
}

function goDashboard() { router.get('/dashboard') }

const mouvementsAvecTotal = computed(() =>
  (props.mouvements || []).map((m: any) => ({
    ...m,
    prix_total_affiche: (Number(m.quantite) || 0) * (Number(m.prix_unitaire) || 0)
  }))
)

const totalFactureTtc = computed(() =>
  factureLines.value.reduce((sum, l) => sum + (Number(l.quantite) || 0) * (Number(l.prix_unitaire) || 0), 0)
)

const totalBonEntree = computed(() =>
  bonEntreeLines.value.reduce((sum, l) => sum + (Number(l.quantite) || 0) * (Number(l.prix_unitaire) || 0), 0)
)

onMounted(() => {
  const params = new URLSearchParams(window.location.search)
  const phone = params.get('client_phone')
  const restored = restoreFactureDraft()
  if (restored) { mode.value = 'sortie'; modalOpen.value = true }
  if (phone) { clientPhone.value = phone; mode.value = 'sortie'; modalOpen.value = true }
})
</script>

<template>
  <div class="p-6 space-y-6" :key="lang">
    <!-- HEADER -->
    <div class="flex justify-between items-center">
      <h1 class="text-2xl font-bold">{{ t('stock_moves_title') }}</h1>
      <div class="flex gap-2">
        <button type="button" @click="openModalAs('entree')" class="bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700">
          Entrée
        </button>
        <button type="button" @click="openModalAs('sortie')" class="bg-red-600 text-white px-4 py-2 rounded hover:bg-red-700">
          Sortie (Vente)
        </button>
        <button v-if="multiSuccursales" type="button" @click="router.get('/transferts', { tab: 'stock' })" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">
          Transfert
        </button>
        <button type="button" @click="goDashboard" class="bg-gray-600 text-white px-4 py-2 rounded hover:bg-gray-700">
          {{ t('dashboard') }}
        </button>
      </div>
    </div>

    <!-- ALERTES STOCK -->
    <div v-if="alertesStock && alertesStock.length" class="bg-amber-50 border border-amber-200 rounded p-4">
      <h2 class="text-sm font-semibold text-amber-800 mb-2">⚠️ Alertes stock bas</h2>
      <div class="space-y-1">
        <div v-for="(a, idx) in alertesStock" :key="idx" class="flex items-center justify-between text-sm">
          <div>
            <span class="font-medium text-amber-800">{{ (a as any).produit }}</span>
            <span v-if="(a as any).succursale" class="text-gray-500 ml-1">({{ (a as any).succursale }})</span>
          </div>
          <span class="text-gray-500 text-xs">Qté: {{ (a as any).quantite }} / Seuil: {{ (a as any).seuil }}</span>
        </div>
      </div>
    </div>

    <!-- APERÇU DU STOCK -->
    <div class="bg-white shadow rounded p-4">
      <h2 class="text-lg font-semibold mb-2">Aperçu du stock</h2>
      <div class="overflow-x-auto">
        <table class="min-w-full table-fixed divide-y divide-gray-200">
          <thead class="bg-gray-50">
            <tr>
              <th class="px-3 py-2 text-left w-40">Produit</th>
              <th class="px-3 py-2 text-right w-24">Quantité</th>
              <th class="px-3 py-2 text-right w-32">Prix Achat</th>
              <th class="px-3 py-2 text-right w-32">Prix Vente</th>
              <th class="px-3 py-2 text-right w-32">Total Achat</th>
              <th class="px-3 py-2 text-right w-32">Total Vente</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="s in stocks" :key="(s as any).produit_id ?? (s as any).id" class="border-t">
              <td class="px-3 py-2 w-40">{{ (s as any).produit?.nom ?? '-' }}</td>
              <td class="px-3 py-2 w-24 text-right">{{ (s as any).quantite }}</td>
              <td class="px-3 py-2 w-32 text-right">{{ (s as any).prix_achat }}</td>
              <td class="px-3 py-2 w-32 text-right">{{ (s as any).prix_vente }}</td>
              <td class="px-3 py-2 w-32 text-right">{{ ((s as any).quantite * (s as any).prix_achat).toFixed(2) }}</td>
              <td class="px-3 py-2 w-32 text-right">{{ ((s as any).quantite * (s as any).prix_vente).toFixed(2) }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- LISTE DES MOUVEMENTS -->
    <div class="bg-white shadow rounded p-4">
      <h2 class="text-lg font-semibold mb-2">Liste des mouvements</h2>
      <div class="relative overflow-x-auto max-h-[500px]">
        <table class="min-w-full border-collapse divide-y divide-gray-200">
          <thead class="bg-blue-800 text-white">
            <tr>
              <th class="px-3 py-2 text-left">Produit</th>
              <th class="px-3 py-2 text-left">Type</th>
              <th class="px-3 py-2 text-right">Quantité</th>
              <th class="px-3 py-2 text-right">Prix Unitaire</th>
              <th class="px-3 py-2 text-right">Prix Total</th>
              <th class="px-3 py-2 text-left">Commentaire</th>
              <th class="px-3 py-2 text-left">Date</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100">
            <tr v-for="m in mouvementsAvecTotal" :key="(m as any).id" class="hover:bg-gray-50 transition">
              <td class="px-3 py-2">{{ (m as any).produit?.nom }}</td>
              <td class="px-3 py-2 capitalize">{{ (m as any).type }}</td>
              <td class="px-3 py-2 text-right">{{ (m as any).quantite }}</td>
              <td class="px-3 py-2 text-right">{{ (m as any).prix_unitaire }}</td>
              <td class="px-3 py-2 text-right">{{ (m as any).prix_total_affiche }}</td>
              <td class="px-3 py-2">{{ (m as any).commentaire || '-' }}</td>
              <td class="px-3 py-2">{{ String((m as any).created_at).substring(0, 19) }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- MODAL FORMULAIRE -->
    <div v-if="modalOpen" class="fixed inset-0 bg-black bg-opacity-50 flex justify-center items-start pt-12 z-50">
      <div class="bg-white p-6 rounded w-full max-w-3xl max-h-[80vh] overflow-y-auto">
        <div class="flex justify-between items-center mb-4">
          <h2 class="text-xl font-bold">
            {{ mode === 'entree' ? 'Formulaire d\'achat (Entrée)' : 'Formulaire de vente (Sortie)' }}
          </h2>
          <button type="button" @click="modalOpen = false" class="px-3 py-1 border rounded">Fermer</button>
        </div>

        <div class="grid grid-cols-1 gap-4">
          <div>
            <!-- Fournisseur (mode entrée) -->
            <div v-if="mode === 'entree'" class="mb-2">
              <label class="text-sm text-gray-600">Fournisseur</label>
              <select v-model="fournisseurId" class="w-full border p-2 rounded mt-1">
                <option :value="null" disabled>Choisir un fournisseur</option>
                <option v-for="f in fournisseurs" :key="(f as any).id" :value="(f as any).id">
                  {{ (f as any).nom_entreprise_fournisseur }}
                </option>
              </select>
              <div v-if="achatReduction && fournisseurTrouve" class="text-sm text-gray-600 mt-1">
                Réduction disponible: <span class="font-semibold">{{ reductionFournisseur.toFixed(2) }}</span>
              </div>
            </div>

            <!-- Client (mode sortie) -->
            <div v-if="mode === 'sortie'" class="mb-2 space-y-2">
              <label class="text-sm text-gray-600">Client (numéro de téléphone)</label>
              <div class="flex gap-2">
                <input v-model="clientPhone" type="text" placeholder="Ex: 0999999999" class="w-full border p-2 rounded"/>
                <button type="button" @click="goAddClient" class="px-3 py-2 bg-blue-600 text-white rounded">
                  Ajouter un client
                </button>
              </div>
              <div class="text-sm text-gray-600">
                Client: <span class="font-semibold">{{ clientTrouve ? (clientTrouve as any).nom_client : 'Non trouvé' }}</span>
              </div>
              <div v-if="venteReduction && clientTrouve" class="text-sm text-gray-600">
                Réduction disponible: <span class="font-semibold">{{ reductionClient.toFixed(2) }}</span>
              </div>
            </div>

            <!-- ✅ Select produit avec stock visible -->
            <label class="text-sm text-gray-600">Produit</label>
            <select v-model="form.produit_id" class="w-full border p-2 rounded mt-1">
              <option :value="null" disabled>Choisir un produit</option>
              <option
                v-for="p in produits"
                :key="(p as any).id"
                :value="(p as any).id"
                :disabled="mode === 'sortie' && getStock((p as any).id) === 0"
              >
                {{ (p as any).nom }}
                <template v-if="mode === 'sortie'"> — stock: {{ getStock((p as any).id) }}</template>
              </option>
            </select>

            <!-- ✅ Indicateur stock disponible -->
            <div v-if="form.produit_id && mode === 'sortie'" class="mt-1 text-xs flex items-center gap-1">
              <span
                :class="getStock(form.produit_id) > 0 ? 'text-green-600' : 'text-red-500'"
                class="font-medium"
              >
                ● Stock disponible : {{ getStock(form.produit_id) }}
              </span>
              <span v-if="getStock(form.produit_id) === 0" class="text-red-400 ml-1">
                (rupture de stock)
              </span>
            </div>

            <input
              type="number"
              v-model.number="form.quantite"
              placeholder="Quantité"
              class="w-full border p-2 rounded mt-2"
              :max="mode === 'sortie' ? getStock(form.produit_id) : undefined"
              min="1"
            />
            <input type="number" v-model.number="form.prix_unitaire" placeholder="Prix Unitaire" class="w-full border p-2 rounded mt-2"/>
            <input type="text" :value="prixTotal" placeholder="Prix Total" class="w-full border p-2 rounded bg-gray-100 mt-2" disabled/>
            <textarea v-model="form.commentaire" placeholder="Commentaire" class="w-full border p-2 rounded mt-2"></textarea>

            <!-- Options paiement sortie -->
            <div v-if="mode === 'sortie'" class="mt-4 space-y-2">
              <label class="flex items-center gap-2">
                <input type="checkbox" v-model="venteCredit" />
                <span>Vente à crédit</span>
              </label>
              <label class="flex items-center gap-2">
                <input type="checkbox" v-model="venteReduction" />
                <span>Utiliser réduction</span>
              </label>
            </div>

            <!-- Options paiement entrée -->
            <div v-if="mode === 'entree'" class="mt-4 space-y-2">
              <label class="flex items-center gap-2">
                <input type="checkbox" v-model="achatCredit" />
                <span>Achat à crédit</span>
              </label>
              <label class="flex items-center gap-2">
                <input type="checkbox" v-model="achatReduction" />
                <span>Utiliser réduction</span>
              </label>
            </div>

            <!-- Boutons action -->
            <div class="mt-4 flex gap-2 flex-wrap">
              <button type="button" v-if="mode === 'entree'" @click="addToBonEntree" class="px-4 py-2 border rounded">
                Ajouter au bon d'entrée
              </button>
              <button type="button" v-if="mode === 'entree'" @click="genererBonEntree" class="px-4 py-2 bg-blue-600 text-white rounded">
                Enregistrer le bon d'entrée
              </button>
              <template v-if="mode === 'sortie'">
                <button type="button" @click="addToFacture" class="px-4 py-2 border rounded">
                  Ajouter à la facture
                </button>
                <button type="button" @click="genererFacture" class="px-4 py-2 bg-blue-600 text-white rounded">
                  {{ venteCredit ? 'Générer facture (crédit)' : 'Générer facture' }}
                </button>
              </template>
            </div>

            <!-- Lignes facture -->
            <div v-if="mode === 'sortie' && factureLines.length" class="mt-6">
              <div class="flex items-center justify-between mb-2">
                <h3 class="font-semibold">Produits ajoutés</h3>
                <button type="button" @click="clearFacture" class="text-sm text-red-600">Vider</button>
              </div>
              <table class="min-w-full border border-gray-200">
                <thead class="bg-gray-50">
                  <tr>
                    <th class="px-3 py-2 text-left">Produit</th>
                    <th class="px-3 py-2 text-right">Qté</th>
                    <th class="px-3 py-2 text-right">PU</th>
                    <th class="px-3 py-2 text-right">Total</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="(l, idx) in factureLines" :key="idx" class="border-t">
                    <td class="px-3 py-2">{{ l.produit_nom }}</td>
                    <td class="px-3 py-2 text-right">{{ l.quantite }}</td>
                    <td class="px-3 py-2 text-right">{{ l.prix_unitaire }}</td>
                    <td class="px-3 py-2 text-right">{{ (l.quantite * l.prix_unitaire).toFixed(2) }}</td>
                  </tr>
                  <tr class="bg-gray-50 font-semibold">
                    <td class="px-3 py-2 text-right" colspan="3">Total</td>
                    <td class="px-3 py-2 text-right">{{ totalFactureTtc.toFixed(2) }}</td>
                  </tr>
                </tbody>
              </table>
            </div>

            <!-- Lignes bon d'entrée -->
            <div v-if="mode === 'entree' && bonEntreeLines.length" class="mt-6">
              <div class="flex items-center justify-between mb-2">
                <h3 class="font-semibold">Produits ajoutés au bon d'entrée</h3>
                <button type="button" @click="clearBonEntree" class="text-sm text-red-600">Vider</button>
              </div>
              <table class="min-w-full border border-gray-200">
                <thead class="bg-gray-50">
                  <tr>
                    <th class="px-3 py-2 text-left">Produit</th>
                    <th class="px-3 py-2 text-right">Qté</th>
                    <th class="px-3 py-2 text-right">PU</th>
                    <th class="px-3 py-2 text-right">Total</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="(l, idx) in bonEntreeLines" :key="idx" class="border-t">
                    <td class="px-3 py-2">{{ l.produit_nom }}</td>
                    <td class="px-3 py-2 text-right">{{ l.quantite }}</td>
                    <td class="px-3 py-2 text-right">{{ l.prix_unitaire }}</td>
                    <td class="px-3 py-2 text-right">{{ (l.quantite * l.prix_unitaire).toFixed(2) }}</td>
                  </tr>
                  <tr class="bg-gray-50 font-semibold">
                    <td class="px-3 py-2 text-right" colspan="3">Total</td>
                    <td class="px-3 py-2 text-right">{{ totalBonEntree.toFixed(2) }}</td>
                  </tr>
                </tbody>
              </table>
            </div>

          </div>
        </div>
      </div>
    </div>
  </div>
</template>