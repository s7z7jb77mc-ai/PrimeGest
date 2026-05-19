<script setup lang="ts">
import { computed } from 'vue';
import { cn } from '@/lib/utils';

// ✅ Imports individuels — réduit le bundle de 532KB à ~15KB
import {
    LayoutDashboard,
    Menu,
    PanelLeftClose,
    Users,
    CreditCard,
    Boxes,
    Store,
    User,
    Truck,
    Landmark,
    BookOpen,
    Archive,
    Shield,
    Settings,
    Share2,
    LogOut,
    UserCircle,
    AlertTriangle,
    Receipt,
    BarChart3,
    ArrowLeftRight,
    Printer,
    Download,
    Eye,
    Sun,
    Moon,
    TrendingDown,
    Plus,
    Pencil,
    Trash2,
    Check,
    X,
    Search,
    ChevronDown,
    ChevronUp,
    ChevronLeft,
    ChevronRight,
    RefreshCw,
    AlertCircle,
    Info,
    CheckCircle,
    XCircle,
    FileText,
    FilePlus,
    Package,
    ShoppingCart,
    DollarSign,
    TrendingUp,
    Calendar,
    Clock,
    Bell,
    Home,
    Building2,
    MapPin,
    Phone,
    Mail,
    Lock,
    Unlock,
    Key,
    PieChart,
} from 'lucide-vue-next';

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

const iconMap: Record<string, any> = {
    // Alias Material Icons → Lucide
    dashboard:            LayoutDashboard,
    menu:                 Menu,
    menu_open:            PanelLeftClose,
    people:               Users,
    payments:             CreditCard,
    inventory:            Boxes,
    store:                Store,
    person:               User,
    local_shipping:       Truck,
    account_balance:      Landmark,
    book:                 BookOpen,
    archive:              Archive,
    admin_panel_settings: Shield,
    settings:             Settings,
    share:                Share2,
    logout:               LogOut,
    account_circle:       UserCircle,
    report:               AlertTriangle,
    receipt_long:         Receipt,
    assessment:           BarChart3,
    swap_horiz:           ArrowLeftRight,
    print:                Printer,
    download:             Download,
    preview:              Eye,
    light_mode:           Sun,
    dark_mode:            Moon,
    money_off:            TrendingDown,
    donut_large:          PieChart,
    // Noms Lucide directs
    LayoutDashboard,
    Menu,
    PanelLeftClose,
    Users,
    CreditCard,
    Boxes,
    Store,
    User,
    Truck,
    Landmark,
    BookOpen,
    Archive,
    Shield,
    Settings,
    Share2,
    LogOut,
    UserCircle,
    AlertTriangle,
    Receipt,
    BarChart3,
    ArrowLeftRight,
    Printer,
    Download,
    Eye,
    Sun,
    Moon,
    TrendingDown,
    Plus,
    Pencil,
    Trash2,
    Check,
    X,
    Search,
    ChevronDown,
    ChevronUp,
    ChevronLeft,
    ChevronRight,
    RefreshCw,
    AlertCircle,
    Info,
    CheckCircle,
    XCircle,
    FileText,
    FilePlus,
    Package,
    ShoppingCart,
    DollarSign,
    TrendingUp,
    Calendar,
    Clock,
    Bell,
    Home,
    Building2,
    MapPin,
    Phone,
    Mail,
    Lock,
    Unlock,
    Key,
    PieChart,
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
    if (iconMap[props.name]) return iconMap[props.name];
    const pascal = toPascalCase(props.name);
    return iconMap[pascal] ?? null;
});
</script>

<template>
    <component
        v-if="icon"
        :is="icon"
        :class="className"
        :size="size"
        :stroke-width="strokeWidth"
        :color="color"
    />
    <span v-else :class="className" style="display:inline-block;"></span>
</template>