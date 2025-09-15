<script setup>
import { reactive, computed, ref } from 'vue'
import { Head, Link } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import { createPayrollService } from '@/service/PayrollService'
import { usePageToast } from '@/composables/usePageToast'

// PrimeVue
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import Select from 'primevue/select'
import Button from 'primevue/button'
import Tag from 'primevue/tag'
import InputText from 'primevue/inputtext'

defineOptions({ layout: AppLayout })

const props = defineProps({
  payrolls: Object,   // paginator
  branches: Array,    // [{id,name}]
  filters: Object     // {branch:'all'|id, status:'all'|draft|approved|locked, q:'', perPage, sort, order}
})

const { showError } = usePageToast()
const payrollService = createPayrollService({ showError })

/* ------- Local UI state (đọc từ filters server) ------- */
const state = reactive({
  branch: props.filters?.branch ?? 'all',
  status: props.filters?.status ?? 'all',
  q: props.filters?.q ?? '',
  perPage: props.filters?.perPage ?? (props.payrolls?.per_page ?? 12)
})

/* ------- Sort/Paging ------- */
const sortField = ref(props.filters?.sort || null)
const sortOrder = ref(
  props.filters?.order === 'asc' ? 1 :
  props.filters?.order === 'desc' ? -1 : null
)

function buildQuery(extra = {}) {
  const query = {}
  if (state.branch && state.branch !== 'all') query.branch = state.branch
  if (state.status && state.status !== 'all') query.status = state.status
  if (state.q && state.q.trim() !== '') query.q = state.q.trim()
  if (state.perPage && state.perPage !== props.payrolls?.per_page) query.per_page = state.perPage
  if (sortField.value) query.sort = sortField.value
  if (sortOrder.value !== null) query.order = sortOrder.value === 1 ? 'asc' : 'desc'
  Object.assign(query, extra)
  return query
}

function applyFilters() {
  payrollService.getList(buildQuery())
}
function clearSearch(){
  state.q = ''
  applyFilters()
}

function onPage(e) {
  const page = Math.floor(e.first / e.rows) + 1
  payrollService.getList(buildQuery({
    per_page: e.rows,
    page: page > 1 ? page : undefined
  }))
}
function onSort(e) {
  sortField.value = e.sortField
  sortOrder.value = e.sortOrder
  applyFilters()
}

/* ------- Actions ------- */
function approve(row) { payrollService.approve(row.id) }
function lock(row)    { payrollService.lock(row.id) }
function remove(row)  { payrollService.destroy(row.id) }

function canApprove(row){ return row.status === 'draft' }
function canLock(row){ return row.status === 'approved' }
function canDelete(row){ return row.status === 'draft' }

/* ------- DT computed ------- */
const value = computed(() => props.payrolls?.data ?? [])
const totalRecords = computed(() => props.payrolls?.total ?? value.value.length)
const rows = computed(() => props.payrolls?.per_page ?? 12)
const first = computed(() => Math.max(0, (props.payrolls?.from ?? 1) - 1))

/* ------- Helpers ------- */
function statusSeverity(s) {
  switch (s) {
    case 'draft':    return 'info'
    case 'approved': return 'success'
    case 'locked':   return 'warning'
    default:         return 'info'
  }
}

function formatDate(v) {
  if (!v) return '—'
  const d = new Date(v)
  if (isNaN(d)) return String(v)
  const dd = String(d.getDate()).padStart(2, '0')
  const mm = String(d.getMonth() + 1).padStart(2, '0')
  const yyyy = d.getFullYear()
  return `${dd}/${mm}/${yyyy}`
}
</script>

<template>
  <Head title="Bảng lương (Payroll)" />

  <div class="mb-3 flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
    <h1 class="text-xl md:text-2xl font-heading font-semibold">Bảng lương</h1>

    <div class="flex flex-wrap items-center gap-2">
      <!-- Branch -->
      <Select
        v-model="state.branch"
        :options="[{label:'Tất cả chi nhánh', value:'all'}, ...(props.branches||[]).map(b=>({label:b.name, value:String(b.id)}))]"
        optionLabel="label" optionValue="value"
        :pt="{ root: { class: 'min-w-[220px]' } }"
        @change="applyFilters"
      />

      <!-- Status -->
      <Select
        v-model="state.status"
        :options="[
          {label:'Tất cả trạng thái', value:'all'},
          {label:'Nháp',   value:'draft'},
          {label:'Đã duyệt', value:'approved'},
          {label:'Đã khoá', value:'locked'},
        ]"
        optionLabel="label" optionValue="value"
        class="w-44"
        @change="applyFilters"
      />

      <!-- Search -->
      <span class="inline-flex items-center gap-1">
        <InputText v-model="state.q" placeholder="Tìm mã / ghi chú..." class="w-60" @keydown.enter="applyFilters" />
        <Button icon="pi pi-search" text @click="applyFilters" :aria-label="'Tìm kiếm'" />
        <Button icon="pi pi-times" text @click="clearSearch" :disabled="!state.q" :aria-label="'Xoá tìm kiếm'" />
      </span>

      <!-- PerPage -->
      <Select
        v-model="state.perPage"
        :options="[{label:'12 / trang',value:12},{label:'24 / trang',value:24},{label:'50 / trang',value:50}]"
        optionLabel="label" optionValue="value"
        class="w-36"
        @change="applyFilters"
      />

      <!-- Create -->
      <Link :href="route('manager.payrolls.create')" class="px-3 py-2 rounded-lg bg-emerald-600 text-white hover:bg-emerald-700">
        <i class="pi pi-plus mr-1"></i> Tạo bảng lương
      </Link>
    </div>
  </div>

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
      <Column field="code" header="Mã" style="width: 160px" :sortable="true" />
      <Column field="branch_name" header="Chi nhánh" style="width: 200px" :sortable="true">
        <template #body="{ data }">
          <span>{{ data.branch?.name ?? '— (Toàn hệ thống)' }}</span>
        </template>
      </Column>
      <Column field="period_from" header="Từ ngày" style="width: 140px" :sortable="true">
        <template #body="{ data }">
          {{ formatDate(data.period_from) }}
        </template>
      </Column>
      <Column field="period_to" header="Đến ngày" style="width: 140px" :sortable="true">
        <template #body="{ data }">
          {{ formatDate(data.period_to) }}
        </template>
      </Column>
      <Column field="total_amount" header="Tổng (VND)" style="width: 160px" :sortable="true">
        <template #body="{ data }">
          {{ new Intl.NumberFormat('vi-VN').format(data.total_amount ?? 0) }}
        </template>
      </Column>
      <Column field="status" header="Trạng thái" style="width: 140px" :sortable="true">
        <template #body="{ data }">
          <Tag :value="data.status"
               :severity="statusSeverity(data.status)" />
        </template>
      </Column>

      <Column header="" style="width: 280px">
        <template #body="{ data }">
          <div class="flex gap-2 justify-end">
            <Link
              :href="route('manager.payrolls.show', data.id)"
              class="px-3 py-1.5 rounded border border-indigo-300 text-indigo-700 hover:bg-indigo-50
                     dark:border-indigo-700 dark:text-indigo-300 dark:hover:bg-indigo-900/20">
              <i class="pi pi-eye mr-1"></i> Xem
            </Link>

            <Button v-if="canApprove(data)" label="Duyệt" icon="pi pi-check"
                    @click="approve(data)" outlined
                    class="!border-emerald-300 !text-emerald-700 hover:!bg-emerald-50
                           dark:!border-emerald-700 dark:!text-emerald-300 dark:hover:!bg-emerald-900/20" />

            <Button v-if="canLock(data)" label="Khoá" icon="pi pi-lock"
                    @click="lock(data)" outlined
                    class="!border-amber-300 !text-amber-700 hover:!bg-amber-50
                           dark:!border-amber-700 dark:!text-amber-300 dark:hover:!bg-amber-900/20" />

            <Button v-if="canDelete(data)" label="Xoá" icon="pi pi-trash"
                    @click="remove(data)" outlined
                    class="!border-red-300 !text-red-600 hover:!bg-red-50
                           dark:!border-red-700 dark:!text-red-300 dark:hover:!bg-red-900/20" />
          </div>
        </template>
      </Column>

      <template #empty>
        <div class="p-6 text-center text-slate-500 dark:text-slate-400">Chưa có bảng lương nào.</div>
      </template>
    </DataTable>
  </div>
</template>
