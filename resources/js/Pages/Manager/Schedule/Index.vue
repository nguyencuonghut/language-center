<script setup>
import { reactive, ref, computed } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'

// PrimeVue
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import Select from 'primevue/select'
import DatePicker from 'primevue/datepicker'
import Button from 'primevue/button'
import Tag from 'primevue/tag'

defineOptions({ layout: AppLayout })

const props = defineProps({
  filters: Object,   // {branch_id,class_id,teacher_id,from,to,order,perPage}
  branches: Array,   // [{id,name}]
  classes: Array,    // [{id,code,name}]
  teachers: Array,   // [{id,name}]
  sessions: Object,  // paginator
})

/* ------ Helpers ------ */
function toDdMm(dateStr) {
  if (!dateStr) return ''
  const d = new Date(dateStr)
  if (isNaN(d.getTime())) return ''
  return `${String(d.getFullYear()).padStart(4, '0')}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`
}

function statusSeverity(s){
  switch (s) {
    case 'planned': return 'info'
    case 'moved':   return 'warn'
    case 'canceled':return 'danger'
    default:        return 'info'
  }
}

/* ------ Local state ------ */
const state = reactive({
  branch_id: props.filters?.branch_id ? String(props.filters.branch_id) : null,
  class_id:  props.filters?.class_id ? String(props.filters.class_id)   : null,
  teacher_id:props.filters?.teacher_id ? String(props.filters.teacher_id) : null,
  from: props.filters?.from ? new Date(props.filters.from + 'T00:00:00') : new Date(),
  to:   props.filters?.to   ? new Date(props.filters.to   + 'T00:00:00') : new Date(),
  perPage: props.filters?.perPage ?? (props.sessions?.per_page ?? 20),
  order: props.filters?.order ?? 'asc',
})

function toYmdLocal(d){
  if (!d) return null
  const dt = new Date(d)
  const y = dt.getFullYear()
  const m = String(dt.getMonth()+1).padStart(2,'0')
  const day = String(dt.getDate()).padStart(2,'0')
  return `${y}-${m}-${day}`
}

function apply(page){
  const q = {}
  if (state.branch_id) q.branch_id = state.branch_id
  if (state.class_id)  q.class_id  = state.class_id
  if (state.teacher_id)q.teacher_id= state.teacher_id
  if (state.from) q.from = toYmdLocal(state.from)
  if (state.to)   q.to   = toYmdLocal(state.to)
  if (state.order) q.order = state.order
  if (state.perPage) q.per_page = state.perPage
  if (page && page > 1) q.page = page

  router.visit(route('manager.schedule.index', q), {
    preserveScroll: true,
    preserveState: true,
  })
}

function onPage(e){
  const page = Math.floor(e.first / e.rows) + 1
  state.perPage = e.rows
  apply(page)
}

/* ------ Table bindings ------ */
const value = computed(() => props.sessions?.data ?? [])
const totalRecords = computed(() => props.sessions?.total ?? value.value.length)
const rows = computed(() => props.sessions?.per_page ?? 20)
const first = computed(() => Math.max(0, (props.sessions?.from ?? 1) - 1))
</script>

<template>
  <Head title="Lịch lớp (Manager)" />

   <!-- Header -->
  <div class="mb-2">
    <h1 class="text-xl md:text-2xl font-heading font-semibold">Lịch lớp</h1>
    <p class="text-slate-500 dark:text-slate-400 text-sm">Dạng danh sách theo khoảng thời gian</p>
  </div>

  <!-- Filters -->
  <div class="mb-3 flex flex-wrap items-end gap-2">
    <!-- Branch -->
    <div class="min-w-[220px]">
      <label class="block text-xs text-slate-500 mb-1">Chi nhánh</label>
      <Select
        v-model="state.branch_id"
        :options="[{label:'Tất cả', value:null}, ...(props.branches||[]).map(b=>({label:b.name, value:String(b.id)}))]"
        optionLabel="label" optionValue="value" class="w-full" showClear
      />
    </div>

    <!-- Class -->
    <div class="min-w-[260px]">
      <label class="block text-xs text-slate-500 mb-1">Lớp</label>
      <Select
        v-model="state.class_id"
        :options="[{label:'Tất cả', value:null}, ...(props.classes||[]).map(c=>({label:`${c.code} · ${c.name}`, value:String(c.id)}))]"
        optionLabel="label" optionValue="value" class="w-full" showClear
      />
    </div>

    <!-- Teacher -->
    <div class="min-w-[220px]">
      <label class="block text-xs text-slate-500 mb-1">Giáo viên</label>
      <Select
        v-model="state.teacher_id"
        :options="[{label:'Tất cả', value:null}, ...(props.teachers||[]).map(t=>({label:t.name, value:String(t.id)}))]"
        optionLabel="label" optionValue="value" class="w-full" showClear
      />
    </div>

    <!-- From -->
    <div>
      <label class="block text-xs text-slate-500 mb-1">Từ ngày</label>
      <DatePicker v-model="state.from" dateFormat="yy-mm-dd" showIcon iconDisplay="input" />
    </div>
    <!-- To -->
    <div>
      <label class="block text-xs text-slate-500 mb-1">Đến ngày</label>
      <DatePicker v-model="state.to" dateFormat="yy-mm-dd" showIcon iconDisplay="input" />
    </div>

    <!-- Order -->
    <div>
      <label class="block text-xs text-slate-500 mb-1">Sắp xếp</label>
      <Select
        v-model="state.order"
        :options="[{label:'Tăng dần',value:'asc'},{label:'Giảm dần',value:'desc'}]"
        optionLabel="label" optionValue="value"
      />
    </div>

    <div>
      <Button label="Lọc" icon="pi pi-filter" @click="apply()" />
    </div>
  </div>

  <!-- Table -->
  <div class="rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 p-2">
    <DataTable
      :value="value"
      :paginator="true"
      :rows="rows"
      :totalRecords="totalRecords"
      :first="first"
      dataKey="id"
      responsiveLayout="scroll"
      size="small"
      @page="onPage"
    >
      <Column header="Ngày" style="width: 140px">
        <template #body="{ data }">{{ toDdMm(data.date) }}</template>
      </Column>
      <Column header="Giờ" style="width: 140px">
        <template #body="{ data }">{{ data.start_time }}–{{ data.end_time }}</template>
      </Column>
      <Column header="Lớp">
        <template #body="{ data }">
          <span>{{ data.classroom?.code }} · {{ data.classroom?.name }}</span>
        </template>
      </Column>
      <Column header="Phòng" style="width: 180px">
        <template #body="{ data }">
          <span v-if="data.room">{{ data.room.code ? (data.room.code + ' · ') : '' }}{{ data.room.name }}</span>
          <span v-else class="text-slate-400">—</span>
        </template>
      </Column>
      <Column header="Giáo viên" style="width: 260px">
        <template #body="{ data }">
          <span v-if="data.substitute">
            <i class="pi pi-refresh mr-1"></i> Dạy thay: {{ data.substitute.name }}
          </span>
          <span v-else class="text-slate-500">Theo phân công</span>
        </template>
      </Column>
      <Column header="Trạng thái" style="width: 140px">
        <template #body="{ data }">
          <Tag :value="data.status" :severity="statusSeverity(data.status)" />
        </template>
      </Column>

      <template #empty>
        <div class="p-6 text-center text-slate-500 dark:text-slate-400">
          Không có buổi học trong khoảng đã chọn.
        </div>
      </template>
    </DataTable>
  </div>
</template>
