import { defineStore } from 'pinia'
import { usePage } from '@inertiajs/vue3'
import { computed } from 'vue'

export const usePlanStore = defineStore('plan', () => {
    const page = usePage()

    const plan = computed(() => page.props.plan ?? 'free')
    const limits = computed(() => page.props.plan_limits ?? {})

    const can = (feature) => {
        const val = limits.value[feature]
        if (val === undefined) return false
        if (typeof val === 'boolean') return val
        return val === -1 || val > 0
    }

    const isLimitReached = (feature, currentCount) => {
        const limit = limits.value[feature]
        if (limit === undefined || limit === -1) return false
        return currentCount >= limit
    }

    const getLimit = (feature) => {
        return limits.value[feature] ?? 0
    }

    const isPremium = computed(() => plan.value === 'premium' || plan.value === 'pro')
    const isPro = computed(() => plan.value === 'pro')
    const isFree = computed(() => plan.value === 'free')

    return {
        plan,
        limits,
        can,
        isLimitReached,
        getLimit,
        isPremium,
        isPro,
        isFree,
    }
})
