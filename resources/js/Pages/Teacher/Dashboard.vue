<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { Head } from '@inertiajs/vue3'
defineOptions({ layout: AppLayout })
const props = defineProps({ title: String, note: String })
</script>
<template>
  <Head :title="props.title || 'Teacher dashboard'" />
  <div class="p-4 rounded-lg border bg-white dark:bg-slate-800">
    <h1 class="text-xl font-semibold mb-2">{{ props.title || 'Teacher dashboard' }}</h1>
    <p class="text-slate-600 dark:text-slate-300">{{ props.note || 'Đang phát triển' }}</p>
  </div>
</template>
