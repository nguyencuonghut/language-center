<script setup>
import { ref, reactive } from 'vue'
import { Head, router, Link } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'

// PrimeVue
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import Select from 'primevue/select'
import Button from 'primevue/button'
import Tag from 'primevue/tag'
import InputText from 'primevue/inputtext'

defineOptions({ layout: AppLayout })

const props = defineProps({
  session: Object,   // {id, class_id, date, start_time, end_time}
  classroom: Object, // {id, code, name}
  students: Array    // [{student_id, code, name, status, note}]
})

const rows = ref((props.students || []).map(s => ({
  student_id: s.student_id,
  code: s.code,
  name: s.name,
  status: s.status || 'present',
  note: s.note || '',
})))

const statusOptions = [
  { label: 'Có mặt', value: 'present' },
  { label: 'Vắng',   value: 'absent' },
  { label: 'Muộn',   value: 'late' },
  { label: 'Xin phép', value: 'excused' },
]

function save(){
  const items = rows.value.map(r => ({
    student_id: r.student_id,
    status: r.status,
    note: r.note || null
  }))
  router.post(route('manager.attendance.store', { session: props.session.id }), { items }, {
    preserveScroll: true
  })
}
function toHHmm(t){ return String(t||'').slice(0,5) }
</script>

<template>
  <Head :title="`Điểm danh - ${classroom?.name || ''} - Manager`" />

  <div class="mb-3 flex items-center justify-between">
    <div>
      <h1 class="text-xl md:text-2xl font-heading font-semibold">Điểm danh - Manager</h1>
      <div class="text-sm text-slate-500 dark:text-slate-400">
        Lớp: <span class="font-medium text-slate-900 dark:text-slate-100">{{ classroom?.name }}</span> —
        {{ session.date }} ({{ toHHmm(session.start_time) }} - {{ toHHmm(session.end_time) }})
      </div>
    </div>
    <div class="flex gap-2">
      <Link :href="route('manager.attendance.index')"
            class="px-3 py-1.5 rounded border border-slate-300 dark:border-slate-600 hover:bg-slate-100 dark:hover:bg-slate-800">
        ← Danh sách buổi học
      </Link>
      <Button label="Lưu" icon="pi pi-check" @click="save" />
    </div>
  </div>

  <div class="rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 p-2">
    <DataTable :value="rows" dataKey="student_id" size="small">
      <Column field="code" header="Mã" style="width:140px" />
      <Column field="name" header="Học viên" />
      <Column header="Trạng thái" style="width:220px">
        <template #body="{ data }">
          <Select v-model="data.status" :options="statusOptions" optionLabel="label" optionValue="value" class="w-full" />
        </template>
      </Column>
      <Column header="Ghi chú" style="width:300px">
        <template #body="{ data }">
          <InputText v-model="data.note" placeholder="Nhập ghi chú..." class="w-full" />
        </template>
      </Column>

      <template #empty>
        <div class="p-6 text-center text-slate-500 dark:text-slate-400">
          Chưa có học viên nào trong lớp.
        </div>
      </template>
    </DataTable>
  </div>
</template>
