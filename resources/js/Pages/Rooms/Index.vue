<script setup>
import { reactive, computed, ref } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import { createRoomService } from '@/service/RoomService'
import { usePageToast } from '@/composables/usePageToast'

// PrimeVue
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import InputText from 'primevue/inputtext'
import Select from 'primevue/select'
import Button from 'primevue/button'
import Tag from 'primevue/tag'

defineOptions({ layout: AppLayout })

const props = defineProps({
  rooms: Object,     // LengthAwarePaginator
  branches: Array,   // [{id,name}]
  filters: Object,   // {branch:'all'|id, q:'', perPage:number, sort:'', order:''}
})

const { showSuccess, showError } = usePageToast()
const roomService = createRoomService({ showSuccess, showError })

/* ---- Local UI state (đọc từ props.filters) ---- */
const state = reactive({
  q: props.filters?.q ?? '',
  branch: props.filters?.branch ?? 'all',
  perPage: props.filters?.perPage ?? (props.rooms?.per_page ?? 12),
})

/* ---- Sorting state ---- */
const sortField = ref(props.filters?.sort || null)
const sortOrder = ref(props.filters?.order === 'asc' ? 1 : props.filters?.order === 'desc' ? -1 : null)

/* ---- Helpers để request lại trang theo filter/pagination ---- */
function applyFilters() {
  const query = {}
  if (state.branch && state.branch !== 'all') query.branch = state.branch
  if (state.q && state.q.trim() !== '') query.q = state.q.trim()
  if (state.perPage && state.perPage !== props.rooms?.per_page) query.per_page = state.perPage
  if (sortField.value) query.sort = sortField.value
  if (sortOrder.value !== null) query.order = sortOrder.value === 1 ? 'asc' : 'desc'

  roomService.getList(query)
}

function onClearSearch() {
  state.q = ''
  applyFilters()
}

/* DataTable phân trang server-side */
function onPage(event) {
  const page = Math.floor(event.first / event.rows) + 1
  const query = {}
  if (state.branch && state.branch !== 'all') query.branch = state.branch
  if (state.q && state.q.trim() !== '') query.q = state.q.trim()
  if (event.rows) query.per_page = event.rows
  if (page > 1) query.page = page
  if (sortField.value) query.sort = sortField.value
  if (sortOrder.value !== null) query.order = sortOrder.value === 1 ? 'asc' : 'desc'

  roomService.getList(query)
}

function onSort(event) {
  sortField.value = event.sortField
  sortOrder.value = event.sortOrder
  applyFilters()
}

function destroy(id) {
  roomService.delete(id)
}

/* Tính toán props cho DataTable */
const value = computed(() => props.rooms?.data ?? [])
const totalRecords = computed(() => props.rooms?.total ?? value.value.length)
const rows = computed(() => props.rooms?.per_page ?? 12)
const first = computed(() => Math.max(0, (props.rooms?.from ?? 1) - 1))
</script>

<template>
  <!-- Title trang (browser tab) -->
  <Head title="Phòng học" />

  <!-- Tiêu đề hiển thị trên trang -->
  <div class="mb-3 flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
    <h1 class="text-xl md:text-2xl font-heading font-semibold">Phòng học</h1>

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
        <InputText v-model="state.q" placeholder="Tìm mã/tên phòng..." class="w-60" @keydown.enter="applyFilters" />
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

      <Link :href="route('admin.rooms.create')" class="px-3 py-2 rounded-lg bg-emerald-600 text-white hover:bg-emerald-700">
        <i class="pi pi-plus mr-1"></i> Tạo phòng
      </Link>
    </div>
  </div>

  <!-- DataTable -->
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

      <Column header="Chi nhánh" style="width: 200px" field="branch.name" :sortable="true">
        <template #body="{ data }">
          <span>{{ data.branch?.name ?? '—' }}</span>
        </template>
      </Column>

      <Column field="capacity" header="Sức chứa" style="width: 120px" :sortable="true">
        <template #body="{ data }">
          <Tag :value="data.capacity" />
        </template>
      </Column>

      <Column header="" style="width: 180px">
        <template #body="{ data }">
          <div class="flex gap-2 justify-end">
            <Link :href="route('admin.rooms.edit', data.id)" class="px-3 py-1.5 rounded border border-emerald-300 text-emerald-700 hover:bg-emerald-50 dark:border-emerald-700 dark:text-emerald-300 dark:hover:bg-emerald-900/20">
              <i class="pi pi-pencil mr-1"></i>Sửa
            </Link>
            <button @click="destroy(data.id)" class="px-3 py-1.5 rounded border border-red-300 text-red-600 hover:bg-red-50 dark:border-red-700 dark:text-red-300 dark:hover:bg-red-900/20">
              <i class="pi pi-trash mr-1"></i>Xoá
            </button>
          </div>
        </template>
      </Column>

      <template #empty>
        <div class="p-6 text-center text-slate-500 dark:text-slate-400">Chưa có phòng nào.</div>
      </template>
    </DataTable>
  </div>
</template>
