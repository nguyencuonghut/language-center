<script setup>
import { reactive, ref, computed } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import TransferFormModal from '@/Components/TransferFormModal.vue'
import { createTransferService } from '@/service/TransferService.js'

// PrimeVue
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import Button from 'primevue/button'
import Tag from 'primevue/tag'
import Select from 'primevue/select'
import Dialog from 'primevue/dialog'
import AutoComplete from 'primevue/autocomplete'
import DatePicker from 'primevue/datepicker'
import Checkbox from 'primevue/checkbox'
import InputText from 'primevue/inputtext'
import ToggleSwitch from 'primevue/toggleswitch'

defineOptions({ layout: AppLayout })

const props = defineProps({
  classroom: Object,       // {id, code, name, ...}
  enrollments: Object,     // paginator
  filters: Object,         // {perPage, sort, order}
  // [{label: 'STU001 · Nguyễn A (098...)', value: 1, code:'STU001', name:'Nguyễn A'}] — server gợi ý sẵn
  suggestStudents: Array,
})

// Initialize TransferService (no toast injection - handled by AppLayout)
const transferService = createTransferService()

/* ---------------- Helpers ---------------- */
const statusOptions = [
  {label: 'Đang học', value: 'active'},
  {label: 'Chuyển lớp', value: 'transferred'},
  {label: 'Hoàn thành', value: 'completed'},
  {label: 'Bỏ học', value: 'dropped'},
]
function statusSeverity(v){
  switch(v){
    case 'active': return 'info'
    case 'completed': return 'success'
    case 'transferred': return 'warning'
    case 'dropped': return 'danger'
    default: return 'info'
  }
}
function toYmdLocal(d) {
  if (!d) return null
  const dt = new Date(d)
  const y = dt.getFullYear()
  const m = String(dt.getMonth() + 1).padStart(2, '0')
  const day = String(dt.getDate()).padStart(2, '0')
  return `${y}-${m}-${day}`
}
function routeExists(name){
  try { return route().has(name) } catch { return false }
}

/* ---------------- List state ---------------- */
const state = reactive({
  perPage: props.filters?.perPage ?? (props.enrollments?.per_page ?? 12),
})
const sortField = ref(props.filters?.sort || null)
const sortOrder = ref(props.filters?.order === 'desc' ? -1 : (props.filters?.order === 'asc' ? 1 : null))

function buildQuery(extra = {}) {
  const q = {}
  if (state.perPage && state.perPage !== props.enrollments?.per_page) q.per_page = state.perPage
  if (sortField.value) q.sort = sortField.value
  if (sortOrder.value !== null) q.order = sortOrder.value === 1 ? 'asc' : 'desc'
  Object.assign(q, extra)
  return q
}
function applyFilters(){
  router.visit(route('manager.classrooms.enrollments.index', { classroom: props.classroom.id, ...buildQuery() }),
    { preserveScroll: true, preserveState: true })
}
function onPage(e){
  const page = Math.floor(e.first / e.rows) + 1
  router.visit(route('manager.classrooms.enrollments.index', {
    classroom: props.classroom.id,
    ...buildQuery({ per_page: e.rows, page: page > 1 ? page : undefined })
  }), { preserveScroll: true, preserveState: true })
}
function onSort(e){
  sortField.value = e.sortField
  sortOrder.value = e.sortOrder
  applyFilters()
}

const value = computed(() => props.enrollments?.data ?? [])
const totalRecords = computed(() => props.enrollments?.total ?? value.value.length)
const rows = computed(() => props.enrollments?.per_page ?? 12)
const first = computed(() => Math.max(0, (props.enrollments?.from ?? 1) - 1))

/* ---------------- Dialog: Enroll ---------------- */
const showDialog = ref(false)
const isBulk = ref(false) // false = một học viên; true = nhiều học viên

// Ensure suggestions are in correct format
const formattedSuggestions = computed(() => {
  const suggestions = props.suggestStudents || []

  // Data is already in correct format, just return as-is
  return suggestions
})

const acItems = ref([])

function searchStudents(e) {
  const q = (e?.query || '').toLowerCase()
  const src = formattedSuggestions.value || []

  // Lọc theo label; nếu không nhập gì (bấm dropdown), trả về tất cả (giới hạn 50 cho nhanh UI)
  if (!q) {
    acItems.value = src.slice(0, 50)
  } else {
    acItems.value = src.filter(s => String(s.label || '').toLowerCase().includes(q)).slice(0, 50)
  }
}

const form = reactive({
  studentObj: null,        // object {label,value,...} cho 1 học viên
  studentsMulti: [],       // array cho nhiều học viên
  enrolled_at: null,
  start_session_no: '1',
  status: 'active',
  errors: {},
  saving: false,
})

function openDialog(){
  isBulk.value = false
  form.studentObj = null
  form.studentsMulti = []
  form.enrolled_at = null
  form.start_session_no = '1'
  form.status = 'active'
  form.errors = {}
  showDialog.value = true
}

function submitEnroll(){
  form.errors = {}

  // validate cơ bản
  if (!isBulk.value) {
    if (!form.studentObj?.value) {
      form.errors.student_id = 'Vui lòng chọn học viên'
      return
    }
  } else {
    const ids = (form.studentsMulti || []).map(x => x.value)
    if (!ids.length) {
      form.errors.student_ids = 'Vui lòng chọn ít nhất 1 học viên'
      return
    }
  }

  const payload = {
    enrolled_at: form.enrolled_at ? toYmdLocal(form.enrolled_at) : null,
    start_session_no: Number(String(form.start_session_no || '1')),
    status: form.status,
  }

  let routeName
  if (isBulk.value) {
    payload.student_ids = (form.studentsMulti || []).map(x => x.value)
    routeName = routeExists('manager.classrooms.enrollments.bulk-store')
      ? 'manager.classrooms.enrollments.bulk-store'
      : 'manager.classrooms.enrollments.store' // fallback: backend tự nhận diện mảng student_ids
  } else {
    payload.student_id = form.studentObj.value
    routeName = 'manager.classrooms.enrollments.store'
  }

  form.saving = true
  router.post(route(routeName, { classroom: props.classroom.id }), payload, {
    preserveScroll: true,
    onFinish: () => { form.saving = false },
    onSuccess: () => { showDialog.value = false },
    onError: (errors) => { form.errors = errors || {} }
  })
}

function destroy(id){
  if (!confirm('Xoá ghi danh này?')) return
  router.delete(route('manager.classrooms.enrollments.destroy', { classroom: props.classroom.id, enrollment: id }), {
    preserveScroll: true
  })
}

/* ---------------- Transfer Class Modal ---------------- */
const showTransferModal = ref(false)
const transferData = reactive({
  student: {},
  fromClass: {}
})

async function transferClass(id){
  // Find enrollment data
  const enrollment = value.value.find(e => e.id === id)
  if (!enrollment) return

  // Prepare data for TransferFormModal
  transferData.student = {
    id: enrollment.student?.id ?? enrollment.student_id,
    code: enrollment.student?.code ?? enrollment.student_code ?? enrollment.code,
    name: enrollment.student?.name ?? enrollment.student_name ?? enrollment.name ?? '—'
  }

  transferData.fromClass = {
    id: props.classroom.id,
    code: props.classroom.code,
    name: props.classroom.name
  }

  showTransferModal.value = true
}

function onTransferSuccess(){
  showTransferModal.value = false
  // Refresh the enrollments data
  router.visit(route('manager.classrooms.enrollments.index', { classroom: props.classroom.id, ...buildQuery() }),
    { preserveScroll: true, preserveState: true })
}
</script>

<template>
  <Head :title="`Ghi danh - ${classroom.name}`" />

  <div class="mb-3 flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
    <div>
      <h1 class="text-xl md:text-2xl font-heading font-semibold">Ghi danh học viên</h1>
      <div class="text-slate-500 dark:text-slate-400 text-sm">
        Lớp: <span class="font-medium text-slate-900 dark:text-slate-100">{{ classroom.name }}</span>
      </div>
    </div>

    <div class="flex flex-wrap items-center gap-2">
      <Button label="Ghi danh" icon="pi pi-user-plus" @click="openDialog" />

      <Link
        :href="route('manager.classrooms.edit', { classroom: classroom.id })"
        class="px-3 py-1.5 rounded-lg border border-slate-300 dark:border-slate-600 hover:bg-slate-100 dark:hover:bg-slate-800"
      >
        Chi tiết lớp
      </Link>
      <Link
        :href="route('manager.classrooms.index')"
        class="px-3 py-1.5 rounded-lg border border-slate-300 dark:border-slate-600 hover:bg-slate-100 dark:hover:bg-slate-800"
      >
        ← Danh sách lớp
      </Link>

      <Select
        v-model="state.perPage"
        :options="[{label:'20 / trang',value:20},{label:'50 / trang',value:50},{label:'100 / trang',value:100}]"
        optionLabel="label" optionValue="value"
        class="w-40"
        @change="applyFilters"
      />
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
      paginatorTemplate="RowsPerPageDropdown FirstPageLink PrevPageLink PageLinks NextPageLink LastPageLink CurrentPageReport"
      currentPageReportTemplate="Hiển thị {first} - {last} trên tổng số {totalRecords} bản ghi"
      :rowsPerPageOptions="[12, 24, 48]"
    >
      <Column field="id" header="#" style="width: 80px" :sortable="true" />

      <!-- Mã -->
      <Column header="Mã" style="width: 140px" :sortable="false">
        <template #body="{ data }">
          {{ data.student?.code ?? data.student_code ?? data.code ?? '—' }}
        </template>
      </Column>

      <!-- Học viên -->
      <Column header="Học viên" :sortable="false">
        <template #body="{ data }">
          {{ data.student?.name ?? data.student_name ?? data.name ?? '—' }}
        </template>
      </Column>

      <Column field="start_session_no" header="Bắt đầu từ buổi" style="width: 160px" :sortable="true" />
      <Column field="enrolled_at" header="Ngày ghi danh" style="width: 160px" :sortable="true" />

      <Column field="status" header="Trạng thái" style="width: 140px" :sortable="true">
        <template #body="{ data }">
          <Tag :value="data.status" :severity="statusSeverity(data.status)" />
        </template>
      </Column>

      <Column header="" style="width: 280px">
        <template #body="{ data }">
          <div class="flex justify-end gap-2">
            <button
              class="px-3 py-1.5 rounded border border-amber-300 text-amber-600 hover:bg-amber-50 dark:border-amber-700 dark:text-amber-300 dark:hover:bg-amber-900/20"
              @click="transferClass(data.id)"
            >
              <i class="pi pi-arrow-right mr-1" /> Chuyển lớp
            </button>
            <button
              class="px-3 py-1.5 rounded border border-red-300 text-red-600 hover:bg-red-50 dark:border-red-700 dark:text-red-300 dark:hover:bg-red-900/20"
              @click="destroy(data.id)"
            >
              <i class="pi pi-trash mr-1" /> Xoá
            </button>
          </div>
        </template>
      </Column>

      <template #empty>
        <div class="p-6 text-center text-slate-500 dark:text-slate-400">Chưa có ghi danh nào.</div>
      </template>
    </DataTable>
  </div>

  <!-- Dialog: Ghi danh -->
  <Dialog v-model:visible="showDialog" modal header="Ghi danh học viên" :style="{ width: '560px' }">
    <!-- Chế độ -->
    <div class="flex items-center justify-between mb-3">
      <div class="text-sm text-slate-500">Chế độ</div>
      <div class="flex items-center gap-3">
        <span class="text-sm" :class="{ 'font-medium': !isBulk, 'text-slate-500': isBulk }">
          Một học viên
        </span>
        <ToggleSwitch v-model="isBulk" />
        <span class="text-sm" :class="{ 'font-medium': isBulk, 'text-slate-500': !isBulk }">
          Nhiều học viên
        </span>
      </div>
    </div>

    <div class="flex flex-col gap-4">
      <!-- Học viên -->
      <div>
        <label class="block text-sm font-medium mb-1">Học viên</label>

        <!-- Single -->
        <AutoComplete
          v-if="!isBulk"
          v-model="form.studentObj"
          :suggestions="acItems"
          optionLabel="label"
          dropdown
          class="w-full"
          placeholder="Nhập tên / mã / SĐT / email..."
          @complete="searchStudents"
          :forceSelection="true"
        />
        <div v-if="form.errors?.student_id" class="text-red-500 text-xs mt-1">
          {{ form.errors.student_id }}
        </div>

        <!-- Bulk -->
        <AutoComplete
          v-if="isBulk"
          v-model="form.studentsMulti"
          :suggestions="acItems"
          optionLabel="label"
          multiple
          dropdown
          class="w-full"
          placeholder="Nhập để tìm & chọn nhiều học viên..."
          @complete="searchStudents"
        />
        <div v-if="isBulk && form.errors?.student_ids" class="text-red-500 text-xs mt-1">
          {{ form.errors.student_ids }}
        </div>
      </div>

      <!-- Ngày ghi danh & buổi bắt đầu -->
      <div class="grid grid-cols-2 gap-3">
        <div>
          <label class="block text-sm font-medium mb-1">Ngày ghi danh</label>
          <DatePicker v-model="form.enrolled_at" dateFormat="yy-mm-dd" showIcon iconDisplay="input" class="w-full" />
          <div v-if="form.errors?.enrolled_at" class="text-red-500 text-xs mt-1">
            {{ form.errors.enrolled_at }}
          </div>
        </div>
        <div>
          <label class="block text-sm font-medium mb-1">Bắt đầu từ buổi số</label>
          <InputText v-model="form.start_session_no" class="w-full" placeholder="1" />
          <div v-if="form.errors?.start_session_no" class="text-red-500 text-xs mt-1">
            {{ form.errors.start_session_no }}
          </div>
        </div>
      </div>

      <div>
        <label class="block text-sm font-medium mb-1">Trạng thái</label>
        <Select v-model="form.status" :options="statusOptions" optionLabel="label" optionValue="value" class="w-full" />
        <div v-if="form.errors?.status" class="text-red-500 text-xs mt-1">{{ form.errors.status }}</div>
      </div>
    </div>

    <template #footer>
      <Button label="Huỷ" icon="pi pi-times" text @click="showDialog=false" />
      <Button label="Ghi danh" icon="pi pi-check" :loading="form.saving" @click="submitEnroll" autofocus />
    </template>
  </Dialog>

  <!-- Transfer Class Modal -->
  <TransferFormModal
    v-model:visible="showTransferModal"
    :student="transferData.student"
    :from-class="transferData.fromClass"
    @success="onTransferSuccess"
  />
</template>
