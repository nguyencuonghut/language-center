<script setup>
import { reactive, ref, computed } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'

// PrimeVue
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import Select from 'primevue/select'
import Button from 'primevue/button'
import DatePicker from 'primevue/datepicker'
import InputText from 'primevue/inputtext'
import Tag from 'primevue/tag'

defineOptions({ layout: AppLayout })

const props = defineProps({
  substitutions: Object, // paginator
  filters: Object,       // {branch, class_id, teacher_id, date_from, date_to, q, perPage}
  branches: Array,       // [{id,name}]
  classrooms: Array,        // [{id,label,value}]
  teachers: Array        // [{id,label,value}]
})

/* -------- state -------- */
const state = reactive({
  branch: props.filters?.branch ?? 'all',
  classroom_id: props.filters?.classroom_id ?? null,
  teacher_id: props.filters?.teacher_id ?? null,
  date_from: props.filters?.date_from ? new Date(props.filters.date_from) : null,
  date_to: props.filters?.date_to ? new Date(props.filters.date_to) : null,
  q: props.filters?.q ?? '',
  perPage: props.filters?.perPage ?? (props.substitutions?.per_page ?? 20),
})

function toYmdLocal(d){
  if (!d) return null
  const dt = new Date(d)
  const y = dt.getFullYear(), m = String(dt.getMonth()+1).padStart(2,'0'), day = String(dt.getDate()).padStart(2,'0')
  return `${y}-${m}-${day}`
}

/* -------- actions -------- */
function buildQuery(extra = {}){
  const q = {}
  if (state.branch && state.branch !== 'all') q.branch = state.branch
  if (state.class_id) q.class_id = state.class_id
  if (state.teacher_id) q.teacher_id = state.teacher_id
  if (state.date_from) q.date_from = toYmdLocal(state.date_from)
  if (state.date_to) q.date_to = toYmdLocal(state.date_to)
  if (state.q && state.q.trim()!=='') q.q = state.q.trim()
  if (state.perPage && state.perPage !== props.substitutions?.per_page) q.per_page = state.perPage
  Object.assign(q, extra)
  return q
}
function applyFilters(){
  router.visit(route('manager.substitutions.index', buildQuery()), { preserveScroll: true, preserveState: true })
}
function onClear(){
  state.q = ''
  applyFilters()
}
function onPage(e){
  const page = Math.floor(e.first / e.rows) + 1
  router.visit(route('manager.substitutions.index', buildQuery({
    per_page: e.rows,
    page: page > 1 ? page : undefined
  })), { preserveScroll: true, preserveState: true })
}

/* -------- table computed -------- */
const value = computed(()=> props.substitutions?.data ?? [])
const totalRecords = computed(()=> props.substitutions?.total ?? value.value.length)
const rows = computed(()=> props.substitutions?.per_page ?? 20)
const first = computed(()=> Math.max(0, (props.substitutions?.from ?? 1) - 1))

/* display helpers */
function timeShort(t){ return t ? String(t).slice(0,5) : '—' }
</script>

<template>
  <Head title="Dạy thay" />

  <div class="mb-3 flex flex-col md:flex-row md:items-center md:justify-between gap-2">
    <h1 class="text-xl md:text-2xl font-heading font-semibold">Dạy thay</h1>

    <div class="flex flex-wrap items-center gap-2">
      <Select
        v-model="state.branch"
        :options="[{label:'Tất cả chi nhánh', value:'all'}, ...(branches||[]).map(b=>({label:b.name, value:String(b.id)}))]"
        optionLabel="label" optionValue="value" class="min-w-[220px]" @change="applyFilters" />

      <Select
        v-model="state.class_id"
        :options="(classrooms||[]).map(c=>({label:c.label, value:String(c.id)}))"
        optionLabel="label" optionValue="value" class="min-w-[260px]" showClear placeholder="Lọc theo lớp"
        @change="applyFilters" />

      <Select
        v-model="state.teacher_id"
        :options="(teachers||[]).map(t=>({label:t.label, value:String(t.id)}))"
        optionLabel="label" optionValue="value" class="min-w-[220px]" showClear placeholder="Lọc theo GV"
        @change="applyFilters" />

      <DatePicker v-model="state.date_from" dateFormat="yy-mm-dd" showIcon iconDisplay="input" placeholder="Từ ngày" @date-select="applyFilters" />
      <DatePicker v-model="state.date_to"   dateFormat="yy-mm-dd" showIcon iconDisplay="input" placeholder="Đến ngày" @date-select="applyFilters" />

      <span class="inline-flex items-center gap-1">
        <InputText v-model="state.q" placeholder="Tìm mã/tên lớp..." class="w-56" @keydown.enter="applyFilters" />
        <Button icon="pi pi-search" text @click="applyFilters" />
        <Button icon="pi pi-times" text :disabled="!state.q" @click="onClear" />
      </span>

      <Select v-model="state.perPage"
              :options="[{label:'20 / trang',value:20},{label:'50 / trang',value:50},{label:'100 / trang',value:100}]"
              optionLabel="label" optionValue="value" class="w-40" @change="applyFilters" />
    </div>
  </div>

  <div class="rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 p-2">
    <DataTable
      :value="value" :paginator="true" :rows="rows" :totalRecords="totalRecords" :first="first"
      dataKey="id" size="small" responsiveLayout="scroll"
      @page="onPage"
    >
      <Column field="date" header="Ngày" style="width: 140px" />
      <Column header="Giờ" style="width: 140px">
        <template #body="{ data }">{{ timeShort(data.start_time) }}–{{ timeShort(data.end_time) }}</template>
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
      <Column header="Giáo viên" style="width: 220px">
        <template #body="{ data }">
          <div class="font-medium">{{ data.teacher_name }}</div>
          <Tag value="Dạy thay" severity="warn" class="mt-1" />
        </template>
      </Column>
      <Column header="Lý do" style="width: 200px">
        <template #body="{ data }">
          <span v-if="data.reason">{{ data.reason }}</span>
          <span v-else class="text-slate-400">—</span>
        </template>
      </Column>
      <Column header="Chi nhánh" style="width: 180px">
        <template #body="{ data }">{{ data.branch_name ?? '—' }}</template>
      </Column>
      <Column header="Phê duyệt" style="width: 200px">
        <template #body="{ data }">
          <span v-if="data.approved_at">Đã duyệt</span>
          <span v-else class="text-slate-400">Chưa duyệt</span>
        </template>
      </Column>
      <template #empty>
        <div class="p-6 text-center text-slate-500 dark:text-slate-400">Chưa có bản ghi dạy thay.</div>
      </template>
    </DataTable>
  </div>
</template>
