<template>
  <div class="bg-gray-100 text-gray-900 min-h-screen" :key="lang">
    <div class="max-w-4xl mx-auto p-6">
      <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold">⚙️ {{ t('settings') }}</h1>
        <button type="button" @click="goDashboard" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700">
          {{ t('dashboard') }}
        </button>
      </div>


      <!-- Formulaire des paramètres -->
      <form @submit.prevent="demanderConfirmation">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label class="block mb-1">Nom de l'entreprise</label>
            <input v-model="form.nom_entreprise" class="input" type="text" required />
          </div>
          <div>
            <label class="block mb-1">Adresse</label>
            <input v-model="form.adresse" class="input" type="text" required />
          </div>
          <div>
            <label class="block mb-1">Email</label>
            <input v-model="form.email" class="input" type="email" required />
          </div>
          <div>
            <label class="block mb-1">Téléphone</label>
            <input v-model="form.telephone" class="input" type="text" required />
          </div>
          <div>
            <label class="block mb-1">Devise</label>
            <select v-model="form.devise" class="input">
              <option value="USD">USD</option>
              <option value="CDF">CDF</option>
              <option value="RWF">RWF</option>
            </select>
          </div>
          <div>
            <label class="block mb-1">Langue</label>
            <select v-model="form.langue" class="input">
              <option value="fr">Français</option>
              <option value="en">English</option>
            </select>
          </div>
          <div>
            <label class="block mb-1">RCCM</label>
            <input v-model="form.rccm" class="input" type="text" />
          </div>
          <div>
            <label class="block mb-1">Identifiant national</label>
            <input v-model="form.identifiant_national" class="input" type="text" />
          </div>
          <div>
            <label class="block mb-1">Numéro d’impôt</label>
            <input v-model="form.numero_impot" class="input" type="text" />
          </div>
          <div>
            <label class="block mb-1">TVA (%)</label>
            <input v-model.number="form.tva" class="input" type="number" step="0.01" min="0" max="100" />
          </div>
          <div>
            <label class="block mb-1">Réduction accordée (%)</label>
            <input v-model.number="form.reduction_accordee" class="input" type="number" step="0.01" min="0" max="100" />
          </div>
          <div>
            <label class="block mb-1">Logo (PNG/JPG)</label>
            <input
              type="file"
              accept="image/*"
              class="input"
              ref="logoInput"
              @change="handleLogoChange"
            />
            <div v-if="currentLogoUrl" class="mt-2">
              <img :src="currentLogoUrl" alt="Logo" class="h-12" />
            </div>
          </div>
          <div>
            <label class="block mb-1">Position du logo (facture)</label>
            <select v-model="form.logo_position" class="input">
              <option value="left">Gauche</option>
              <option value="center">Centre</option>
              <option value="right">Droite</option>
            </select>
          </div>
          <div class="md:col-span-2">
            <label class="block mb-1">Message de remerciement</label>
            <textarea v-model="form.message_remerciement" class="input" rows="3"></textarea>
          </div>
          <div class="md:col-span-2">
            <label class="block mb-2">Votre entreprise possède t-elle plusieurs points de vente ou succursales ?</label>
            <div class="flex items-center gap-4">
              <label class="flex items-center gap-2">
                <input
                  type="checkbox"
                  :checked="form.multi_succursales === true"
                  @change="form.multi_succursales = true"
                />
                Oui
              </label>
              <label class="flex items-center gap-2">
                <input
                  type="checkbox"
                  :checked="form.multi_succursales === false"
                  :disabled="hasSuccursales"
                  @change="form.multi_succursales = false"
                />
                Non
              </label>
            </div>
          </div>
          <!-- Réduction accordée ajoutée -->
        </div>
        <div v-if="form.multi_succursales" class="mt-4 flex items-center gap-3">
          <button type="button" @click="openSuccursaleModal" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700">
            Nouvelle succursale
          </button>
        </div>

        <div v-if="form.multi_succursales" class="mt-4">
          <div class="bg-white rounded-lg shadow p-4">
            <h3 class="font-semibold mb-2">Succursales</h3>
            <ul v-if="succursales.length" class="text-sm text-gray-700 space-y-1">
              <li v-for="s in succursales" :key="s.id">
                {{ s.nom }}
                <span class="text-gray-500">
                  - {{ s.adresse || '-' }} - Manager: {{ s.manager || '-' }}
                  - <span :class="s.active ? 'text-green-700' : 'text-gray-500'">{{ s.active ? 'Actif' : 'Inactif' }}</span>
                </span>
              </li>
            </ul>
            <div v-else class="text-gray-400 text-sm">Aucune succursale enregistrée.</div>
          </div>
        </div>
        <button type="submit" class="bg-green-600 mt-4 text-white px-4 py-2 rounded-lg hover:bg-green-700">
           Enregistrer
        </button>
      </form>
    </div>

    <!-- Modal de confirmation -->
    <div v-if="showModal" class="fixed inset-0 bg-black bg-opacity-60 flex justify-center items-center z-50">
      <div class="bg-white dark:bg-gray-800 p-6 rounded-lg w-96">
        <h2 class="text-xl font-bold mb-4 text-center">Confirmation Super Admin</h2>
        <input
          v-model="adminPassword"
          type="password"
          placeholder="Entrez le mot de passe Super Admin"
          class="w-full p-2 border rounded mb-4 dark:bg-gray-700 dark:text-white"
        />
        <div class="flex justify-between">
          <button type="button" @click="showModal = false" class="bg-gray-400 text-white px-4 py-2 rounded hover:bg-gray-500">
            Annuler
          </button>
          <button type="button" @click="enregistrer" class="bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700">
            Confirmer
          </button>
        </div>
      </div>
    </div>

    <!-- Modal succursale -->
    <div v-if="succursaleModalOpen" class="fixed inset-0 bg-black bg-opacity-60 flex justify-center items-center z-50">
      <div class="bg-white p-6 rounded-lg w-96">
        <h2 class="text-xl font-bold mb-4 text-center">Nouvelle succursale</h2>
        <div class="space-y-3">
          <div>
            <label class="block mb-1">Nom de la succursale</label>
            <input v-model="succursaleForm.nom" class="input" type="text" />
            <p v-if="succursaleErrors.nom" class="text-sm text-red-600">{{ succursaleErrors.nom[0] }}</p>
          </div>
          <div>
            <label class="block mb-1">Adresse</label>
            <input v-model="succursaleForm.adresse" class="input" type="text" />
          </div>
          <div>
            <label class="block mb-1">Nom manager (employé)</label>
            <select v-model="succursaleForm.manager_user_id" class="input" required>
              <option value="">-- Sélectionner --</option>
              <option v-for="m in props.managers" :key="m.id" :value="m.id">
                {{ m.employe_nom || m.name }} ({{ m.role }})
              </option>
            </select>
            <p v-if="succursaleErrors.manager_user_id" class="text-sm text-red-600">{{ succursaleErrors.manager_user_id[0] }}</p>
          </div>
        </div>
        <div class="mt-4 flex justify-between">
          <button type="button" @click="closeSuccursaleModal" class="bg-gray-400 text-white px-4 py-2 rounded hover:bg-gray-500">
            Annuler
          </button>
          <button type="button" @click="saveSuccursale" class="bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700">
            OK
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted, computed } from "vue";
import { setLang, t as _t } from "@/lang";
import { useLang } from '@/composables/useLang'

const props = defineProps({
  parametre: {
    type: Object,
    default: null,
  },
  managers: {
    type: Array,
    default: () => [],
  },
  succursales: {
    type: Array,
    default: () => [],
  },
  has_succursales: {
    type: Boolean,
    default: false,
  },
});

const form = ref({
  nom_entreprise: "",
  adresse: "",
  email: "",
  telephone: "",
  devise: "CDF",
  langue: "fr",
  rccm: "",
  identifiant_national: "",
  numero_impot: "",
  tva: 0,
  reduction_accordee: 0,
  message_remerciement: "",
  logo_position: "left",
  multi_succursales: false,
});
const logoFile = ref(null);
const logoSelected = ref(false);
const logoInput = ref(null);
const currentLogoUrl = ref("");
const showModal = ref(false);
const adminPassword = ref("");
const superAdminPassword = ref("");
const nouveauMotDePasse = ref("");
const t = _t
const lang = useLang()

const handleLogoChange = (e) => {
  const f = e?.target?.files?.[0] || null;
  logoFile.value = f;
  logoSelected.value = !!f;
  if (f) {
    currentLogoUrl.value = URL.createObjectURL(f);
  }
};

// Aller au dashboard
const goDashboard = () => {
  window.location.href = "/dashboard";
};


// Charger les paramètres depuis le serveur
onMounted(() => {
  const savedSuperAdminPassword = localStorage.getItem("superAdminPassword");
  if (savedSuperAdminPassword) {
    superAdminPassword.value = savedSuperAdminPassword;
  }

  if (props.parametre) {
    form.value = {
      nom_entreprise: props.parametre.nom_entreprise || "",
      adresse: props.parametre.adresse || "",
      email: props.parametre.email || "",
      telephone: props.parametre.telephone || "",
      devise: props.parametre.devise || "CDF",
      langue: props.parametre.langue || "fr",
      rccm: props.parametre.rccm || "",
      identifiant_national: props.parametre.identifiant_national || "",
      numero_impot: props.parametre.numero_impot || "",
      tva: props.parametre.tva ?? 0,
      reduction_accordee: props.parametre.reduction_accordee ?? 0,
      message_remerciement: props.parametre.message_remerciement || "",
      logo_position: props.parametre.logo_position || "left",
      multi_succursales: !!props.parametre.multi_succursales,
    };
    currentLogoUrl.value = props.parametre.logo_url || "";
  }
  if (hasSuccursales.value) {
    form.value.multi_succursales = true;
  }
  if (form.value.langue) {
    setLang(form.value.langue);
  }
});

// Définir le mot de passe Super Admin
const _definirMotDePasseSuperAdmin = () => {
  if (!nouveauMotDePasse.value) {
    alert("❌ Veuillez entrer un mot de passe.");
    return;
  }
  superAdminPassword.value = nouveauMotDePasse.value;
  localStorage.setItem("superAdminPassword", nouveauMotDePasse.value);
  alert("✅ Mot de passe Super Admin défini avec succès !");
  nouveauMotDePasse.value = "";
};

// Réinitialiser le mot de passe Super Admin (nécessite confirmation)
const _resetSuperAdminPassword = () => {
  const answer = prompt("Pour réinitialiser le mot de passe Super Admin, tapez RESET et cliquez sur OK.");
  if (answer === "RESET") {
    localStorage.removeItem("superAdminPassword");
    superAdminPassword.value = "";
    alert("✅ Mot de passe Super Admin réinitialisé. Veuillez en définir un nouveau.");
  } else {
    alert("❌ Réinitialisation annulée.");
  }
};

// Valider les champs avant enregistrement
const validerFormulaire = () => {
  if (form.value.email && !form.value.email.includes("@")) {
    alert("❌ Veuillez entrer un email valide.");
    return false;
  }
  if (form.value.tva < 0 || form.value.tva > 100) {
    alert("❌ La TVA doit être entre 0 et 100.");
    return false;
  }
  if (form.value.reduction_accordee < 0 || form.value.reduction_accordee > 100) {
    alert("❌ La réduction accordée doit être entre 0 et 100.");
    return false;
  }
  return true;
};

const demanderConfirmation = () => {
  if (!validerFormulaire()) return;
  showModal.value = true;
};

// Enregistrement avec validation Super Admin
const enregistrer = async () => {
  if (!adminPassword.value) {
    alert("❌ Veuillez entrer le mot de passe Super Admin !");
    return;
  }

  try {
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

    const body = new FormData();
    Object.entries(form.value).forEach(([key, value]) => {
      body.append(key, value ?? "");
    });
    body.append("superPassword", adminPassword.value);
    const fileFromInput = logoInput.value?.files?.[0] || null;
    const fileToSend = fileFromInput || logoFile.value;
    logoSelected.value = !!fileToSend;
    if (fileToSend) {
      body.append("logo", fileToSend);
    }
    body.append("logo_selected", logoSelected.value ? "1" : "0");

    const response = await fetch("/parametres", {
      method: "POST",
      headers: {
        "X-CSRF-TOKEN": csrfToken || "",
        "X-Requested-With": "XMLHttpRequest",
        "Accept": "application/json",
      },
      credentials: "same-origin",
      body,
    });

    const data = await response.text();
    
    let result;
    try {
      result = JSON.parse(data);
    } catch (_e) {
      console.error("Réponse non JSON:", data);
      if (response.status === 419) {
        alert("❌ Session expirée. Recharge la page puis réessaie.");
      } else {
        alert("❌ Erreur: Réponse du serveur invalide");
      }
      return;
    }
    
    if (response.ok) {
      alert("✅ Paramètres enregistrés avec succès !");
      showModal.value = false;
      adminPassword.value = "";
      if (result.logo_url) {
        currentLogoUrl.value = result.logo_url;
      }
      if (form.value.langue) {
        setLang(form.value.langue);
      }
      logoFile.value = null;
      logoSelected.value = false;
      if (logoInput.value) {
        logoInput.value.value = "";
      }
      if (form.value.multi_succursales) {
        window.location.reload();
      }
    } else {
      let errorMsg = result.errors?.superPassword?.[0] || result.errors?.general?.[0] || result.message || "";
      if (!errorMsg && result.errors) {
        const firstKey = Object.keys(result.errors)[0];
        if (firstKey) {
          const firstArr = result.errors[firstKey];
          errorMsg = Array.isArray(firstArr) ? firstArr[0] : String(firstArr);
        }
      }
      if (!errorMsg) {
        errorMsg = "Erreur lors de l'enregistrement";
      }
      alert("❌ " + errorMsg);
    }
  } catch (error) {
    alert("❌ Erreur: " + error.message);
  }
};

const succursaleModalOpen = ref(false);
const succursaleForm = ref({
  nom: "",
  adresse: "",
  manager_user_id: "",
});
const succursaleErrors = ref({});
const succursales = ref(props.succursales || []);
const hasSuccursales = computed(() => props.has_succursales || succursales.value.length > 0);

const openSuccursaleModal = () => {
  succursaleErrors.value = {};
  succursaleForm.value = { nom: "", adresse: "", manager_user_id: "" };
  succursaleModalOpen.value = true;
};

const closeSuccursaleModal = () => {
  succursaleModalOpen.value = false;
};

const saveSuccursale = async () => {
  succursaleErrors.value = {};
  try {
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
    const response = await fetch("/succursales", {
      method: "POST",
      headers: {
        "X-CSRF-TOKEN": csrfToken || "",
        "X-Requested-With": "XMLHttpRequest",
        "Accept": "application/json",
        "Content-Type": "application/json",
      },
      credentials: "same-origin",
      body: JSON.stringify(succursaleForm.value),
    });
    const text = await response.text();
    let data = {};
    try {
      data = JSON.parse(text);
    } catch (_e) {
      data = {};
    }
    if (!response.ok) {
      succursaleErrors.value = data.errors || {};
      const msg = data.message || (data.errors ? null : text);
      if (msg) {
        alert("❌ " + msg);
      }
      return;
    }

    succursales.value.push(data.succursale);
    succursaleModalOpen.value = false;
  } catch (_e) {
    alert("❌ Erreur: " + e.message);
  }
};
</script>

<style scoped>
.input {
  @apply w-full border border-gray-300 rounded-lg p-2 focus:outline-none focus:ring-2 focus:ring-blue-400;
}
.dark .input {
  @apply bg-gray-700 text-white border-gray-600;
}
</style>
