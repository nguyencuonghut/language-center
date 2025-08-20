<script setup>
import { reactive, toRefs } from 'vue'
import { Head, Link } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import { createClassroomService } from '@/service/ClassroomService'
import { usePageToast } from '@/composables/usePageToast'

// PrimeVue v4
import InputText from 'primevue/inputtext'
import Textarea from 'primevue/textarea'
import Select from 'primevue/select'
import DatePicker from 'primevue/datepicker'
import Button from 'primevue/button'
import Tag from 'primevue/tag'

defineOptions({ layout: AppLayout })

const props = defineProps({
  classroom: Object,     // { id, code, name, branch_id, course_id, teacher_id, start_date, sessions_total, tuition_fee, status, note }
  branches: Array,       // [{id,name}]
  courses: Array,        // [{id,name}]
  teachers: Array,       // [{id,name}]
  errors: Object,        // Inertia validation errors
})

const { showSuccess, showError } = usePageToast()
const classroomService = createClassroomService({ showSuccess, showError })

/* ----- Local state ----- */
const state = reactive({
  code: props.classroom?.code ?? '',
  name: props.classroom?.name ?? '',
  branch_id: props.classroom?.branch_id ? String(props.classroom.branch_id) : null,
  course_id: props.classroom?.course_id ? String(props.classroom.course_id) : null,
  teacher_id: props.classroom?.teacher_id ? String(props.classroom.teacher_id) : null,
  start_date: props.classroom?.start_date ? new Date(props.classroom.start_date + 'T00:00:00') : null,
  sessions_total: props.classroom?.sessions_total ?? 24,
  tuition_fee: props.classroom?.tuition_fee ?? 0,
  status: props.classroom?.status ?? 'open',
  note: props.classroom?.note ?? '',
  saving: false,
})

/* ----- Options ----- */
const branchOptions = (props.branches || []).map(b => ({ label: b.name, value: String(b.id) }))
const courseOptions = (props.courses || []).map(c => ({ label: c.name, value: String(c.id) }))
const teacherOptions = (props.teachers || []).map(t => ({ label: t.name, value: String(t.id) }))
const statusOptions = [
  { label: 'Đang mở', value: 'open' },
  { label: 'Đóng', value: 'closed' },
]

/* ----- Submit ----- */
function onSubmit() {
  state.saving = true
  classroomService.update(props.classroom.id, {
    code: state.code,
    name: state.name,
    branch_id: state.branch_id ? Number(state.branch_id) : null,
    course_id: state.course_id ? Number(state.course_id) : null,
    teacher_id: state.teacher_id ? Number(state.teacher_id) : null,
    start_date: state.start_date ? new Date(state.start_date).toISOString().slice(0,10) : null,
    sessions_total: Number(state.sessions_total) || 0,
    tuition_fee: Number(state.tuition_fee) || 0,
    status: state.status,
    note: state.note || null,
  }, {
    onSuccess: () => { state.saving = false },
    onError: () => { state.saving = false },
  })
}
</script>

<template>
  <Head :title="`Sửa lớp - ${classroom?.name || ''}`" />

  <!-- Header / Breadcrumb & Actions -->
  <div class="mb-3 flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
    <div>
      <h1 class="text-xl md:text-2xl font-heading font-semibold">Sửa lớp</h1>
      <div class="text-slate-500 dark:text-slate-400 text-sm">
        Mã: <Tag :value="classroom.code" />
      </div>
    </div>

    <div class="flex flex-wrap gap-2">
      <!-- Nút đi đến Buổi học -->
      <Link
        :href="route('admin.classrooms.sessions.index', { classroom: classroom.id })"
        class="px-3 py-1.5 rounded border border-sky-300 text-sky-700 hover:bg-sky-50
               dark:border-sky-700 dark:text-sky-300 dark:hover:bg-sky-900/20"
        title="Danh sách buổi học"
      >
        <i class="pi pi-list mr-1"></i> Buổi học
      </Link>

      <!-- Nút đi đến Lịch tuần -->
      <Link
        :href="route('admin.classrooms.sessions.week', { classroom: classroom.id })"
        class="px-3 py-1.5 rounded border border-indigo-300 text-indigo-700 hover:bg-indigo-50
               dark:border-indigo-700 dark:text-indigo-300 dark:hover:bg-indigo-900/20"
        title="Xem lịch tuần"
      >
        <i class="pi pi-calendar mr-1"></i> Lịch tuần
      </Link>

      <!-- Quay lại danh sách lớp -->
      <Link
        :href="route('admin.classrooms.index')"
        class="px-3 py-1.5 rounded border border-slate-300 dark:border-slate-600 hover:bg-slate-100 dark:hover:bg-slate-800"
      >
        ← Danh sách lớp
      </Link>
    </div>
  </div>

  <!-- Form -->
  <form @submit.prevent="onSubmit"
        class="space-y-4 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 p-4">

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
      <div>
        <label class="block text-sm font-medium mb-1">Mã lớp</label>
        <InputText v-model="state.code" class="w-full" placeholder="VD: ENG-01" />
        <div v-if="errors?.code" class="text-red-500 text-xs mt-1">{{ errors.code }}</div>
      </div>

      <div>
        <label class="block text-sm font-medium mb-1">Tên lớp</label>
        <InputText v-model="state.name" class="w-full" placeholder="VD: Tiếng Anh Thiếu nhi 1" />
        <div v-if="errors?.name" class="text-red-500 text-xs mt-1">{{ errors.name }}</div>
      </div>

      <div>
        <label class="block text-sm font-medium mb-1">Chi nhánh</label>
        <Select v-model="state.branch_id" :options="branchOptions" optionLabel="label" optionValue="value" class="w-full" />
        <div v-if="errors?.branch_id" class="text-red-500 text-xs mt-1">{{ errors.branch_id }}</div>
      </div>

      <div>
        <label class="block text-sm font-medium mb-1">Khóa học</label>
        <Select v-model="state.course_id" :options="courseOptions" optionLabel="label" optionValue="value" class="w-full" />
        <div v-if="errors?.course_id" class="text-red-500 text-xs mt-1">{{ errors.course_id }}</div>
      </div>

      <div>
        <label class="block text-sm font-medium mb-1">Giáo viên</label>
        <Select v-model="state.teacher_id" :options="teacherOptions" optionLabel="label" optionValue="value" class="w-full" showClear />
        <div v-if="errors?.teacher_id" class="text-red-500 text-xs mt-1">{{ errors.teacher_id }}</div>
      </div>

      <div>
        <label class="block text-sm font-medium mb-1">Ngày bắt đầu</label>
        <DatePicker v-model="state.start_date" dateFormat="yy-mm-dd" showIcon iconDisplay="input" class="w-full" />
        <div v-if="errors?.start_date" class="text-red-500 text-xs mt-1">{{ errors.start_date }}</div>
      </div>

      <div>
        <label class="block text-sm font-medium mb-1">Tổng số buổi</label>
        <InputText v-model.number="state.sessions_total" type="number" min="1" class="w-full" />
        <div v-if="errors?.sessions_total" class="text-red-500 text-xs mt-1">{{ errors.sessions_total }}</div>
      </div>

      <div>
        <label class="block text-sm font-medium mb-1">Học phí (VND)</label>
        <InputText v-model.number="state.tuition_fee" type="number" min="0" class="w-full" />
        <div v-if="errors?.tuition_fee" class="text-red-500 text-xs mt-1">{{ errors.tuition_fee }}</div>
      </div>

      <div>
        <label class="block text-sm font-medium mb-1">Trạng thái</label>
        <Select v-model="state.status" :options="statusOptions" optionLabel="label" optionValue="value" class="w-full" />
        <div v-if="errors?.status" class="text-red-500 text-xs mt-1">{{ errors.status }}</div>
      </div>
    </div>

    <div>
      <label class="block text-sm font-medium mb-1">Ghi chú</label>
      <Textarea v-model="state.note" autoResize rows="3" class="w-full" placeholder="Ghi chú thêm (không bắt buộc)" />
      <div v-if="errors?.note" class="text-red-500 text-xs mt-1">{{ errors.note }}</div>
    </div>

    <div class="flex items-center gap-2 justify-end pt-2">
      <Link
        :href="route('admin.classrooms.index')"
        class="px-3 py-2 rounded border border-slate-300 dark:border-slate-600 hover:bg-slate-100 dark:hover:bg-slate-800"
      >
        Huỷ
      </Link>
      <Button type="submit" :loading="state.saving" label="Cập nhật" icon="pi pi-check" />
    </div>
  </form>
</template>
