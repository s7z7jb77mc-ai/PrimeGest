<script setup lang="ts">
import { ref } from 'vue';
import { Link } from '@inertiajs/vue3';

const showingNavigationDropdown = ref(false);
</script>

<template>
    <div>
        <!-- Navigation Bar -->
        <nav class="bg-white border-b border-gray-100">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex justify-between h-16">
                    <div class="flex">
                        <div class="flex items-center">
                            <Link href="/dashboard" class="text-lg font-semibold text-gray-800">
                                Dashboard
                            </Link>
                        </div>
                    </div>
                    <div class="flex items-center">
                        <button @click="showingNavigationDropdown = !showingNavigationDropdown" class="inline-flex items-center px-3 py-2 text-gray-500 hover:text-gray-700">
                            Menu
                        </button>
                    </div>
                </div>
            </div>
        </nav>

        <!-- Page Header -->
        <header class="bg-white shadow">
            <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
                <slot name="header" />
            </div>
        </header>

        <!-- Page Content -->
        <main>
            <slot />
        </main>
    </div>
</template>
