<template>
    <AppLayout>
        <div class="max-w-5xl mx-auto py-10 px-4">
            <h1 class="text-xl font-medium mb-8">Administration — Plans</h1>

            <!-- Paiements en attente -->
            <div class="mb-10" v-if="subscriptions.length">
                <h2 class="text-base font-medium mb-4 text-amber-600">Paiements en attente ({{ subscriptions.length }})</h2>
                <div class="border rounded-xl overflow-hidden">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50 text-gray-500 text-xs">
                            <tr>
                                <th class="text-left p-3">Entreprise</th>
                                <th class="text-left p-3">Plan</th>
                                <th class="text-left p-3">Montant</th>
                                <th class="text-left p-3">Référence</th>
                                <th class="text-left p-3">Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="s in subscriptions" :key="s.id" class="border-t">
                                <td class="p-3">{{ s.entreprise }}</td>
                                <td class="p-3"><span class="bg-blue-100 text-blue-700 px-2 py-0.5 rounded text-xs">{{ s.plan }}</span></td>
                                <td class="p-3">{{ s.amount }} $</td>
                                <td class="p-3 font-mono text-xs">{{ s.payment_reference }}</td>
                                <td class="p-3 text-gray-400">{{ s.created_at }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Toutes les entreprises -->
            <h2 class="text-base font-medium mb-4">Toutes les entreprises</h2>
            <div class="border rounded-xl overflow-hidden">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 text-gray-500 text-xs">
                        <tr>
                            <th class="text-left p-3">Entreprise</th>
                            <th class="text-left p-3">Plan actuel</th>
                            <th class="text-left p-3">Expire le</th>
                            <th class="text-left p-3">Activer</th>
                            <th class="text-left p-3">Durée</th>
                            <th class="text-left p-3">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="e in entreprises" :key="e.id" class="border-t">
                            <td class="p-3">{{ e.name }}</td>
                            <td class="p-3">
                                <span :class="{
                                    'bg-gray-100 text-gray-600': e.plan === 'free',
                                    'bg-blue-100 text-blue-700': e.plan === 'premium',
                                    'bg-purple-100 text-purple-700': e.plan === 'pro'
                                }" class="px-2 py-0.5 rounded text-xs">{{ e.plan }}</span>
                            </td>
                            <td class="p-3 text-gray-400 text-xs">{{ e.plan_expires_at ?? '—' }}</td>
                            <td class="p-3">
                                <select v-model="forms[e.id].plan" class="border rounded px-2 py-1 text-xs">
                                    <option value="premium">Premium</option>
                                    <option value="pro">Pro</option>
                                    <option value="free">Free</option>
                                </select>
                            </td>
                            <td class="p-3">
                                <select v-model="forms[e.id].duration" class="border rounded px-2 py-1 text-xs">
                                    <option value="1">1 mois</option>
                                    <option value="3">3 mois</option>
                                    <option value="6">6 mois</option>
                                    <option value="12">12 mois</option>
                                </select>
                            </td>
                            <td class="p-3 flex gap-2">
                                <button @click="activate(e)"
                                    class="bg-blue-500 text-white text-xs px-3 py-1 rounded hover:bg-blue-600">
                                    Activer
                                </button>
                                <button @click="downgrade(e)"
                                    class="border text-xs px-3 py-1 rounded hover:bg-gray-50 text-gray-500">
                                    Free
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import AppLayout from '@/layouts/AppLayout.vue'
import { useForm } from '@inertiajs/vue3'
import { reactive } from 'vue'

const props = defineProps({ entreprises: Array, subscriptions: Array })

const forms = reactive({})
props.entreprises.forEach(e => {
    forms[e.id] = { plan: 'premium', duration: '1' }
})

const activate = (e) => {
    useForm({
        plan: forms[e.id].plan,
        duration: forms[e.id].duration,
    }).post(route('admin.plans.activate', e.id))
}

const downgrade = (e) => {
    useForm({}).post(route('admin.plans.downgrade', e.id))
}
</script>
