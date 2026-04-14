<script setup lang="ts">
import { ref, computed } from 'vue'
import { router } from '@inertiajs/vue3'
import { t as _t } from '@/lang'
import { useLang } from '@/composables/useLang'

const props = defineProps({
  clients: { type: Array, default: () => [] },
  fournisseurs: { type: Array, default: () => [] },
  devise: { type: String, default: 'CDF' },
})

const devise = computed(() => props.devise || 'CDF')
const t = _t
const lang = useLang()

const montantClient = ref({})
const montantFournisseur = ref({})

function payerCreance(clientId) {
  const montant = Number(montantClient.value[clientId] || 0)
  if (montant <= 0) {
    alert('Montant invalide.')
    return
  }
  router.post(`/creances-dettes/clients/${clientId}/paiement`, { montant }, { preserveScroll: true })
}

function payerDette(fournisseurId) {
  const montant = Number(montantFournisseur.value[fournisseurId] || 0)
  if (montant <= 0) {
    alert('Montant invalide.')
    return
  }
  router.post(`/creances-dettes/fournisseurs/${fournisseurId}/paiement`, { montant }, { preserveScroll: true })
}

function goDashboard() {
  router.get('/dashboard')
}
</script>

<template>
  <div class="p-6 space-y-6" :key="lang">
    <div class="flex justify-between items-center">
      <h1 class="text-2xl font-bold">{{ t('debts') }}</h1>
      <button type="button" @click="goDashboard" class="bg-gray-600 text-white px-4 py-2 rounded hover:bg-gray-700">
        {{ t('dashboard') }}
      </button>
    </div>

    <div class="bg-white shadow rounded p-4">
      <h2 class="text-lg font-semibold mb-2">Créances clients</h2>
      <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
          <thead class="bg-gray-50">
            <tr>
              <th class="px-4 py-2 text-left">Client</th>
              <th class="px-4 py-2 text-left">Téléphone</th>
              <th class="px-4 py-2 text-right">Créance</th>
              <th class="px-4 py-2 text-right">Montant payé</th>
              <th class="px-4 py-2 text-center">Action</th>
            </tr>
          </thead>
          <tbody v-if="props.clients.length">
            <tr v-for="c in props.clients" :key="c.id" class="border-t">
              <td class="px-4 py-2">{{ c.nom_client }}</td>
              <td class="px-4 py-2">{{ c.numero_telephone }}</td>
              <td class="px-4 py-2 text-right">{{ Number(c.creance || 0).toFixed(2) }} {{ devise }}</td>
              <td class="px-4 py-2 text-right">
                <input v-model.number="montantClient[c.id]" type="number" step="0.01" class="w-32 border p-1 rounded" />
              </td>
              <td class="px-4 py-2 text-center">
                <div class="flex items-center justify-center gap-2">
                  <button type="button" @click="payerCreance(c.id)" class="px-3 py-1 bg-green-600 text-white rounded">Payer</button>
                  <button type="button" @click="router.get(`/creances-dettes/clients/${c.id}`)" class="px-3 py-1 bg-blue-600 text-white rounded">Détail</button>
                </div>
              </td>
            </tr>
          </tbody>
          <tbody v-else>
            <tr>
              <td colspan="5" class="text-center py-6 text-gray-400">Aucune créance</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <div class="bg-white shadow rounded p-4">
      <h2 class="text-lg font-semibold mb-2">Dettes fournisseurs</h2>
      <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
          <thead class="bg-gray-50">
            <tr>
              <th class="px-4 py-2 text-left">Fournisseur</th>
              <th class="px-4 py-2 text-right">Dette</th>
              <th class="px-4 py-2 text-right">Montant payé</th>
              <th class="px-4 py-2 text-center">Action</th>
            </tr>
          </thead>
          <tbody v-if="props.fournisseurs.length">
            <tr v-for="f in props.fournisseurs" :key="f.id" class="border-t">
              <td class="px-4 py-2">{{ f.nom_entreprise_fournisseur }}</td>
              <td class="px-4 py-2 text-right">{{ Number(f.dette || 0).toFixed(2) }} {{ devise }}</td>
              <td class="px-4 py-2 text-right">
                <input v-model.number="montantFournisseur[f.id]" type="number" step="0.01" class="w-32 border p-1 rounded" />
              </td>
              <td class="px-4 py-2 text-center">
                <div class="flex items-center justify-center gap-2">
                  <button type="button" @click="payerDette(f.id)" class="px-3 py-1 bg-red-600 text-white rounded">Payer</button>
                  <button type="button" @click="router.get(`/creances-dettes/fournisseurs/${f.id}`)" class="px-3 py-1 bg-blue-600 text-white rounded">Détail</button>
                </div>
              </td>
            </tr>
          </tbody>
          <tbody v-else>
            <tr>
              <td colspan="4" class="text-center py-6 text-gray-400">Aucune dette</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</template>
