<script setup>
import { reactive, computed, ref } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import { createInvoiceService } from '@/service/InvoiceService'

// PrimeVue
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import InputText from 'primevue/inputtext'
import Select from 'primevue/select'
import Button from 'primevue/button'
import Tag from 'primevue/tag'
import Tooltip from 'primevue/tooltip'

defineOptions({ layout: AppLayout })

const props = defineProps({
  invoices: Object,  // LengthAwarePaginator
  branches: Array,   // [{id,name}]
  filters: Object,   // { q:'', status:'', branch:'all'|id, perPage:number, sort:'', order:'' }
})

/* -------- Helpers -------- */
function toVnDate(ymd) {
  if (!ymd) return '—'
  const [y,m,d] = String(ymd).split('-')
  if (!y || !m || !d) return ymd
  return `${d}/${m}/${y}`
}

// Check if input is a date in format dd/mm/yyyy or dd/mm
function isDateString(str) {
  return /^\d{1,2}\/\d{1,2}(?:\/\d{4})?$/.test(str)
}

// Convert date string to YYYY-MM-DD format for API
function toYmd(dmy) {
  const [d, m, y = new Date().getFullYear()] = dmy.split('/')
  return `${y}-${m.padStart(2, '0')}-${d.padStart(2, '0')}`
}

// Check if input is a date range (e.g., 01/08 - 15/08)
function isDateRangeString(str) {
  return /^\d{1,2}\/\d{1,2}\s*-\s*\d{1,2}\/\d{1,2}$/.test(str)
}

// Convert date range string to start and end dates
function parseDateRange(rangeStr) {
  const [start, end] = rangeStr.split('-').map(s => s.trim())
  return {
    start: toYmd(start),
    end: toYmd(end)
  }
}

// Check if input is a number (for amount)
function isNumericString(str) {
  return /^\d+$/.test(str)
}

const invoiceService = createInvoiceService()

/* -------- Local UI state -------- */
const state = reactive({
  q: props.filters?.q ?? '',
  status: props.filters?.status ?? 'all',
  branch: props.filters?.branch ?? 'all',
  perPage: props.filters?.perPage ?? (props.invoices?.per_page ?? 20),
})

/* -------- Sorting -------- */
const sortField = ref(props.filters?.sort || null)
const sortOrder = ref(
  props.filters?.order === 'asc' ? 1 :
  props.filters?.order === 'desc' ? -1 : null
)

/* -------- Navigate with filters -------- */
function buildQuery(extra = {}) {
  const query = {}
  const searchTerm = state.q.trim()

  if (searchTerm !== '') {
    if (isDateRangeString(searchTerm)) {
      // Handle date range search (e.g., 01/08 - 15/08)
      const { start, end } = parseDateRange(searchTerm)
      query.start_date = start
      query.end_date = end
    } else if (isDateString(searchTerm)) {
      // Handle single date search (dd/mm/yyyy or dd/mm)
      query.due_date = toYmd(searchTerm)
    } else if (isNumericString(searchTerm)) {
      // Handle amount search
      query.total = searchTerm
    } else {
      // Handle text search
      query.q = searchTerm
    }
  }

  if (state.status && state.status !== 'all') query.status = state.status
  if (state.branch && state.branch !== 'all') query.branch = state.branch
  if (state.perPage && state.perPage !== props.invoices?.per_page) query.per_page = state.perPage
  if (sortField.value) query.sort = sortField.value
  if (sortOrder.value !== null) query.order = sortOrder.value === 1 ? 'asc' : 'desc'
  Object.assign(query, extra)
  return query
}

function applyFilters() {
  router.visit(route('admin.invoices.index', buildQuery()), {
    preserveScroll: true,
    preserveState: true,
  })
}
function onClearSearch() {
  state.q = ''
  applyFilters()
}
function onPage(e) {
  const page = Math.floor(e.first / e.rows) + 1
  router.visit(route('admin.invoices.index', buildQuery({
    per_page: e.rows,
    page: page > 1 ? page : undefined,
  })), {
    preserveScroll: true,
    preserveState: true,
  })
}
function onSort(e) {
  sortField.value = e.sortField
  sortOrder.value = e.sortOrder
  applyFilters()
}

/* -------- Actions -------- */
function destroy(id) {
  if (!confirm('Xác nhận xoá hoá đơn này?')) return
  invoiceService.delete(id) // BE sẽ flash toast
}

/* -------- DataTable computed -------- */
const value = computed(() => props.invoices?.data ?? [])
const totalRecords = computed(() => props.invoices?.total ?? value.value.length)
const rows = computed(() => props.invoices?.per_page ?? 20)
const first = computed(() => Math.max(0, (props.invoices?.from ?? 1) - 1))

/* -------- Status UI -------- */
const statusOptions = [
  { label: 'Tất cả trạng thái', value: 'all' },
  { label: 'Chưa thu', value: 'unpaid' },
  { label: 'Thu một phần', value: 'partial' },
  { label: 'Đã thu', value: 'paid' },
  { label: 'Hoàn tiền', value: 'refunded' },
]
function statusSeverity(s) {
  switch (s) {
    case 'unpaid': return 'danger'
    case 'partial': return 'warning'
    case 'paid': return 'success'
    case 'refunded': return 'info'
    default: return 'info'
  }
}
</script>

<template>
  <Head title="Hoá đơn học phí" />

  <!-- Header -->
  <div class="mb-3 flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
    <h1 class="text-xl md:text-2xl font-heading font-semibold">Hoá đơn học phí</h1>

    <div class="flex flex-wrap items-center gap-2">
      <!-- Branch -->
      <Select
        v-model="state.branch"
        :options="[{label:'Tất cả chi nhánh', value:'all'}, ...(branches||[]).map(b=>({label:b.name, value:String(b.id)}))]"
        optionLabel="label"
        optionValue="value"
        :pt="{ root: { class: 'min-w-[220px]' } }"
        @change="applyFilters"
      />

      <!-- Status -->
      <Select
        v-model="state.status"
        :options="statusOptions"
        optionLabel="label"
        optionValue="value"
        class="w-52"
        @change="applyFilters"
      />

      <!-- Search -->
      <div class="flex flex-col sm:flex-row gap-2 w-full sm:w-auto">
        <span class="relative flex-grow">
          <InputText
            v-model="state.q"
            placeholder="Tìm mã/tên HV/lớp/tổng tiền ..."
            class="w-full sm:w-96"
            @keydown.enter="applyFilters"
          />
        </span>
        <div class="flex gap-1">
          <Button
            icon="pi pi-search"
            class="p-button-text"
            @click="applyFilters"
            :title="'Tìm kiếm'"
          />
          <Button
            icon="pi pi-times"
            class="p-button-text"
            @click="onClearSearch"
            :disabled="!state.q"
            :title="'Xoá tìm kiếm'"
          />
        </div>
      </div>

      <!-- PerPage -->
      <Select
        v-model="state.perPage"
        :options="[{label:'12 / trang',value:12},{label:'24 / trang',value:24},{label:'48 / trang',value:48}]"
        optionLabel="label" optionValue="value"
        class="w-40"
        @change="applyFilters"
      />

      <Link
        :href="route('admin.invoices.create')"
        class="px-3 py-2 rounded-lg bg-emerald-600 text-white hover:bg-emerald-700"
      >
        <i class="pi pi-plus mr-1" /> Tạo hoá đơn
      </Link>
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
      lazy
      @page="onPage"
      @sort="onSort"
      dataKey="id"
      responsiveLayout="scroll"
      size="small"
    >
      <Column field="id" header="#" style="width: 80px" :sortable="true" />

      <Column field="code" header="Mã" style="width: 140px" :sortable="true" />

      <Column header="Học viên" :sortable="true" style="min-width: 220px">
        <template #body="{ data }">
          <div class="flex flex-col">
            <span class="font-medium">{{ data.student?.name ?? '—' }}</span>
            <span class="text-xs text-slate-500">{{ data.student?.code ?? '' }}</span>
          </div>
        </template>
      </Column>

      <Column header="Lớp" :sortable="true" style="min-width: 180px">
        <template #body="{ data }">
          <span>{{ data.classroom?.name ?? data.classroom?.code ?? '—' }}</span>
        </template>
      </Column>

      <Column header="Chi nhánh" field="branch.name" :sortable="true" style="width: 180px">
        <template #body="{ data }">
          <span>{{ data.branch?.name ?? '—' }}</span>
        </template>
      </Column>

      <Column field="total" header="Tổng tiền" :sortable="true" style="width: 140px">
        <template #body="{ data }">
          {{ new Intl.NumberFormat('vi-VN').format(data.total || 0) }}
        </template>
      </Column>

      <Column field="status" header="Trạng thái" :sortable="true" style="width: 140px">
        <template #body="{ data }">
          <Tag :value="data.status" :severity="statusSeverity(data.status)" />
        </template>
      </Column>

      <Column field="due_date" header="Hạn TT" :sortable="true" style="width: 140px">
        <template #body="{ data }">
          {{ toVnDate(data.due_date) }}
        </template>
      </Column>

      <Column header="" style="width: 240px">
        <template #body="{ data }">
          <div class="flex gap-2 justify-end">
            <Link :href="route('admin.invoices.show', data.id)"
                  class="px-3 py-1.5 rounded border border-slate-300 hover:bg-slate-50 dark:border-slate-700 dark:hover:bg-slate-900/20">
              Chi tiết
            </Link>
            <Link :href="route('admin.invoices.edit', data.id)"
                  class="px-3 py-1.5 rounded border border-emerald-300 text-emerald-700 hover:bg-emerald-50 dark:border-emerald-700 dark:text-emerald-300 dark:hover:bg-emerald-900/20">
              Sửa
            </Link>
            <button @click="destroy(data.id)"
                    class="px-3 py-1.5 rounded border border-red-300 text-red-600 hover:bg-red-50 dark:border-red-700 dark:text-red-300 dark:hover:bg-red-900/20">
              Xoá
            </button>
          </div>
        </template>
      </Column>

      <template #empty>
        <div class="p-6 text-center text-slate-500 dark:text-slate-400">Chưa có hoá đơn nào.</div>
      </template>
    </DataTable>
  </div>
</template>
