<script setup>
import { Head, Link } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'

// PrimeVue
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import Tag from 'primevue/tag'
import Button from 'primevue/button'

defineOptions({ layout: AppLayout })

const props = defineProps({
  sessions: Object // paginator
})

function toHHmm(t){ return String(t||'').slice(0,5) }
</script>

<template>
  <Head title="Buổi dạy của tôi" />

  <h1 class="text-xl md:text-2xl font-heading font-semibold mb-3">Buổi dạy của tôi</h1>

  <div class="rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 p-2">
    <DataTable :value="props.sessions?.data ?? []" :paginator="true"
               :rows="props.sessions?.per_page ?? 20"
               :totalRecords="props.sessions?.total ?? 0"
               :first="Math.max(0, (props.sessions?.from ?? 1) - 1)"
               dataKey="id" size="small">
      <Column field="date" header="Ngày" style="width:140px" />
      <Column header="Giờ" style="width:160px">
        <template #body="{ data }">{{ toHHmm(data.start_time) }} - {{ toHHmm(data.end_time) }}</template>
      </Column>
      <Column header="Lớp">
        <template #body="{ data }">{{ data.classroom?.name ?? '—' }}</template>
      </Column>
      <Column header="" style="width:160px">
        <template #body="{ data }">
          <Link :href="route('teacher.attendance.show', { session: data.id })"
                class="px-3 py-1.5 rounded border border-emerald-300 text-emerald-700 hover:bg-emerald-50
                       dark:border-emerald-700 dark:text-emerald-300 dark:hover:bg-emerald-900/20">
            <i class="pi pi-check-square mr-1" /> Điểm danh
          </Link>
        </template>
      </Column>

      <template #empty>
        <div class="p-6 text-center text-slate-500 dark:text-slate-400">
          Chưa có buổi dạy nào.
        </div>
      </template>
    </DataTable>
  </div>
</template>
