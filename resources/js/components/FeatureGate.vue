<template>
    <slot v-if="allowed" />

    <template v-else-if="mode === 'block'">
        <div class="feature-gate-block">
            <span class="feature-gate-block__icon">🔒</span>
            <p class="feature-gate-block__title">Fonctionnalité {{ planLabel }} requise</p>
            <p class="feature-gate-block__desc">{{ description }}</p>
            <a :href="`/abonnement?plan=${requiredPlan}`" class="feature-gate-block__btn">
                Passer au plan {{ planLabel }} →
            </a>
        </div>
    </template>

    <template v-else-if="mode === 'inline'">
        <a :href="`/abonnement?plan=${requiredPlan}`" class="feature-gate-inline" :title="description">
            🔒 {{ planLabel }}
        </a>
    </template>
    <!-- mode === 'hide' : rien affiché -->
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { usePlanStore } from '@/stores/usePlanStore'

const props = withDefaults(defineProps<{
    feature: string
    mode?:   'block' | 'inline' | 'hide'
}>(), { mode: 'block' })

const REQUIRED_PLAN: Record<string, 'premium' | 'pro'> = {
    succursales:    'pro',
    exports:        'premium',
    dette_tracking: 'premium',
    reductions:     'premium',
}

const DESCRIPTIONS: Record<string, string> = {
    succursales:    'Les succursales sont disponibles uniquement avec le plan Pro (10$/mois).',
    exports:        "L'export PDF/Excel est disponible à partir du plan Premium (7$/mois).",
    dette_tracking: 'Le suivi des dettes et créances est disponible à partir du plan Premium (7$/mois).',
    reductions:     'Les réductions sont disponibles à partir du plan Premium (7$/mois).',
}

const planStore    = usePlanStore()
const allowed      = computed(() => planStore.can(props.feature))
const requiredPlan = computed(() => REQUIRED_PLAN[props.feature] ?? 'premium')
const planLabel    = computed(() => requiredPlan.value === 'pro' ? 'Pro' : 'Premium')
const description  = computed(() => DESCRIPTIONS[props.feature] ?? 'Cette fonctionnalité nécessite un plan supérieur.')
</script>

<style scoped>
.feature-gate-block {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.5rem;
    padding: 1.5rem;
    border: 1px dashed #d1c4a8;
    border-radius: 1rem;
    background: #fdfaf4;
    text-align: center;
}

.feature-gate-block__icon  { font-size: 1.5rem; line-height: 1; }
.feature-gate-block__title { font-weight: 700; color: #1d160f; font-size: 0.95rem; margin: 0; }
.feature-gate-block__desc  { font-size: 0.82rem; color: #7a6040; max-width: 28rem; margin: 0; }

.feature-gate-block__btn {
    margin-top: 0.25rem;
    display: inline-block;
    padding: 0.55rem 1.25rem;
    border-radius: 999px;
    background: linear-gradient(135deg, #d69a1a, #ffd070);
    color: #19130c;
    font-weight: 700;
    font-size: 0.85rem;
    text-decoration: none;
    transition: opacity 0.18s;
}
.feature-gate-block__btn:hover { opacity: 0.88; }

.feature-gate-inline {
    display: inline-flex;
    align-items: center;
    gap: 0.2rem;
    font-size: 0.75rem;
    font-weight: 600;
    color: #b9780f;
    text-decoration: none;
    padding: 0.2rem 0.5rem;
    border-radius: 999px;
    border: 1px solid #e8c97a;
    background: #fdf6e3;
    cursor: pointer;
    transition: background 0.15s;
}
.feature-gate-inline:hover { background: #faeec7; }
</style>
