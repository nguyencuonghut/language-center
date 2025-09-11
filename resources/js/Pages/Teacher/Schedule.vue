<script setup>
import { ref } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'

// PrimeVue
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import DatePicker from 'primevue/datepicker'
import Button from 'primevue/button'
import Tag from 'primevue/tag'

defineOptions({ layout: AppLayout })

const props = defineProps({
  items: Array,
  filters: Object // { from, to }
})

/* ----- helpers ----- */
function toDdMmYyyy(d) {
  if (!d) return '—'
  const dt = new Date(String(d).replace(' ', 'T'))
  if (isNaN(dt.getTime())) {
    const [y,m,day] = String(d).split('-')
    if (y && m && day) return `${day.padStart(2,'0')}/${m.padStart(2,'0')}/${y}`
    return String(d)
  }
  const dd = String(dt.getDate()).padStart(2, '0')
  const mm = String(dt.getMonth() + 1).padStart(2, '0')
  const yy = dt.getFullYear()
  return `${dd}/${mm}/${yy}`
}
const hhmm = (t) => (t ? String(t).slice(0,5) : '—')
const statusSeverity = (s) => s === 'planned' ? 'info' : (s === 'moved' ? 'warning' : 'danger')
const substitutionSeverity = (isSub) => isSub ? 'warn' : 'success'


/* ----- local filter state ----- */
const from = ref(props.filters?.from ? new Date(props.filters.from) : new Date())
const to   = ref(props.filters?.to   ? new Date(props.filters.to)   : new Date(Date.now() + 14*86400000))

function toYmdLocal(d) {
  if (!d) return null
  const dt = new Date(d)
  return `${dt.getFullYear()}-${String(dt.getMonth()+1).padStart(2,'0')}-${String(dt.getDate()).padStart(2,'0')}`
}

function apply() {
  router.visit(route('teacher.schedule.index', {
    from: toYmdLocal(from.value),
    to:   toYmdLocal(to.value),
  }), { preserveState: true, preserveScroll: true })
}
</script>

<template>
  <Head title="Lịch dạy" />

  <div class="mb-3 flex flex-col md:flex-row md:items-center md:justify-between gap-3">
    <div>
      <h1 class="text-xl md:text-2xl font-heading font-semibold">Lịch dạy</h1>
      <div class="text-slate-500 dark:text-slate-400 text-sm">
        Khoảng ngày: {{ toDdMmYyyy(filters?.from) }} — {{ toDdMmYyyy(filters?.to) }}
      </div>
    </div>

    <div class="flex items-center gap-2">
      <DatePicker v-model="from" dateFormat="dd/mm/yy" showIcon iconDisplay="input" />
      <span>—</span>
      <DatePicker v-model="to" dateFormat="dd/mm/yy" showIcon iconDisplay="input" />
      <Button label="Lọc" icon="pi pi-filter" @click="apply" />
    </div>
  </div>

  <div class="rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 p-2">
    <DataTable
      :value="props.items || []"
      dataKey="id"
      size="small"
      responsiveLayout="scroll"
      :paginator="(props.items||[]).length > 15"
      :rows="15"
    >
      <Column header="Ngày" style="width: 140px">
        <template #body="{ data }">{{ toDdMmYyyy(data.date) }}</template>
      </Column>
      <Column header="Giờ" style="width: 120px">
        <template #body="{ data }">{{ hhmm(data.start_time) }}–{{ hhmm(data.end_time) }}</template>
      </Column>
      <Column header="Lớp">
        <template #body="{ data }">
          <div class="font-medium">{{ data.class_code }}</div>
          <div class="text-slate-500 text-sm">{{ data.class_name }}</div>
        </template>
      </Column>
      <Column header="Phòng" style="width: 200px">
        <template #body="{ data }">
          <span v-if="data.room_code">{{ data.room_code }} · {{ data.room_name }}</span>
          <span v-else class="text-slate-400">—</span>
        </template>
      </Column>
      <Column header="Chi nhánh" style="width: 200px">
        <template #body="{ data }">{{ data.branch_name ?? '—' }}</template>
      </Column>
      <Column header="Loại" style="width: 140px">
        <template #body="{ data }">
          <Tag :value="data.is_substitution ? 'Dạy thay' : 'Phân công'" :severity="substitutionSeverity(data.is_substitution)" />
        </template>
      </Column>
      <Column header="Trạng thái" style="width: 140px">
        <template #body="{ data }">
          <Tag :value="data.status" :severity="statusSeverity(data.status)" />
        </template>
      </Column>

      <template #empty>
        <div class="p-6 text-center text-slate-500 dark:text-slate-400">Không có buổi nào trong khoảng đã chọn.</div>
      </template>
    </DataTable>
  </div>
</template>
