<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import { useForm, router } from '@inertiajs/vue3'
import { t as _t } from '@/lang'
import { useLang } from '@/composables/useLang'

function confirmerPaiement(id) {
  if (!id) {
    alert('ID de fiche invalide.')
    return
  }
  if (!confirm('Confirmer le paiement de cette fiche ?')) return

  router.post(`/fiches/${id}/confirmer`, { id }, {
    onSuccess: () => {
      // Inertia redirige vers la liste et rafraîchit les données côté serveur
      // On peut afficher un message optionnel
      // alert('Paiement confirmé avec succès.')
    },
    onError: (errors) => {
      console.error(errors)
      alert('Erreur lors de la confirmation du paiement.')
    }
  })
}

// Props
const props = defineProps({
  fiches: { type: Array, default: () => [] },
  employes: { type: Array, default: () => [] }
})
const t = _t
const lang = useLang()

// Modal
const modalOpen = ref(false)
const isEditing = ref(false)

const form = useForm({
  id: null,
  employe_id: null,
  mois: '',
  annee: new Date().getFullYear(),
  salaire_base: 0,
  primes: 0,
  retenues: 0,
  net_a_payer: 0,
  statut: 'en_attente',
  date_paiement: ''
})

// Options mois
const moisOptions = [
  { value: 1, label: 'Janvier' },
  { value: 2, label: 'Février' },
  { value: 3, label: 'Mars' },
  { value: 4, label: 'Avril' },
  { value: 5, label: 'Mai' },
  { value: 6, label: 'Juin' },
  { value: 7, label: 'Juillet' },
  { value: 8, label: 'Août' },
  { value: 9, label: 'Septembre' },
  { value: 10, label: 'Octobre' },
  { value: 11, label: 'Novembre' },
  { value: 12, label: 'Décembre' },
]

// Calcul automatique du net à payer
const netAPayer = computed(() => Number(form.salaire_base || 0) + Number(form.primes || 0) - Number(form.retenues || 0))

// Mise à jour du salaire de base quand on change d’employé
watch(() => form.employe_id, (val) => {
  const emp = props.employes.find(e => e.id === val)
  form.salaire_base = emp ? emp.salaire_base : 0
})

// Ouvrir modal
function openModal(fiche = null) {
  form.reset()
  if (fiche) {
    Object.assign(form, {
      ...fiche,
      employe_id: fiche.employe.id
    })
    isEditing.value = true
  } else {
    isEditing.value = false
  }
  modalOpen.value = true
}

// Enregistrer fiche
function save() {
  form.net_a_payer = netAPayer.value
  if (isEditing.value) {
    form.put(`/fiches/${form.id}`, { onSuccess: () => modalOpen.value = false })
  } else {
    form.post('/fiches', { onSuccess: () => modalOpen.value = false })
  }
}

// Dashboard
function goDashboard() {
  router.get('/dashboard')
}

// Formater date et heure: "mer 11/02/2026 07:09"
function formatDateTime(dateStr) {
  if (!dateStr) return ''

  const raw = String(dateStr).trim()
  const [datePart, timePart = '00:00:00'] = raw.includes(' ')
    ? raw.split(' ')
    : raw.split('T')

  const [y, m, d] = datePart.split('-').map(Number)
  const [hh = 0, mm = 0, ss = 0] = timePart.split(':').map(Number)

  const dt = new Date(y, (m || 1) - 1, d || 1, hh || 0, mm || 0, ss || 0)
  if (isNaN(dt.getTime())) return raw

  const weekdays = ['dim', 'lun', 'mar', 'mer', 'jeu', 'ven', 'sam']
  const dd = String(dt.getDate()).padStart(2, '0')
  const mmStr = String(dt.getMonth() + 1).padStart(2, '0')
  const yyyy = dt.getFullYear()
  const hhStr = String(dt.getHours()).padStart(2, '0')
  const minStr = String(dt.getMinutes()).padStart(2, '0')

  return `${weekdays[dt.getDay()]} ${dd}/${mmStr}/${yyyy} ${hhStr}:${minStr}`
}
</script>

<template>
  <div class="p-6" :key="lang">
    <div class="flex justify-between mb-4">
      <h1 class="text-2xl font-bold">{{ t('payroll') }}</h1>
      <div class="flex gap-2">
        <button type="button" @click="openModal()" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">Nouvelle fiche</button>
        <button type="button" @click="goDashboard" class="bg-gray-700 text-white px-4 py-2 rounded hover:bg-gray-800">{{ t('dashboard') }}</button>
      </div>
    </div>

    <!-- Tableau -->
    <div class="overflow-x-auto bg-white shadow rounded">
    <div class="overflow-x-auto -mx-4 sm:mx-0">
      <table class="min-w-full table-fixed divide-y divide-gray-200">
        <colgroup>
          <col class="w-56" /> <!-- Employé -->
          <col class="w-24" /> <!-- Mois -->
          <col class="w-20" /> <!-- Année -->
          <col class="w-28" /> <!-- Salaire de base -->
          <col class="w-20" /> <!-- Primes -->
          <col class="w-20" /> <!-- Retenues -->
          <col class="w-28" /> <!-- Net à payer -->
          <col class="w-28" /> <!-- Statut paiement -->
          <col class="w-40" /> <!-- Date de paiement -->
          <col class="w-24" /> <!-- Actions -->
        </colgroup>
        <thead class="bg-gray-50">
          <tr>
            <th class="px-3 py-2 text-left">Employé</th>
            <th class="px-3 py-2 text-left">Mois</th>
            <th class="px-3 py-2 text-left">Année</th>
            <th class="px-3 py-2 text-right">Salaire de base</th>
            <th class="px-3 py-2 text-right">Primes</th>
            <th class="px-3 py-2 text-right">Retenues</th>
            <th class="px-3 py-2 text-right">Net à payer</th>
            <th class="px-3 py-2 text-left">Statut paiement</th>
            <th class="px-3 py-2 text-left">Date de paiement</th>
            <th class="px-3 py-2 text-left">Actions</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="fiche in fiches" :key="fiche.id" class="border-t">
            <td class="px-3 py-2">{{ fiche.employe.nom }} {{ fiche.employe.prenom }}</td>
            <td class="px-3 py-2">{{ moisOptions.find(m => m.value === Number(fiche.mois))?.label }}</td>
            <td class="px-3 py-2">{{ fiche.annee }}</td>
            <td class="px-3 py-2 text-right">{{ fiche.salaire_base }}</td>
            <td class="px-3 py-2 text-right">{{ fiche.primes }}</td>
            <td class="px-3 py-2 text-right">{{ fiche.retenues }}</td>
            <td class="px-3 py-2 text-right">{{ fiche.net_a_payer }}</td>
            <td class="px-3 py-2 capitalize">{{ fiche.statut_paiement }}</td>
            <td class="px-3 py-2">{{ formatDateTime(fiche.date_paiement) }}</td>
            <td class="px-3 py-2">
              <!-- Bouton confirmer paiement -->
              <button
                type="button"
                v-if="fiche.statut_paiement === 'en_attente'"
                @click="confirmerPaiement(fiche.id)"
                class="bg-green-600 text-white px-3 py-1 rounded hover:bg-green-700"
              >
                Confirmer paiement
              </button>
            </td>
          </tr>
        </tbody>
      </table>
     </div>
    </div>

    <!-- Modal -->
    <div v-if="modalOpen" class="fixed inset-0 bg-black bg-opacity-50 flex justify-center items-center z-50">
      <div class="bg-white p-6 rounded-lg w-full max-w-3xl">
        <h2 class="text-xl font-bold mb-4">{{ isEditing ? "Modifier" : "Nouvelle" }} fiche de paie</h2>
        <form @submit.prevent="save" class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <!-- Employé -->
          <div>
            <label class="block mb-1 font-semibold">Employé</label>
            <select v-model="form.employe_id" class="w-full border p-2 rounded" required>
              <option value="" disabled>Choisir un employé</option>
              <option v-for="emp in employes" :key="emp.id" :value="emp.id">
                {{ emp.nom }} {{ emp.prenom }}
              </option>
            </select>
          </div>

          <!-- Mois -->
          <div>
            <label class="block mb-1 font-semibold">Mois</label>
            <select v-model="form.mois" class="w-full border p-2 rounded" required>
              <option value="" disabled>Choisir le mois</option>
              <option v-for="m in moisOptions" :key="m.value" :value="m.value">{{ m.label }}</option>
            </select>
          </div>

          <!-- Année -->
          <div>
            <label class="block mb-1 font-semibold">Année</label>
            <input type="number" v-model="form.annee" class="w-full border p-2 rounded" required />
          </div>

          <!-- Salaire de base -->
          <div>
            <label class="block mb-1 font-semibold">Salaire de base</label>
            <input type="number" :value="form.salaire_base" class="w-full border p-2 rounded bg-gray-100" readonly />
          </div>

          <!-- Primes -->
          <div>
            <label class="block mb-1 font-semibold">Primes</label>
            <input type="number" v-model="form.primes" class="w-full border p-2 rounded" />
          </div>

          <!-- Retenues -->
          <div>
            <label class="block mb-1 font-semibold">Retenues</label>
            <input type="number" v-model="form.retenues" class="w-full border p-2 rounded" />
          </div>

          <!-- Net à payer -->
          <div>
            <label class="block mb-1 font-semibold">Net à payer</label>
            <input :value="netAPayer" type="number" class="w-full border p-2 rounded bg-gray-100" readonly />
          </div>

          <!-- Statut -->
          <div>
            <label class="block mb-1 font-semibold">Statut</label>
            <select v-model="form.statut" class="w-full border p-2 rounded">
              <option value="en_attente">En attente</option>
              <option value="payé">Payé</option>
            </select>
          </div>

          <!-- Date paiement -->
          <div>
            <label class="block mb-1 font-semibold">Date de paiement</label>
            <input type="datetime-local" v-model="form.date_paiement" class="w-full border p-2 rounded" />
          </div>

          <!-- Boutons -->
          <div class="md:col-span-2 flex justify-end gap-2 mt-4">
            <button type="button" @click="modalOpen = false" class="bg-gray-400 text-white px-4 py-2 rounded">Annuler</button>
            <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded">Enregistrer</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>
