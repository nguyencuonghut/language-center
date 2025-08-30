<!-- resources/js/Pages/Manager/Students/Index.vue -->
<script setup>
import { reactive, computed, ref } from 'vue'
import { Head, Link } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import { createStudentService } from '@/service/StudentService'

// PrimeVue
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import InputText from 'primevue/inputtext'
import Select from 'primevue/select'
import Button from 'primevue/button'
import Tag from 'primevue/tag'

defineOptions({ layout: AppLayout })

const props = defineProps({
  students: Object, // LengthAwarePaginator
  filters: Object,  // { q:'', perPage:number, sort:'', order:'asc'|'desc' }
})

const studentService = createStudentService()

/* -------- Helpers -------- */
function toDdMmYyyy(input) {
  if (!input) return '—'
  const dt = new Date(String(input).replace(' ', 'T'))
  if (isNaN(dt.getTime())) {
    const [y,m,d] = String(input).split('-')
    if (y && m && d) return `${d.padStart(2,'0')}/${m.padStart(2,'0')}/${y}`
    return String(input)
  }
  const dd = String(dt.getDate()).padStart(2,'0')
  const mm = String(dt.getMonth()+1).padStart(2,'0')
  const yy = dt.getFullYear()
  return `${dd}/${mm}/${yy}`
}

/* -------- Local UI state -------- */
const state = reactive({
  q: props.filters?.q ?? '',
  perPage: props.filters?.perPage ?? (props.students?.per_page ?? 20),
})

/* -------- Sorting state -------- */
const sortField = ref(props.filters?.sort || null)
const sortOrder = ref(
  props.filters?.order === 'asc' ? 1 :
  props.filters?.order === 'desc' ? -1 : null
)

/* -------- Query helpers -------- */
function buildQuery(extra = {}) {
  const query = {}
  if (state.q && state.q.trim() !== '') query.q = state.q.trim()
  if (state.perPage && state.perPage !== props.students?.per_page) query.per_page = state.perPage
  if (sortField.value) query.sort = sortField.value
  if (sortOrder.value !== null) query.order = sortOrder.value === 1 ? 'asc' : 'desc'
  Object.assign(query, extra)
  return query
}

function applyFilters() {
  studentService.getList(buildQuery())
}

function onClearSearch() {
  state.q = ''
  applyFilters()
}

function onPage(event) {
  const page = Math.floor(event.first / event.rows) + 1
  studentService.getList(buildQuery({
    per_page: event.rows,
    page: page > 1 ? page : undefined
  }))
}

function onSort(event) {
  sortField.value = event.sortField
  sortOrder.value = event.sortOrder
  applyFilters()
}

/* -------- Actions -------- */
function destroy(id) {
  studentService.delete(id)
}

/* -------- DataTable computed -------- */
const value = computed(() => props.students?.data ?? [])
const totalRecords = computed(() => props.students?.total ?? value.value.length)
const rows = computed(() => props.students?.per_page ?? 20)
const first = computed(() => Math.max(0, (props.students?.from ?? 1) - 1))

/* -------- UI helpers -------- */
function activeSeverity(active) {
  return active ? 'success' : 'danger'
}
</script>

<template>
  <Head title="Học viên" />

  <div class="mb-3 flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
    <h1 class="text-xl md:text-2xl font-heading font-semibold">Học viên</h1>

    <div class="flex flex-wrap items-center gap-2">
      <!-- Search -->
      <span class="inline-flex items-center gap-1">
        <InputText
          v-model="state.q"
          placeholder="Tìm tên / mã / SĐT / email..."
          class="w-72"
          @keydown.enter="applyFilters"
        />
        <Button icon="pi pi-search" text aria-label="Tìm kiếm" @click="applyFilters" />
        <Button icon="pi pi-times" text aria-label="Xoá" :disabled="!state.q" @click="onClearSearch" />
      </span>

      <!-- PerPage -->
      <Select
        v-model="state.perPage"
        :options="[{label:'20 / trang',value:20},{label:'50 / trang',value:50},{label:'100 / trang',value:100}]"
        optionLabel="label"
        optionValue="value"
        class="w-40"
        @change="applyFilters"
      />

      <Link
        :href="route('manager.students.create')"
        class="px-3 py-2 rounded-lg bg-emerald-600 text-white hover:bg-emerald-700"
      >
        <i class="pi pi-plus mr-1"></i> Thêm học viên
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
      <Column field="code" header="Mã" style="width: 140px" :sortable="true" />
      <Column field="name" header="Họ tên" :sortable="true" />
      <Column field="gender" header="Giới tính" style="width: 120px" :sortable="true" />
      <Column field="dob" header="Ngày sinh" style="width: 150px" :sortable="true">
        <template #body="{ data }">{{ toDdMmYyyy(data.dob) }}</template>
      </Column>
      <Column field="email" header="Email" :sortable="true" />
      <Column field="phone" header="Điện thoại" style="width: 160px" :sortable="true" />
      <Column field="active" header="Trạng thái" style="width: 140px" :sortable="true">
        <template #body="{ data }">
          <Tag :value="data.active ? 'Đang hoạt động' : 'Ngừng'" :severity="activeSeverity(data.active)" />
        </template>
      </Column>

      <Column header="" style="width: 280px">
        <template #body="{ data }">
          <div class="flex gap-2 justify-end">
            <Link
              :href="route('manager.students.show', data.id)"
              class="px-3 py-1.5 rounded border border-slate-300 hover:bg-slate-50 dark:border-slate-700 dark:hover:bg-slate-900/20"
            >
              Chi tiết
            </Link>
            <Link
              :href="route('manager.students.edit', data.id)"
              class="px-3 py-1.5 rounded border border-emerald-300 text-emerald-700 hover:bg-emerald-50
                     dark:border-emerald-700 dark:text-emerald-300 dark:hover:bg-emerald-900/20"
            >
              <i class="pi pi-pencil mr-1"></i> Sửa
            </Link>
            <button
              @click="destroy(data.id)"
              class="px-3 py-1.5 rounded border border-red-300 text-red-600 hover:bg-red-50
                     dark:border-red-700 dark:text-red-300 dark:hover:bg-red-900/20"
            >
              <i class="pi pi-trash mr-1"></i> Xoá
            </button>
          </div>
        </template>
      </Column>

      <template #empty>
        <div class="p-6 text-center text-slate-500 dark:text-slate-400">Chưa có học viên nào.</div>
      </template>
    </DataTable>
  </div>
</template>
