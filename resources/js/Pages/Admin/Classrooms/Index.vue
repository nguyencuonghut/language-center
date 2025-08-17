<script setup>
import { reactive, computed, ref } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'

// PrimeVue
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import InputText from 'primevue/inputtext'
import Select from 'primevue/select'
import Button from 'primevue/button'
import Tag from 'primevue/tag'

defineOptions({ layout: AppLayout })

const props = defineProps({
  classrooms: Object, // LengthAwarePaginator
  branches: Array,    // [{id,name}]
  filters: Object,    // {branch:'all'|id, q:'', perPage:number, sort:'', order:''}
})

/* ---- Local UI state ---- */
const state = reactive({
  q: props.filters?.q ?? '',
  branch: props.filters?.branch ?? 'all',
  perPage: props.filters?.perPage ?? (props.classrooms?.per_page ?? 12),
})

/* ---- Sorting state ---- */
const sortField = ref(props.filters?.sort || null)
const sortOrder = ref(props.filters?.order === 'asc' ? 1 : props.filters?.order === 'desc' ? -1 : null)

/* ---- Helpers: gọi lại trang theo filter/pagination ---- */
function buildQuery(extra = {}) {
  const query = {}
  if (state.branch && state.branch !== 'all') query.branch = state.branch
  if (state.q && state.q.trim() !== '') query.q = state.q.trim()
  if (state.perPage && state.perPage !== props.classrooms?.per_page) query.per_page = state.perPage
  if (sortField.value) query.sort = sortField.value
  if (sortOrder.value !== null) query.order = sortOrder.value === 1 ? 'asc' : 'desc'
  Object.assign(query, extra)
  return query
}

function applyFilters() {
  router.visit(route('admin.classrooms.index', buildQuery()), { preserveScroll: true, preserveState: true })
}

function onClearSearch() {
  state.q = ''
  applyFilters()
}

function onPage(event) {
  const page = Math.floor(event.first / event.rows) + 1
  router.visit(route('admin.classrooms.index', buildQuery({
    per_page: event.rows,
    page: page > 1 ? page : undefined,
  })), { preserveScroll: true, preserveState: true })
}

function onSort(event) {
  sortField.value = event.sortField
  sortOrder.value = event.sortOrder
  applyFilters()
}

/* Actions */
function destroy(id) {
  if (confirm('Xoá lớp này?')) {
    router.delete(route('admin.classrooms.destroy', id), { preserveScroll: true })
  }
}

/* DataTable props */
const value = computed(() => props.classrooms?.data ?? [])
const totalRecords = computed(() => props.classrooms?.total ?? value.value.length)
const rows = computed(() => props.classrooms?.per_page ?? 12)
const first = computed(() => Math.max(0, (props.classrooms?.from ?? 1) - 1))

/* Helpers hiển thị Tag status */
function statusSeverity(s) {
  switch (s) {
    case 'open': return 'success'
    case 'closed': return 'danger'
    default: return 'info'
  }
}
</script>

<template>
  <Head title="Lớp học" />

  <div class="mb-3 flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
    <h1 class="text-xl md:text-2xl font-heading font-semibold">Lớp học</h1>

    <div class="flex flex-wrap items-center gap-2">
      <!-- Branch filter -->
      <Select
        v-model="state.branch"
        :options="[{label:'Tất cả chi nhánh', value:'all'}, ...(branches||[]).map(b=>({label:b.name, value:String(b.id)}))]"
        optionLabel="label"
        optionValue="value"
        :pt="{ root: { class: 'min-w-[220px]' } }"
        @change="applyFilters"
      />
      <!-- Search -->
      <span class="inline-flex items-center gap-1">
        <InputText v-model="state.q" placeholder="Tìm mã/tên lớp..." class="w-60" @keydown.enter="applyFilters" />
        <Button icon="pi pi-search" text @click="applyFilters" :aria-label="'Tìm kiếm'" />
        <Button icon="pi pi-times" text @click="onClearSearch" :disabled="!state.q" :aria-label="'Xoá tìm kiếm'" />
      </span>
      <!-- PerPage -->
      <Select
        v-model="state.perPage"
        :options="[{label:'12 / trang',value:12},{label:'24 / trang',value:24},{label:'50 / trang',value:50}]"
        optionLabel="label" optionValue="value"
        class="w-36"
        @change="applyFilters"
      />

      <!-- Nút tạo lớp (sẽ làm ở bước sau) -->
      <Link :href="route('admin.classrooms.create')" class="px-3 py-2 rounded-lg bg-emerald-600 text-white hover:bg-emerald-700">
        <i class="pi pi-plus mr-1"></i> Tạo lớp
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
      <Column field="name" header="Tên" :sortable="true" />

      <Column header="Chi nhánh" field="branch_name" style="width: 200px" :sortable="true">
        <template #body="{ data }">
          <span>{{ data.branch_name ?? '—' }}</span>
        </template>
      </Column>

      <Column header="Khóa học" field="course_name" style="width: 220px" :sortable="true">
        <template #body="{ data }">
          <span>{{ data.course_name ?? '—' }}</span>
        </template>
      </Column>

      <Column header="Giáo viên" field="teacher_name" style="width: 220px" :sortable="true">
        <template #body="{ data }">
          <span>{{ data.teacher_name ?? '—' }}</span>
        </template>
      </Column>

      <Column field="start_date" header="Bắt đầu" style="width: 140px" :sortable="true" />
      <Column field="sessions_total" header="Số buổi" style="width: 120px" :sortable="true" />

      <Column header="Trạng thái" field="status" style="width: 140px" :sortable="true">
        <template #body="{ data }">
          <Tag :value="data.status" :severity="statusSeverity(data.status)" />
        </template>
      </Column>

      <Column header="" style="width: 180px">
        <template #body="{ data }">
          <div class="flex gap-2 justify-end">
            <Link :href="route('admin.classrooms.edit', data.id)" class="px-3 py-1.5 rounded border border-emerald-300 text-emerald-700 hover:bg-emerald-50 dark:border-emerald-700 dark:text-emerald-300 dark:hover:bg-emerald-900/20">
              <i class="pi pi-pencil mr-1"></i>Sửa
            </Link>
            <button @click="destroy(data.id)" class="px-3 py-1.5 rounded border border-red-300 text-red-600 hover:bg-red-50 dark:border-red-700 dark:text-red-300 dark:hover:bg-red-900/20">
              <i class="pi pi-trash mr-1"></i>Xoá
            </button>
          </div>
        </template>
      </Column>

      <template #empty>
        <div class="p-6 text-center text-slate-500 dark:text-slate-400">Chưa có lớp nào.</div>
      </template>
    </DataTable>
  </div>
</template>
