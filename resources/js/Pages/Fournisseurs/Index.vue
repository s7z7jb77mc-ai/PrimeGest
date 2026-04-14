<script setup lang="ts">
import { onMounted } from 'vue'
import { router } from '@inertiajs/vue3'

onMounted(() => {
  router.replace('/tiers')
})
</script>

<template>
  <div class="p-6">Redirection...</div>
</template>
