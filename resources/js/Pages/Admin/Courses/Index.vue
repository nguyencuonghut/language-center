<script setup>
import { reactive, computed, ref } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'

// PrimeVue
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import Button from 'primevue/button'
import Tag from 'primevue/tag'
import Select from 'primevue/select'

// Service
import { createCourseService } from '@/service/CourseService'
import { usePageToast } from '@/composables/usePageToast'

defineOptions({ layout: AppLayout })

const props = defineProps({
  courses: Object,  // paginator
  filters: Object   // {sort, order, perPage}
})

const { showSuccess, showError } = usePageToast()
const courseService = createCourseService({ showSuccess, showError })

/* ---- Local UI state ---- */
const state = reactive({
  perPage: props.filters?.perPage ?? (props.courses?.per_page ?? 12),
})

/* ---- Sorting ---- */
const sortField = ref(props.filters?.sort || 'code')
const sortOrder = ref(
  props.filters?.order === 'desc' ? -1 :
  props.filters?.order === 'asc' ? 1 : 1
)

/* ---- Helpers ---- */
function applyFilters() {
  const query = {}
  if (state.perPage !== props.courses?.per_page) query.per_page = state.perPage
  if (sortField.value) query.sort = sortField.value
  if (sortOrder.value !== null) query.order = sortOrder.value === 1 ? 'asc' : 'desc'
  courseService.getList(query)
}

function onPage(event) {
  const page = Math.floor(event.first / event.rows) + 1
  const query = { page }
  if (event.rows) query.per_page = event.rows
  if (sortField.value) query.sort = sortField.value
  if (sortOrder.value !== null) query.order = sortOrder.value === 1 ? 'asc' : 'desc'
  courseService.getList(query)
}

function onSort(event) {
  sortField.value = event.sortField
  sortOrder.value = event.sortOrder
  applyFilters()
}

function destroy(id) {
  courseService.delete(id)
}

/* ---- Data ---- */
const value = computed(() => props.courses?.data ?? [])
const totalRecords = computed(() => props.courses?.total ?? 0)
const rows = computed(() => props.courses?.per_page ?? 12)
const first = computed(() => Math.max(0, (props.courses?.from ?? 1) - 1))

const audienceLabels = {
  kids: 'Thiếu nhi',
  student: 'Học sinh/SV',
  working: 'Người đi làm',
  toeic: 'TOEIC',
  ielts: 'IELTS'
}

const languageLabels = {
  en: 'Tiếng Anh',
  zh: 'Tiếng Trung',
  ko: 'Tiếng Hàn',
  ja: 'Tiếng Nhật'
}
</script>

<template>
  <Head title="Khóa học" />

  <!-- Header -->
  <div class="mb-3 flex justify-between items-center">
    <h1 class="text-xl md:text-2xl font-heading font-semibold">Danh sách khóa học</h1>
    <div class="flex items-center gap-2">
      <Link
        :href="route('admin.courses.create')"
        class="px-3 py-2 rounded-lg bg-emerald-600 text-white hover:bg-emerald-700"
      >
        <i class="pi pi-plus mr-1"></i> Thêm khóa học
      </Link>
      <Select
        v-model="state.perPage"
        :options="[{label:'12 / trang',value:12},{label:'24 / trang',value:24},{label:'48 / trang',value:48}]"
        optionLabel="label" optionValue="value"
        class="w-40"
        @change="applyFilters"
      />
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
      <Column field="code" header="Mã" style="width: 160px" :sortable="true" />
      <Column field="name" header="Tên khóa học" :sortable="true" />
      <Column field="audience" header="Đối tượng" :sortable="true" style="width: 160px">
        <template #body="{ data }">{{ audienceLabels[data.audience] || data.audience }}</template>
      </Column>
      <Column field="language" header="Ngôn ngữ" :sortable="true" style="width: 160px">
        <template #body="{ data }">{{ languageLabels[data.language] || data.language }}</template>
      </Column>
      <Column field="active" header="Trạng thái" :sortable="true" style="width: 160px">
        <template #body="{ data }">
          <Tag
            :value="data.active ? 'Đang hoạt động' : 'Ngừng'"
            :severity="data.active ? 'success' : 'danger'"
          />
        </template>
      </Column>
      <Column header="Hành động" style="width: 220px">
        <template #body="{ data }">
          <div class="flex justify-end gap-2">
            <Link
              :href="route('admin.courses.edit', data.id)"
              class="px-3 py-1.5 rounded border border-emerald-300 text-emerald-700 hover:bg-emerald-50 dark:border-emerald-700 dark:text-emerald-300 dark:hover:bg-emerald-900/20"
            >
              <i class="pi pi-pencil mr-1"></i>Sửa
            </Link>
            <button
              @click="destroy(data.id)"
              class="px-3 py-1.5 rounded border border-red-300 text-red-600 hover:bg-red-50 dark:border-red-700 dark:text-red-300 dark:hover:bg-red-900/20"
            >
              <i class="pi pi-trash mr-1"></i>Xoá
            </button>
          </div>
        </template>
      </Column>
      <template #empty>
        <div class="p-6 text-center text-slate-500 dark:text-slate-400">Chưa có khóa học.</div>
      </template>
    </DataTable>
  </div>
</template>
