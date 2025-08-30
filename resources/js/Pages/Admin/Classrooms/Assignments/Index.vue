<script setup>
import { reactive, computed, ref } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import { createTeachingAssignmentService } from '@/service/TeachingAssignmentService'

// PrimeVue
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import Button from 'primevue/button'
import Dialog from 'primevue/dialog'
import Select from 'primevue/select'
import InputNumber from 'primevue/inputnumber'
import DatePicker from 'primevue/datepicker'

defineOptions({ layout: AppLayout })

const props = defineProps({
  classroom: Object,   // {id, code, name}
  assignments: Object, // paginator
  teachers: Array,     // [{id,name,label,value}]
  filters: Object      // {perPage}
})

/* -------- utils -------- */
function toYmdLocal(d) {
  if (!d) return null
  const dt = new Date(d)
  const y = dt.getFullYear()
  const m = String(dt.getMonth() + 1).padStart(2, '0')
  const day = String(dt.getDate()).padStart(2, '0')
  return `${y}-${m}-${day}`
}

/* -------- service -------- */
const svc = createTeachingAssignmentService()

/* -------- table state -------- */
const state = reactive({
  perPage: props.filters?.perPage ?? (props.assignments?.per_page ?? 20),
})
function applyFilters() {
  router.visit(route('admin.classrooms.assignments.index', {
    classroom: props.classroom.id,
    per_page: state.perPage !== props.assignments?.per_page ? state.perPage : undefined
  }), { preserveScroll: true, preserveState: true })
}
function onPage(e) {
  const page = Math.floor(e.first / e.rows) + 1
  router.visit(route('admin.classrooms.assignments.index', {
    classroom: props.classroom.id,
    per_page: e.rows,
    page: page > 1 ? page : undefined
  }), { preserveScroll: true, preserveState: true })
}
const value = computed(() => props.assignments?.data ?? [])
const totalRecords = computed(() => props.assignments?.total ?? value.value.length)
const rows = computed(() => props.assignments?.per_page ?? 20)
const first = computed(() => Math.max(0, (props.assignments?.from ?? 1) - 1))

/* -------- dialog create / edit -------- */
const showDialog = ref(false)
const isEditing = ref(false)
const editingId = ref(null)
const form = reactive({
  teacher_id: null,
  rate_per_session: null,
  effective_from: new Date(),
  effective_to: null,
  errors: {},
  saving: false
})

function openCreate() {
  isEditing.value = false
  editingId.value = null
  form.teacher_id = null
  form.rate_per_session = null
  form.effective_from = new Date()
  form.effective_to = null
  form.errors = {}
  showDialog.value = true
}
function openEdit(row) {
  console.log('Raw date data:', {
  effective_from: row.effective_from,
    effective_to: row.effective_to
  });

  isEditing.value = true
  editingId.value = row.id
  form.teacher_id = String(row.teacher_id)
  form.rate_per_session = Number(row.rate_per_session ?? 0)

  // Handle dates with timezone adjustment
  if (row.effective_from) {
    const date = new Date(row.effective_from)
    form.effective_from = new Date(date.getTime() + date.getTimezoneOffset() * 60000)
  } else {
    form.effective_from = new Date()
  }

  if (row.effective_to) {
    const date = new Date(row.effective_to)
    form.effective_to = new Date(date.getTime() + date.getTimezoneOffset() * 60000)
  } else {
    form.effective_to = null
  }

  console.log('Parsed dates:', {
  effective_from: form.effective_from,
    effective_to: form.effective_to
  });

  form.errors = {}
  showDialog.value = true
}
function save() {
  form.errors = {}
  if (!form.teacher_id) form.errors.teacher_id = 'Vui lòng chọn giáo viên'
  if (!form.rate_per_session || Number(form.rate_per_session) <= 0) form.errors.rate_per_session = 'Vui lòng nhập đơn giá > 0'
  if (Object.keys(form.errors).length) return

  form.saving = true
  const payload = {
    teacher_id: Number(form.teacher_id),
    rate_per_session: Number(form.rate_per_session),
  effective_from: toYmdLocal(form.effective_from),
    effective_to:   form.effective_to   ? toYmdLocal(form.effective_to)   : null,
  }

  if (!isEditing.value) {
    svc.create(props.classroom.id, payload, {
      onFinish: () => { form.saving = false },
      onSuccess: () => { showDialog.value = false },
      onError: (errors) => {
        form.errors = errors || {}
      }
    })
  } else {
    svc.update(props.classroom.id, editingId.value, payload, {
      onFinish: () => { form.saving = false },
      onSuccess: () => { showDialog.value = false }
    })
  }
}
function destroy(id) {
  if (!confirm('Xác nhận xoá phân công này?')) return
  svc.delete(props.classroom.id, id)
}
</script>

<template>
  <Head :title="`Phân công GV - ${classroom.name}`" />

  <!-- Header -->
  <div class="mb-3 flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
    <div>
      <h1 class="text-xl md:text-2xl font-heading font-semibold">Phân công giáo viên</h1>
      <div class="text-sm text-slate-500 dark:text-slate-400">
        Lớp: <span class="font-medium text-slate-900 dark:text-slate-100">{{ classroom.code }} · {{ classroom.name }}</span>
      </div>
    </div>
    <div class="flex flex-wrap items-center gap-2">
      <Button label="Thêm phân công" icon="pi pi-plus" @click="openCreate" />
      <Link
        :href="route('admin.classrooms.index')"
        class="px-3 py-1.5 rounded-lg border border-slate-300 dark:border-slate-600 hover:bg-slate-100 dark:hover:bg-slate-800"
      >
        ← Danh sách lớp
      </Link>
      <Select
        v-model="state.perPage"
        :options="[{label:'20 / trang',value:20},{label:'50 / trang',value:50},{label:'100 / trang',value:100}]"
        optionLabel="label" optionValue="value" class="w-40" @change="applyFilters"
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
      lazy
      @page="onPage"
      dataKey="id"
      responsiveLayout="scroll"
      size="small"
    >
      <Column field="teacher.name" header="Giáo viên" />
      <Column field="rate_per_session" header="Đơn giá/buổi" style="width: 180px">
        <template #body="{ data }">
          {{ new Intl.NumberFormat('vi-VN', { style:'currency', currency:'VND' }).format(data.rate_per_session||0) }}
        </template>
      </Column>
      <Column field="effective_from" header="Hiệu lực từ" style="width: 140px">
        <template #body="{ data }">
          {{ data.effective_from ? new Date(data.effective_from).toLocaleDateString('vi-VN') : '' }}
        </template>
      </Column>
      <Column field="effective_to" header="Đến" style="width: 140px">
        <template #body="{ data }">
          {{ data.effective_to ? new Date(data.effective_to).toLocaleDateString('vi-VN') : '' }}
        </template>
      </Column>
      <Column header="" style="width: 220px">
        <template #body="{ data }">
          <div class="flex justify-end gap-2">
            <Button label="Sửa" icon="pi pi-pencil" text @click="openEdit(data)" />
            <button
              class="px-3 py-1.5 rounded border border-red-300 text-red-600 hover:bg-red-50
                     dark:border-red-700 dark:text-red-300 dark:hover:bg-red-900/20"
              @click="destroy(data.id)"
            >
              <i class="pi pi-trash mr-1"></i> Xoá
            </button>
          </div>
        </template>
      </Column>
      <template #empty>
        <div class="p-6 text-center text-slate-500 dark:text-slate-400">Chưa có phân công nào.</div>
      </template>
    </DataTable>
  </div>

  <!-- Dialog: Create / Edit -->
  <Dialog
    v-model:visible="showDialog"
    modal
    :header="isEditing ? 'Sửa phân công' : 'Thêm phân công'"
    :style="{ width: '560px' }"
    @hide="form.saving = false"
  >
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
      <div class="md:col-span-2">
        <label class="block text-sm font-medium mb-1">Giáo viên</label>
        <Select
          v-model="form.teacher_id"
          :options="(props.teachers||[]).map(t => ({ label:t.label ?? t.name, value:String(t.id) }))"
          optionLabel="label" optionValue="value" class="w-full"
        />
        <div v-if="form.errors?.teacher_id" class="text-red-500 text-xs mt-1">{{ form.errors.teacher_id }}</div>
      </div>

      <div class="md:col-span-2">
        <label class="block text-sm font-medium mb-1">Đơn giá/buổi</label>
        <InputNumber v-model="form.rate_per_session" mode="currency" currency="VND" locale="vi-VN" class="w-full" />
        <div v-if="form.errors?.rate_per_session" class="text-red-500 text-xs mt-1">{{ form.errors.rate_per_session }}</div>
      </div>

      <div>
        <label class="block text-sm font-medium mb-1">Hiệu lực từ</label>
        <DatePicker
          v-model="form.effective_from"
          :required="true"
          dateFormat="dd/mm/yy"
          :showTime="false"
          :manualInput="false"
          showIcon
          iconDisplay="input"
          class="w-full"
        />
        <div v-if="form.errors?.effective_from" class="text-red-500 text-xs mt-1">{{ form.errors.effective_from }}</div>
      </div>
      <div>
        <label class="block text-sm font-medium mb-1">Đến (tuỳ chọn)</label>
        <DatePicker
          v-model="form.effective_to"
          dateFormat="dd/mm/yy"
          :showTime="false"
          :manualInput="false"
          showIcon
          iconDisplay="input"
          class="w-full"
        />
      </div>
    </div>

    <template #footer>
      <Button label="Huỷ" icon="pi pi-times" text @click="showDialog=false" />
      <Button label="Lưu" icon="pi pi-check" :loading="form.saving" @click="save" autofocus />
    </template>
  </Dialog>
</template>
