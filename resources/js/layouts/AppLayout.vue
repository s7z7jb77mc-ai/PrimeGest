<script setup lang="ts">
import { ref } from 'vue';
import { Link } from '@inertiajs/vue3';

const _showingNavigationDropdown = ref(false);
</script>

<template>
    <div class="min-h-screen bg-gray-50">
        <!-- Navigation Bar -->
        <nav class="bg-white border-b border-gray-100">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex justify-between h-16">
                    <div class="flex">
                        <div class="flex items-center">
                            <Link href="/dashboard" class="text-lg font-semibold text-gray-800">
                                PrimeGest
                            </Link>
                        </div>
                    </div>
                </div>
            </div>
        </nav>

        <!-- Page Content -->
        <main class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
            <slot />
        </main>
    </div>
</template>
