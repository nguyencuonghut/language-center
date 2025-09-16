<script setup>
import { reactive, ref, computed, watch } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'

// PrimeVue
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import InputText from 'primevue/inputtext'
import Select from 'primevue/select'
import DatePicker from 'primevue/datepicker'
import Button from 'primevue/button'
import Tag from 'primevue/tag'
import Drawer from 'primevue/drawer'

defineOptions({ layout: AppLayout })

/**
 * Props từ controller:
 * - logs: LengthAwarePaginator (data, total, per_page, from, ...)
 * - filters: { q, actor_id, action, target_type, date_from, date_to, perPage, sort, order }
 * - actors: [{id,name}]
 * - actions: [ 'invoice.paid', 'transfer.created', ... ]
 * - target_types: [ 'App\\Models\\Invoice', 'App\\Models\\Transfer', ... ]
 */
const props = defineProps({
  logs: Object,
  filters: Object,
  actors: Array,
  actions: Array,
  target_types: Array,
})

/* ---------- UI state ---------- */
const state = reactive({
  q: props.filters?.q ?? '',
  actor_id: props.filters?.actor_id ? String(props.filters.actor_id) : null,
  action: props.filters?.action ?? null,
  target_type: props.filters?.target_type ?? null,
  date_from: props.filters?.date_from ? new Date(props.filters.date_from+'T00:00:00') : null,
  date_to: props.filters?.date_to ? new Date(props.filters.date_to+'T00:00:00') : null,
  perPage: props.filters?.perPage ?? (props.logs?.per_page ?? 30),
})
const sortField = ref(props.filters?.sort || 'created_at')
const sortOrder = ref(props.filters?.order === 'asc' ? 1 : -1)

/* ---------- Helpers ---------- */
function toYmdLocal(d) {
  if (!d) return null
  const x = new Date(d)
  const y = x.getFullYear()
  const m = String(x.getMonth()+1).padStart(2,'0')
  const day = String(x.getDate()).padStart(2,'0')
  return `${y}-${m}-${day}`
}
function formatDateTime(dt) {
  if (!dt) return '—'
  const d = typeof dt === 'string' ? new Date(dt) : dt
  if (isNaN(d.getTime())) return dt
  const y = d.getFullYear()
  const m = String(d.getMonth() + 1).padStart(2, '0')
  const day = String(d.getDate()).padStart(2, '0')
  const h = String(d.getHours()).padStart(2, '0')
  const i = String(d.getMinutes()).padStart(2, '0')
  return `${y}-${m}-${day} ${h}:${i}`
}
function buildQuery(extra = {}) {
  const q = {}
  if (state.q && state.q.trim() !== '') q.q = state.q.trim()
  if (state.actor_id) q.actor_id = state.actor_id
  if (state.action) q.action = state.action
  if (state.target_type) q.target_type = state.target_type
  if (state.date_from) q.date_from = toYmdLocal(state.date_from)
  if (state.date_to) q.date_to = toYmdLocal(state.date_to)
  if (state.perPage && state.perPage !== props.logs?.per_page) q.per_page = state.perPage
  if (sortField.value) q.sort = sortField.value
  if (sortOrder.value !== null) q.order = sortOrder.value === 1 ? 'asc' : 'desc'
  Object.assign(q, extra)
  return q
}
let debounceTimer = null
function debouncedSearch() {
  clearTimeout(debounceTimer)
  debounceTimer = setTimeout(() => applyFilters(), 350)
}
function applyFilters() {
  router.visit(route('admin.activity-logs.index', buildQuery()), {
    preserveScroll: true,
    preserveState: true,
  })
}
function clearFilters() {
  state.q = ''
  state.actor_id = null
  state.action = null
  state.target_type = null
  state.date_from = null
  state.date_to = null
  state.perPage = 30
  sortField.value = 'created_at'
  sortOrder.value = -1
  applyFilters()
}

/* ---------- DataTable events ---------- */
function onPage(e) {
  const page = Math.floor(e.first / e.rows) + 1
  router.visit(route('admin.activity-logs.index', buildQuery({
    per_page: e.rows,
    page: page > 1 ? page : undefined
  })), { preserveScroll: true, preserveState: true })
}
function onSort(e) {
  sortField.value = e.sortField
  sortOrder.value = e.sortOrder
  applyFilters()
}

/* ---------- Export CSV ---------- */
function exportCsv() {
  // Build query string từ state filter
  const params = new URLSearchParams({
    q: state.q || '',
    actor_id: state.actor_id || '',
    action: state.action || '',
    target_type: state.target_type || '',
    date_from: state.date_from ? toYmdLocal(state.date_from) : '',
    date_to: state.date_to ? toYmdLocal(state.date_to) : '',
  }).toString();
  window.open(`/admin/activity-logs/export?${params}`, '_blank');
}

/* ---------- Table computed ---------- */
const value = computed(() => props.logs?.data ?? [])
const totalRecords = computed(() => props.logs?.total ?? value.value.length)
const rows = computed(() => props.logs?.per_page ?? 30)
const first = computed(() => Math.max(0, (props.logs?.from ?? 1) - 1))

/* ---------- Drawer (detail) ---------- */
const showDrawer = ref(false)
const currentRow = ref(null)
function openDetail(row) {
  currentRow.value = row
  showDrawer.value = true
}

/* ---------- Badges ---------- */
function actionSeverity(a) {
  if (!a) return 'info'
  if (a.startsWith('invoice')) return 'success'
  if (a.startsWith('timesheet')) return 'warning'
  if (a.startsWith('transfer')) return 'info'
  if (a.startsWith('class.session')) return 'secondary'
  return 'info'
}
function shortType(t) {
  if (!t) return '—'
  const parts = String(t).split('\\')
  return parts[parts.length - 1] || t
}
</script>

<template>
  <Head title="Nhật ký hoạt động" />

  <!-- Header -->
  <div class="mb-3 flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
    <div>
      <h1 class="text-xl md:text-2xl font-heading font-semibold">Nhật ký hoạt động</h1>
      <p class="text-slate-500 dark:text-slate-400 text-sm">Theo dõi sự kiện nghiệp vụ: thanh toán, chuyển lớp, duyệt timesheet…</p>
    </div>
    <div class="flex items-center gap-2">
      <Button icon="pi pi-refresh" text @click="applyFilters" :aria-label="'Làm mới'" />
      <Button icon="pi pi-download" label="Export CSV" @click="exportCsv" />
    </div>
  </div>

  <!-- Filters Bar -->
  <div class="rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 p-3 mb-3">
    <div class="grid grid-cols-1 md:grid-cols-5 gap-3">
      <div class="md:col-span-2">
        <label class="block text-xs text-slate-500 mb-1">Tìm kiếm</label>
        <div class="flex items-center gap-2">
          <InputText v-model="state.q" placeholder="action / target id / IP / mô tả meta..."
                     class="w-full" @input="debouncedSearch" />
          <Button icon="pi pi-times" text @click="clearFilters" :disabled="!state.q && !state.actor_id && !state.action && !state.target_type && !state.date_from && !state.date_to" />
        </div>
      </div>

      <div>
        <label class="block text-xs text-slate-500 mb-1">Người thực hiện</label>
        <Select v-model="state.actor_id"
                :options="[{label:'Tất cả', value:null}, ...(actors||[]).map(a=>({label:a.name, value:String(a.id)}))]"
                optionLabel="label" optionValue="value" class="w-full" showClear
                @change="applyFilters" />
      </div>

      <div>
        <label class="block text-xs text-slate-500 mb-1">Action</label>
        <Select v-model="state.action"
                :options="[{label:'Tất cả', value:null}, ...(actions||[]).map(a=>({label:a, value:a}))]"
                optionLabel="label" optionValue="value" class="w-full" showClear
                @change="applyFilters" />
      </div>

      <div>
        <label class="block text-xs text-slate-500 mb-1">Target type</label>
        <Select v-model="state.target_type"
                :options="[{label:'Tất cả', value:null}, ...(target_types||[]).map(t=>({label:shortType(t), value:t}))]"
                optionLabel="label" optionValue="value" class="w-full" showClear
                @change="applyFilters" />
      </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-5 gap-3 mt-3">
      <div>
        <label class="block text-xs text-slate-500 mb-1">Từ ngày</label>
        <DatePicker v-model="state.date_from" dateFormat="yy-mm-dd" showIcon iconDisplay="input" class="w-full" @date-select="applyFilters" />
      </div>
      <div>
        <label class="block text-xs text-slate-500 mb-1">Đến ngày</label>
        <DatePicker v-model="state.date_to" dateFormat="yy-mm-dd" showIcon iconDisplay="input" class="w-full" @date-select="applyFilters" />
      </div>
      <div>
        <label class="block text-xs text-slate-500 mb-1">Số dòng / trang</label>
        <Select v-model="state.perPage"
                :options="[{label:'30',value:30},{label:'50',value:50},{label:'100',value:100}]"
                optionLabel="label" optionValue="value" class="w-full"
                @change="applyFilters" />
      </div>
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
      :sortField="sortField"
      :sortOrder="sortOrder"
      dataKey="id"
      lazy
      size="small"
      responsiveLayout="scroll"
      @page="onPage"
      @sort="onSort"
    >
      <Column field="created_at" header="Thời gian" style="width: 200px" :sortable="true" >
        <template #body="{ data }">
          {{ formatDateTime(data.created_at) }}
        </template>
      </Column>
      <Column field="action" header="Action" style="width: 200px" :sortable="true">
        <template #body="{ data }">
          <Tag :value="data.action" :severity="actionSeverity(data.action)" />
        </template>
      </Column>
      <Column header="Đối tượng" style="width: 220px" :sortable="false">
        <template #body="{ data }">
          <div class="truncate" :title="data.target_type">
            {{ shortType(data.target_type) }} #{{ data.target_id }}
          </div>
        </template>
      </Column>
      <Column field="actor.name" header="Người thực hiện" style="width: 200px">
        <template #body="{ data }">
          {{ data.actor?.name ?? 'System' }}
        </template>
      </Column>
      <Column field="ip" header="IP" style="width: 140px" />
      <Column header="User Agent" :sortable="false">
        <template #body="{ data }">
            <div
            class="truncate max-w-[220px]"
            :title="data.user_agent"
            >
            {{ data.user_agent ?? '—' }}
            </div>
        </template>
      </Column>
      <Column header="" style="width: 120px">
        <template #body="{ data }">
          <div class="flex justify-end">
            <Button icon="pi pi-eye" label="Chi tiết" severity="info" variant="outlined" @click="openDetail(data)" />
          </div>
        </template>
      </Column>

      <template #empty>
        <div class="p-6 text-center text-slate-500 dark:text-slate-400">Không có bản ghi phù hợp.</div>
      </template>
    </DataTable>
  </div>

  <!-- Drawer: Detail -->
  <Drawer v-model:visible="showDrawer" position="right" :modal="true" :showCloseIcon="true" class="!w-[520px] md:!w-[620px]">
    <template #header>
      <div class="font-semibold">Chi tiết hoạt động</div>
    </template>

    <div v-if="currentRow" class="space-y-3">
      <div class="grid grid-cols-3 gap-2 text-sm">
        <div class="text-slate-500">Thời gian</div>
        <div class="col-span-2">{{ formatDateTime(currentRow.created_at) }}</div>

        <div class="text-slate-500">Action</div>
        <div class="col-span-2">
          <Tag :value="currentRow.action" :severity="actionSeverity(currentRow.action)" />
        </div>

        <div class="text-slate-500">Đối tượng</div>
        <div class="col-span-2">
          {{ shortType(currentRow.target_type) }} #{{ currentRow.target_id }}
        </div>

        <div class="text-slate-500">Người thực hiện</div>
        <div class="col-span-2">{{ currentRow.actor?.name ?? 'System' }}</div>

        <div class="text-slate-500">IP</div>
        <div class="col-span-2">{{ currentRow.ip ?? '—' }}</div>

        <div class="text-slate-500">User Agent</div>
        <div class="col-span-2 truncate" :title="currentRow.user_agent">{{ currentRow.user_agent ?? '—' }}</div>
      </div>

      <div>
        <div class="text-sm text-slate-500 mb-1">Meta</div>
        <pre class="text-xs bg-slate-50 dark:bg-slate-900/40 p-3 rounded overflow-auto max-h-[360px]">
          {{ JSON.stringify(currentRow.meta ?? {}, null, 2) }}
        </pre>
      </div>
    </div>
  </Drawer>
</template>
