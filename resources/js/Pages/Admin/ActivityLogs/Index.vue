<script setup>
import { Head } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
defineOptions({ layout: AppLayout })
const props = defineProps({ logs: Object })
</script>

<template>
  <Head title="Activity Logs" />
  <h1 class="text-xl font-semibold mb-3">Activity Logs</h1>
  <div class="space-y-2">
    <div v-for="l in props.logs.data" :key="l.id" class="p-3 rounded border border-slate-200 dark:border-slate-700">
      <div class="text-sm">
        <strong>{{ l.action }}</strong>
        — by <span class="font-medium">{{ l.actor?.name ?? 'System' }}</span>
        — target: {{ l.target_type }}#{{ l.target_id }}
      </div>
      <div class="text-xs text-slate-500">IP: {{ l.ip }} · UA: {{ l.user_agent }}</div>
      <pre class="text-xs bg-slate-50 dark:bg-slate-800 p-2 rounded mt-1 overflow-auto">{{ l.meta }}</pre>
      <div class="text-xs text-slate-500 mt-1">{{ l.created_at }}</div>
    </div>
  </div>
</template>
