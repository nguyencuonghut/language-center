<script setup>
import { reactive, ref, computed } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'

// PrimeVue
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import Button from 'primevue/button'
import Tag from 'primevue/tag'
import InputText from 'primevue/inputtext'
import Select from 'primevue/select'

defineOptions({ layout: AppLayout })

const props = defineProps({
  transfers: Object, // paginator: { data, total, per_page, current_page, ... }
  filters: Object    // { sort, order, perPage }
})

/* ---------- Format helpers ---------- */
function toDdMmYyyy(d) {
  if (!d) return '—'
  const dt = new Date(String(d).replace(' ', 'T'))
  if (isNaN(dt.getTime())) return d
  const dd = String(dt.getDate()).padStart(2, '0')
  const mm = String(dt.getMonth() + 1).padStart(2, '0')
  const yyyy = dt.getFullYear()
  return `${dd}/${mm}/${yyyy}`
}

/* ---------- State ---------- */
const state = reactive({
  perPage: props.filters?.perPage ?? (props.transfers?.per_page ?? 20)
})
const sortField = ref(props.filters?.sort || 'created_at')
const sortOrder = ref(props.filters?.order === 'asc' ? 1 : -1)

/* ---------- Pagination & Sorting ---------- */
function buildQuery(extra = {}) {
  const q = {}
  if (state.perPage && state.perPage !== props.transfers?.per_page) q.per_page = state.perPage
  if (sortField.value) q.sort = sortField.value
  if (sortOrder.value !== null) q.order = sortOrder.value === 1 ? 'asc' : 'desc'
  Object.assign(q, extra)
  return q
}

function applyFilters() {
  router.visit(route('admin.transfers.index', buildQuery()), {
    preserveScroll: true,
    preserveState: true
  })
}

function onPage(event) {
  const page = Math.floor(event.first / event.rows) + 1
  router.visit(route('admin.transfers.index', buildQuery({ page: page > 1 ? page : undefined })), {
    preserveScroll: true,
    preserveState: true
  })
}

function onSort(event) {
  sortField.value = event.sortField
  sortOrder.value = event.sortOrder
  applyFilters()
}

/* ---------- Computed ---------- */
const transfersData = computed(() => props.transfers?.data ?? [])
const totalRecords = computed(() => props.transfers?.total ?? 0)
const rows = computed(() => props.transfers?.per_page ?? 20)
const first = computed(() => Math.max(0, (props.transfers?.from ?? 1) - 1))

/* ---------- Delete ---------- */
function destroyTransfer(id) {
  if (!confirm('Xác nhận xoá bản ghi chuyển lớp này?')) return
  router.delete(route('admin.transfers.destroy', id), { preserveScroll: true })
}
</script>

<template>
  <Head title="Chuyển lớp" />

  <!-- Header -->
  <div class="mb-3 flex flex-col md:flex-row md:items-center md:justify-between gap-3">
    <h1 class="text-xl md:text-2xl font-heading font-semibold">Danh sách chuyển lớp</h1>
    <div class="flex items-center gap-2">
      <Link
        :href="route('admin.transfers.create')"
        class="px-3 py-2 rounded border border-emerald-300 text-emerald-700 hover:bg-emerald-50
               dark:border-emerald-700 dark:text-emerald-300 dark:hover:bg-emerald-900/20"
      >
        <i class="pi pi-plus mr-1"></i> Thêm chuyển lớp
      </Link>
      <Select
        v-model="state.perPage"
        :options="[{label:'20 / trang',value:20},{label:'50 / trang',value:50},{label:'100 / trang',value:100}]"
        optionLabel="label"
        optionValue="value"
        class="w-40"
        @change="applyFilters"
      />
    </div>
  </div>

  <!-- Table -->
  <div class="rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 p-2">
    <DataTable
      :value="transfersData"
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
      <!-- ID -->
      <Column field="id" header="#" style="width: 80px" :sortable="true" />

      <!-- Student -->
      <Column field="student.name" header="Học viên" style="min-width: 200px">
        <template #body="{ data }">
          {{ data.student?.code }} · {{ data.student?.name }}
        </template>
      </Column>

      <!-- From class -->
      <Column field="from_class.name" header="Lớp cũ" style="min-width: 180px">
        <template #body="{ data }">
          {{ data.from_class?.code }} · {{ data.from_class?.name }}
        </template>
      </Column>

      <!-- To class -->
      <Column field="to_class.name" header="Lớp mới" style="min-width: 180px">
        <template #body="{ data }">
          {{ data.to_class?.code }} · {{ data.to_class?.name }}
        </template>
      </Column>

      <!-- Effective date -->
      <Column field="effective_date" header="Ngày hiệu lực" style="width: 140px" :sortable="true">
        <template #body="{ data }">
          {{ toDdMmYyyy(data.effective_date) }}
        </template>
      </Column>

      <!-- Auto invoice -->
      <Column field="auto_invoice" header="Tự động tạo hoá đơn" style="width: 180px">
        <template #body="{ data }">
          <Tag :value="data.auto_invoice ? 'Có' : 'Không'" :severity="data.auto_invoice ? 'success' : 'secondary'" />
        </template>
      </Column>

      <!-- Actions -->
      <Column header="Hành động" style="width: 180px">
        <template #body="{ data }">
          <div class="flex justify-end gap-2">
            <Link
              :href="route('admin.transfers.edit', data.id)"
              class="px-2 py-1 rounded border border-emerald-300 text-emerald-700 hover:bg-emerald-50
                     dark:border-emerald-700 dark:text-emerald-300 dark:hover:bg-emerald-900/20"
            >
              <i class="pi pi-pencil"></i>
            </Link>
            <button
              @click="destroyTransfer(data.id)"
              class="px-2 py-1 rounded border border-red-300 text-red-600 hover:bg-red-50
                     dark:border-red-700 dark:text-red-300 dark:hover:bg-red-900/20"
            >
              <i class="pi pi-trash"></i>
            </button>
          </div>
        </template>
      </Column>

      <!-- Empty state -->
      <template #empty>
        <div class="p-6 text-center text-slate-500 dark:text-slate-400">
          Chưa có bản ghi chuyển lớp.
        </div>
      </template>
    </DataTable>
  </div>
</template>
