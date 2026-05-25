<template>
  <div class="flex min-h-screen bg-gray-100" :key="lang">
    <!-- Sidebar -->
    <aside class="hidden lg:flex flex-col w-64 bg-black text-white p-4">
      <div class="flex items-center space-x-3 mb-8">
        <img :src="getLogoUrl()" class="h-12 w-12"/>
        <h1 class="font-bold text-xl">PrimeGest</h1>
      </div>
      <nav class="space-y-2">
        <Link href="/dashboard" class="block px-4 py-2 rounded hover:bg-blue-800 transition">
          <span class="flex items-center space-x-2">
            <Icon name="dashboard" class="text-lg" />
            <span>{{ t('dashboard') }}</span>
          </span>
        </Link>
        <button v-if="canSeeReport" class="w-full text-left px-4 py-2 rounded bg-blue-800">
          <span class="flex items-center space-x-2">
            <Icon name="assessment" class="text-lg" />
            <span>{{ t('report') }}</span>
          </span>
        </button>
      </nav>
    </aside>

    <!-- Contenu principal -->
    <div class="flex-1 flex flex-col">
      <!-- Header -->
      <header class="bg-white shadow p-4 print-hide">
        <div class="flex flex-wrap justify-between items-center gap-3">
          <div>
            <h1 class="text-xl sm:text-2xl font-bold text-gray-800">{{ getTitreRapport() }}</h1>
            <p class="text-gray-600 text-sm" v-if="rapport">{{ rapport.periode_detaillee }}</p>
          </div>
          <div class="flex flex-wrap gap-2">
            <button @click="imprimerRapport" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded flex items-center space-x-2">
              <Icon name="print" />
              <span>Imprimer</span>
            </button>
            <button @click="telechargerRapport" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded flex items-center space-x-2">
              <Icon name="download" />
              <span>Télécharger</span>
            </button>
          </div>
        </div>
      </header>

      <!-- Onglets -->
      <div class="bg-white shadow print-hide">
        <div class="flex border-b overflow-x-auto">
          <button
            v-for="tab in tabs"
            :key="tab.value"
            @click="changeType(tab.value)"
            :class="[
              'px-6 py-3 font-medium text-sm transition',
              selectedType === tab.value
                ? 'border-b-2 border-blue-600 text-blue-600'
                : 'text-gray-600 hover:text-gray-800'
            ]"
          >
            {{ tab.label }}
          </button>
        </div>

        <!-- Sélecteur de date selon le type -->
        <div class="px-4 sm:px-6 py-4 border-b bg-gray-50 flex flex-wrap items-center gap-3">
          <label class="text-sm font-medium text-gray-700" v-if="selectedType === 'journalier'">
            📅 Sélectionner une date:
          </label>
          <label class="text-sm font-medium text-gray-700" v-else-if="selectedType === 'hebdomadaire'">
            Sélectionner une période (7 jours):
          </label>
          <label class="text-sm font-medium text-gray-700" v-else-if="selectedType === 'mensuel'">
            Sélectionner un mois:
          </label>
          <label class="text-sm font-medium text-gray-700" v-else-if="selectedType === 'annuel'">
            Sélectionner une période:
          </label>
          
          <div v-if="selectedType === 'journalier'" class="flex items-center space-x-2">
            <input
              v-model="selectedDate"
              type="date"
              @change="reloadReport"
              class="px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500"
            />
          </div>
          <div v-else-if="selectedType === 'hebdomadaire'" class="flex items-center space-x-2">
            <span class="text-sm text-gray-600">Du</span>
            <input
              v-model="selectedDateDebut"
              type="date"
              @change="reloadReport"
              class="px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500"
            />
            <span class="text-sm text-gray-600">au</span>
            <input
              v-model="selectedDateFin"
              type="date"
              @change="reloadReport"
              class="px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500"
            />
          </div>
          <input
            v-else-if="selectedType === 'mensuel'"
            v-model="selectedMonth"
            type="month"
            @change="reloadReport"
            class="px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500"
          />
          <input
            v-else-if="selectedType === 'annuel'"
            v-model="selectedDate"
            type="number"
            :min="2000"
            :max="2099"
            @change="reloadReport"
            placeholder="YYYY"
            class="px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500"
          />
        </div>
      </div>

      <!-- Contenu -->
      <main v-if="rapport" class="space-y-6 p-4 sm:p-6">

        <!-- Entête du rapport -->
        <section class="bg-white shadow rounded-lg p-6">
          <div class="flex flex-col md:flex-row md:items-start md:justify-between gap-4">
            <div class="flex flex-col gap-2">
              <div class="text-sm text-gray-600">
                <div class="font-semibold">{{ parametres?.nom_entreprise || 'Entreprise' }}</div>
                <div>{{ parametres?.adresse || '-' }}</div>
                <div>{{ parametres?.telephone || '-' }}</div>
              </div>
              <img v-if="parametres?.logo_url" :src="parametres.logo_url" class="h-14 w-14 object-contain" />
              <div>
                <h2 class="text-xl font-bold text-gray-800">
                  {{ getTitreRapport() }}
                </h2>
                <p class="text-gray-600 text-sm" v-if="rapport.periode_detaillee">
                  {{ rapport.periode_detaillee }}
                </p>
              </div>
            </div>
            <div class="text-sm text-gray-600">
              <p v-if="selectedType === 'hebdomadaire'">Période: du {{ selectedDateDebut }} au {{ selectedDateFin }}</p>
              <p v-else-if="selectedType === 'mensuel'">Période: {{ selectedMonth }}</p>
              <p v-else-if="selectedType === 'annuel'">Période: {{ selectedDate }}</p>
              <p v-else>Période: {{ selectedDate }}</p>
              <p>Devise: {{ devise }}</p>
            </div>
          </div>
        </section>

        <!-- Alerte solde initial caisse -->
        <section v-if="rapport.caisse && !rapport.caisse.has_initial" class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 print-hide">
          <p class="text-yellow-800 text-sm">
            Le solde initial de la caisse n'est pas encore défini. Cela peut rendre certains rapports négatifs.
            Veuillez définir un solde initial dans la page Caisse.
          </p>
        </section>
        
        <!-- Tableau Mouvements de Stock -->
        <section class="bg-white shadow rounded-lg p-6">
          <h2 class="text-xl font-bold text-gray-800 mb-4">Mouvements de Stock</h2>
          <div class="overflow-x-auto">
            <table class="w-full border-collapse border border-gray-300">
              <thead class="bg-gray-100">
                <tr>
                  <th class="border border-gray-300 px-4 py-2 text-left">Produit</th>
                  <th class="border border-gray-300 px-4 py-2 text-right">PU</th>
                  <th class="border border-gray-300 px-4 py-2 text-right">SI</th>
                  <th class="border border-gray-300 px-4 py-2 text-right">Entrée</th>
                  <th class="border border-gray-300 px-4 py-2 text-right">Sortie</th>
                  <th class="border border-gray-300 px-4 py-2 text-right">Stock Final</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="(mouvement, idx) in rapport.mouvements" :key="idx" class="hover:bg-gray-50">
                  <td class="border border-gray-300 px-4 py-2">{{ mouvement.produit_nom }}</td>
                  <td class="border border-gray-300 px-4 py-2 text-right">{{ formatAmount(mouvement.prix_unitaire) }} {{ devise }}</td>
                  <td class="border border-gray-300 px-4 py-2 text-right">{{ formatQty(getStockInitialProduit(mouvement.produit_id)) }}</td>
                  <td class="border border-gray-300 px-4 py-2 text-right text-green-600 font-semibold">{{ formatQty(mouvement.entrees) }}</td>
                  <td class="border border-gray-300 px-4 py-2 text-right text-red-600 font-semibold">{{ formatQty(mouvement.sorties) }}</td>
                  <td class="border border-gray-300 px-4 py-2 text-right font-semibold">
                    {{ formatQty(getStockFinalProduit(mouvement.produit_id, mouvement.entrees, mouvement.sorties)) }}
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </section>

        <!-- Résumé compact -->
        <section class="bg-white shadow rounded-lg p-6">
          <h2 class="text-xl font-bold text-gray-800 mb-4">Résumé du rapport</h2>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-6 text-sm">
            <div class="space-y-2">
              <div class="flex justify-between">
                <span class="font-semibold">Stock initial :</span>
                <span>{{ formatAmount(rapport.resume.stock_initial_valeur) }} {{ devise }} ({{ formatQty(rapport.resume.stock_initial_quantite) }} unités)</span>
              </div>
              <div class="flex justify-between">
                <span class="font-semibold">Entrées :</span>
                <span>{{ formatAmount(rapport.resume.total_entrees) }} {{ devise }}</span>
              </div>
              <div class="flex justify-between">
                <span class="font-semibold">Sorties :</span>
                <span>{{ formatAmount(rapport.resume.total_sorties) }} {{ devise }}</span>
              </div>
              <div class="flex justify-between">
                <span class="font-semibold">Stock final :</span>
                <span>{{ formatAmount(rapport.resume.stock_final_valeur) }} {{ devise }} ({{ formatQty(rapport.resume.stock_final_quantite) }} unités)</span>
              </div>
            </div>
            <div class="space-y-2" v-if="rapport.caisse">
              <div class="flex justify-between">
                <span class="font-semibold">Solde caisse actuel :</span>
                <span>{{ formatAmount(rapport.caisse.solde_total) }} {{ devise }}</span>
              </div>
              <div class="flex justify-between">
                <span class="font-semibold">Entrées caisse :</span>
                <span>{{ formatAmount(rapport.caisse.entrees_periode) }} {{ devise }}</span>
              </div>
              <div class="flex justify-between">
                <span class="font-semibold">Sorties caisse :</span>
                <span>{{ formatAmount(rapport.caisse.sorties_periode) }} {{ devise }}</span>
              </div>
              <div class="flex justify-between">
                <span class="font-semibold">Solde période :</span>
                <span>{{ formatAmount(rapport.caisse.solde_periode) }} {{ devise }}</span>
              </div>
            </div>
          </div>
        </section>

        <!-- Résumé Financier -->
        <section class="bg-blue-50 border border-blue-200 rounded-lg p-6">
          <h3 class="text-lg font-bold text-blue-900 mb-4">Résumé Financier</h3>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
            <div class="space-y-2">
              <div class="flex justify-between" v-if="selectedType === 'mensuel'">
                <span class="font-semibold">Stock initial au premier jour du mois:</span>
                <span class="font-bold text-blue-900">{{ formatAmount(rapport.resume.stock_initial_valeur) }} {{ devise }}</span>
              </div>
              <div class="flex justify-between" v-else>
                <span class="font-semibold">Stock initial:</span>
                <span class="font-bold text-blue-900">{{ rapport.resume.stock_initial_valeur.toFixed(2) }} {{ devise }}</span>
              </div>
              <div class="flex justify-between">
                <span class="font-semibold">Total achats:</span>
                <span class="font-bold text-green-600">{{ formatAmount(rapport.resume.total_entrees) }} {{ devise }}</span>
              </div>
              <div class="flex justify-between">
                <span class="font-semibold">Total ventes:</span>
                <span class="font-bold text-red-600">{{ formatAmount(rapport.resume.total_sorties) }} {{ devise }}</span>
              </div>
              <div class="flex justify-between" v-if="selectedType === 'mensuel'">
                <span class="font-semibold">Stock final au dernier jour du mois:</span>
                <span class="font-bold text-purple-600">{{ formatAmount(rapport.resume.stock_final_valeur) }} {{ devise }}</span>
              </div>
              <div class="flex justify-between" v-else>
                <span class="font-semibold">Stock final:</span>
                <span class="font-bold text-purple-600">{{ rapport.resume.stock_final_valeur.toFixed(2) }} {{ devise }}</span>
              </div>
            </div>
            <div class="space-y-2">
              <div class="flex justify-between">
                <span class="font-semibold">Total dépenses:</span>
                <span class="font-bold text-red-600">{{ formatAmount(rapport.totalDepenses) }} {{ devise }}</span>
              </div>
              <div class="flex justify-between border-t-2 border-blue-300 pt-2">
                <span class="font-bold text-lg" v-if="selectedType === 'journalier'">Recette journalière:</span>
                <span class="font-bold text-lg" v-else-if="selectedType === 'hebdomadaire'">Recette hebdomadaire:</span>
                <span class="font-bold text-lg" v-else-if="selectedType === 'mensuel'">Recette mensuelle:</span>
                <span class="font-bold text-lg" v-else-if="selectedType === 'annuel'">Recette annuelle:</span>
                <span class="font-bold text-lg text-blue-900">{{ formatAmount(rapport.resume.recette) }} {{ devise }}</span>
              </div>
            </div>
          </div>
          <div v-if="rapport.resume.explications" class="mt-4 text-xs text-blue-900">
            <p v-if="rapport.resume.explications.stock_final_valeur">Explication: {{ rapport.resume.explications.stock_final_valeur }}</p>
            <p v-if="rapport.resume.explications.difference">Explication: {{ rapport.resume.explications.difference }}</p>
            <p v-if="rapport.resume.explications.recette">Explication: {{ rapport.resume.explications.recette }}</p>
          </div>
        </section>

        <!-- Tableau Dépenses -->
        <section class="bg-white shadow rounded-lg p-6">
          <h2 class="text-xl font-bold text-gray-800 mb-4">Dépenses</h2>
          <div class="overflow-x-auto">
            <table class="w-full border-collapse border border-gray-300">
              <thead class="bg-gray-100">
                <tr>
                  <th class="border border-gray-300 px-4 py-2 text-left">Libellé</th>
                  <th class="border border-gray-300 px-4 py-2 text-center">Montant</th>
                  <th class="border border-gray-300 px-4 py-2 text-left">Utilisateur</th>
                  <th class="border border-gray-300 px-4 py-2 text-center">Heure</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="(depense, idx) in rapport.depenses" :key="idx" class="hover:bg-gray-50">
                  <td class="border border-gray-300 px-4 py-2">{{ depense.libelle }}</td>
                  <td class="border border-gray-300 px-4 py-2 text-center font-semibold">{{ formatAmount(depense.montant) }} {{ devise }}</td>
                  <td class="border border-gray-300 px-4 py-2">{{ depense.user }}</td>
                  <td class="border border-gray-300 px-4 py-2 text-center text-sm">{{ depense.heure }}</td>
                </tr>
                <tr class="bg-gray-100 font-bold">
                  <td class="border border-gray-300 px-4 py-2">Total</td>
                  <td class="border border-gray-300 px-4 py-2 text-center">{{ formatAmount(rapport.totalDepenses) }} {{ devise }}</td>
                  <td class="border border-gray-300 px-4 py-2"></td>
                  <td class="border border-gray-300 px-4 py-2"></td>
                </tr>
              </tbody>
            </table>
          </div>
        </section>

        <!-- Créances & Dettes du jour -->
        <section v-if="rapport.creances_jour || rapport.dettes_jour || rapport.paiements_creances || rapport.paiements_dettes" class="bg-white shadow rounded-lg p-6">
          <h2 class="text-xl font-bold text-gray-800 mb-4">Créances & Dettes (Journalier)</h2>

          <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
              <h3 class="font-semibold mb-2">Créances du jour</h3>
              <ul class="text-sm text-gray-700 space-y-1" v-if="rapport.creances_jour?.length">
                <li v-for="(c, idx) in rapport.creances_jour" :key="'c'+idx">
                  {{ c.client }} : {{ formatAmount(c.montant) }} {{ devise }} ({{ formatDateTimeShort(c.date) }})
                </li>
              </ul>
              <p v-else class="text-sm text-gray-400">Aucune créance du jour</p>
            </div>

            <div>
              <h3 class="font-semibold mb-2">Dettes du jour</h3>
              <ul class="text-sm text-gray-700 space-y-1" v-if="rapport.dettes_jour?.length">
                <li v-for="(d, idx) in rapport.dettes_jour" :key="'d'+idx">
                  {{ d.fournisseur }} : {{ formatAmount(d.montant) }} {{ devise }} ({{ formatDateTimeShort(d.date) }})
                </li>
              </ul>
              <p v-else class="text-sm text-gray-400">Aucune dette du jour</p>
            </div>

            <div>
              <h3 class="font-semibold mb-2">Paiements de créances</h3>
              <ul class="text-sm text-gray-700 space-y-1" v-if="rapport.paiements_creances?.length">
                <li v-for="(p, idx) in rapport.paiements_creances" :key="'pc'+idx">
                  {{ p.client }} : {{ formatAmount(p.montant) }} {{ devise }} ({{ formatDateTimeShort(p.date) }})
                </li>
              </ul>
              <p v-else class="text-sm text-gray-400">Aucun paiement de créance</p>
            </div>

            <div>
              <h3 class="font-semibold mb-2">Paiements de dettes</h3>
              <ul class="text-sm text-gray-700 space-y-1" v-if="rapport.paiements_dettes?.length">
                <li v-for="(p, idx) in rapport.paiements_dettes" :key="'pd'+idx">
                  {{ p.fournisseur }} : {{ formatAmount(p.montant) }} {{ devise }} ({{ formatDateTimeShort(p.date) }})
                </li>
              </ul>
              <p v-else class="text-sm text-gray-400">Aucun paiement de dette</p>
            </div>
          </div>
        </section>

      </main>

      <div v-else class="flex items-center justify-center">
        <p class="text-gray-500 text-lg">Chargement des données...</p>
      </div>
    </div>
  </div>
</template>

<script lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { t as _t, getStoredLang } from '@/lang';
import Icon from '@/components/Icon.vue';

export default {
  components: {
    Link,
    Icon,
  },
  props: {
    rapport: Object,
    type: {
      type: String,
      default: 'journalier',
    },
    date: {
      type: String,
      default: '',
    },
    date_debut: {
      type: String,
      default: '',
    },
    date_fin: {
      type: String,
      default: '',
    },
    devise: {
      type: String,
      default: 'CDF',
    },
    parametres: {
      type: Object,
      default: null,
    },
  },
  data() {
    return {
      lang: getStoredLang(),
      selectedType: this.type || 'journalier',
      selectedDate: this.date || new Date().toISOString().split('T')[0],
      selectedMonth: this.date ? this.date.substring(0, 7) : new Date().toISOString().substring(0, 7),
      selectedDateDebut: this.date_debut || this.date || new Date().toISOString().split('T')[0],
      selectedDateFin: this.date_fin || new Date(Date.now() + 7 * 24 * 60 * 60 * 1000).toISOString().split('T')[0],
      tabs: [
        { label: 'Journalier', value: 'journalier' },
        { label: 'Hebdomadaire', value: 'hebdomadaire' },
        { label: 'Mensuel', value: 'mensuel' },
        { label: 'Annuel', value: 'annuel' },
      ],
    };
  },
  computed: {
    canSeeReport() {
      const user = this.$page?.props?.auth?.user || this.$page?.props?.value?.auth?.user || null;
      const role = String(user?.role || '').toLowerCase();
      if (role === 'super_admin' || role === 'super_aadmin') return true;
      const allowed = user?.access_pages || [];
      return allowed.includes('rapports');
    },
  },
  created() {
    window.addEventListener('primegest:lang', this.handleLangChange);
  },
  beforeUnmount() {
    window.removeEventListener('primegest:lang', this.handleLangChange);
  },
  watch: {
    type(newVal) {
      if (newVal) this.selectedType = newVal;
    },
    date(newVal) {
      if (newVal) {
        this.selectedDate = newVal;
        this.selectedMonth = newVal.substring(0, 7);
      }
    },
    date_debut(newVal) {
      if (newVal) this.selectedDateDebut = newVal;
    },
    date_fin(newVal) {
      if (newVal) this.selectedDateFin = newVal;
    },
  },
  methods: {
    t(key) {
      return _t(key);
    },
    handleLangChange() {
      this.lang = getStoredLang();
    },
    getLogoUrl() {
      return this.$page?.props?.parametres?.logo_url || '/images/primegest.webp';
    },
    changeType(type) {
      this.selectedType = type;
      if (type === 'annuel' && String(this.selectedDate).length !== 4) {
        this.selectedDate = String(new Date().getFullYear());
      }
      this.reloadReport();
    },
    reloadReport() {
      const dataToSend = {
        type: this.selectedType,
      };
      
      if (this.selectedType === 'mensuel') {
        dataToSend.date = this.selectedMonth;
      } else if (this.selectedType === 'hebdomadaire') {
        // Pour les rapports hebdomadaires, envoyer les deux dates
        dataToSend.date_debut = this.selectedDateDebut;
        dataToSend.date_fin = this.selectedDateFin;
      } else {
        dataToSend.date = this.selectedDate;
      }
      
      router.get('/rapport', dataToSend, { preserveState: true });
    },
    telechargerRapport() {
      this.logReportAction('download');
      const nomFichier = `rapport_${this.selectedType}_${this.selectedDate}.json`;
      const donnees = JSON.stringify(this.rapport, null, 2);
      const element = document.createElement('a');
      element.setAttribute('href', 'data:text/json;charset=utf-8,' + encodeURIComponent(donnees));
      element.setAttribute('download', nomFichier);
      element.style.display = 'none';
      document.body.appendChild(element);
      element.click();
      document.body.removeChild(element);
    },
    imprimerRapport() {
      this.logReportAction('print');
      window.print();
    },
    logReportAction(action) {
      router.post('/rapport/log', {
        action,
        report_type: this.selectedType,
        report_date: this.selectedType === 'hebdomadaire'
          ? `${this.selectedDateDebut} au ${this.selectedDateFin}`
          : this.selectedType === 'mensuel'
            ? this.selectedMonth
            : this.selectedDate,
      }, { preserveState: true });
    },
    getTitreRapport() {
      const titres = {
        journalier: 'Rapport Journalier',
        hebdomadaire: 'Rapport Hebdomadaire',
        mensuel: 'Rapport Mensuel',
        annuel: 'Rapport Annuel',
      };
      return titres[this.selectedType] || 'Rapport';
    },
    getStockInitialProduit(produitId) {
      const parProduit = this.rapport?.stock_initial?.par_produit || {};
      const data = parProduit[produitId];
      if (!data) return 0;
      return data.quantite ?? 0;
    },
    getStockInitialValeur(produitId) {
      const parProduit = this.rapport?.stock_initial?.par_produit || {};
      const data = parProduit[produitId];
      if (!data) return 0;
      return data.valeur ?? 0;
    },
    getStockFinalProduit(produitId, entree = 0, sortie = 0) {
      return Number(this.getStockInitialProduit(produitId) || 0) + Number(entree || 0) - Number(sortie || 0);
    },
    formatAmount(value) {
      const num = Number(value || 0);
      return num.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    },
    formatQty(value) {
      const num = Number(value || 0);
      return num.toLocaleString('en-US', { maximumFractionDigits: 0 });
    },
    formatDateTimeShort(dateStr) {
      if (!dateStr) return '';
      try {
        const d = new Date(dateStr);
        if (!isNaN(d.getTime())) {
          const fmt = new Intl.DateTimeFormat('fr-FR', {
            timeZone: 'Africa/Lubumbashi',
            year: 'numeric',
            month: '2-digit',
            day: '2-digit',
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit',
          });
          return fmt.format(d);
        }
      } catch (_e) {}
      let s = String(dateStr).trim();
      s = s.replace('T', ' ');
      if (/^\\d{4}-\\d{2}-\\d{2} \\d{2}:\\d{2}$/.test(s)) s += ':00';
      if (s.indexOf('.') !== -1) s = s.split('.')[0];
      return s.substring(0, 19);
    },
  },
};
</script>

<style scoped>
@media print {
  .print-hide {
    display: none !important;
  }
  aside {
    display: none;
  }
  
  header {
    background: white;
    border: none;
  }
  
  button {
    display: none !important;
  }
  
  main {
    padding: 0;
  }
  
  section {
    page-break-inside: avoid;
    margin-bottom: 2rem;
  }
  
  table {
    width: 100%;
    border-collapse: collapse;
  }
  
  th, td {
    border: 1px solid #000;
    padding: 8px;
  }
}
</style>
