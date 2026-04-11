<script setup lang="ts">
import { cn } from '@/lib/utils';
import * as icons from 'lucide-vue-next';
import { computed } from 'vue';

interface Props {
    name: string;
    class?: string;
    size?: number | string;
    color?: string;
    strokeWidth?: number | string;
}

const props = withDefaults(defineProps<Props>(), {
    class: '',
    size: 16,
    strokeWidth: 2,
});

const className = computed(() => cn('h-4 w-4', props.class));

const aliasMap: Record<string, string> = {
    dashboard: 'LayoutDashboard',
    menu: 'Menu',
    menu_open: 'PanelLeftClose',
    people: 'Users',
    payments: 'CreditCard',
    inventory: 'Boxes',
    store: 'Store',
    person: 'User',
    local_shipping: 'Truck',
    account_balance: 'Landmark',
    book: 'BookOpen',
    archive: 'Archive',
    admin_panel_settings: 'Shield',
    settings: 'Settings',
    share: 'Share2',
    logout: 'LogOut',
    account_circle: 'UserCircle',
    report: 'AlertTriangle',
    receipt_long: 'Receipt',
    assessment: 'BarChart3',
    swap_horiz: 'ArrowLeftRight',
    print: 'Printer',
    download: 'Download',
    preview: 'Eye',
    light_mode: 'Sun',
    dark_mode: 'Moon',
    money_off: 'TrendingDown',
};

function toPascalCase(value: string): string {
    if (/[A-Z]/.test(value)) return value;
    return value
        .replace(/[-_]+/g, ' ')
        .split(' ')
        .filter(Boolean)
        .map((word) => word.charAt(0).toUpperCase() + word.slice(1))
        .join('');
}

const icon = computed(() => {
    const alias = aliasMap[props.name] ?? props.name;
    const iconName = toPascalCase(alias);
    return (icons as Record<string, any>)[iconName];
});
</script>

<template>
    <component :is="icon" :class="className" :size="size" :stroke-width="strokeWidth" :color="color" />
</template>
