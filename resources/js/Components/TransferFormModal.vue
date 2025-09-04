<template>
  <Dialog
    :visible="visible"
    @update:visible="emit('update:visible', $event)"
    modal
    :header="modalTitle"
    :style="{ width: '50rem' }"
    :breakpoints="{ '1199px': '75vw', '575px': '90vw' }"
    :closable="!loading"
    :dismissableMask="false"
  >
    <form @submit.prevent="handleSubmit" class="space-y-6">

      <!-- Student Info (Read-only if pre-selected) -->
      <div v-if="!studentId">
        <label class="block text-sm font-medium mb-2">Học viên *</label>
        <AutoComplete
          v-model="selectedStudentObj"
          dropdown
          :suggestions="studentSuggestions"
          @complete="searchStudents"
          optionLabel="label"
          placeholder="Tìm học viên..."
          class="w-full"
          :class="{ 'p-invalid': errors?.student_id }"
        />
        <small v-if="errors?.student_id" class="p-error">{{ errors.student_id }}</small>
      </div>

      <div v-else class="bg-blue-50 dark:bg-blue-900/20 p-4 rounded-lg">
        <div class="flex items-center gap-3">
          <i class="pi pi-user text-blue-600"></i>
          <div>
            <div class="font-medium">{{ student?.name || 'N/A' }}</div>
            <div class="text-sm text-blue-600">{{ student?.code || 'N/A' }}</div>
          </div>
        </div>
      </div>

      <!-- Current Enrollments (if student selected) -->
      <div v-if="selectedStudent?.enrollments?.length" class="bg-gray-50 dark:bg-gray-700 p-4 rounded-lg">
        <div class="text-sm font-medium mb-2">Lớp hiện tại:</div>
        <div class="space-y-1">
          <div
            v-for="enrollment in selectedStudent.enrollments.filter(e => e.status === 'active')"
            :key="enrollment.id"
            class="text-sm text-blue-800 dark:text-blue-200"
          >
            <span class="font-medium">{{ enrollment.classroom?.code }}</span> - {{ enrollment.classroom?.name }}
            <span class="text-blue-600 dark:text-blue-300 ml-2">(Buổi {{ enrollment.start_session_no }})</span>
          </div>
        </div>
      </div>

      <!-- From Class -->
      <div>
        <label class="block text-sm font-medium mb-2">Từ lớp *</label>
        <Select
          v-model="form.from_class_id"
          :options="fromClassOptions"
          optionLabel="label"
          optionValue="value"
          placeholder="Chọn lớp hiện tại"
          class="w-full"
          :class="{ 'p-invalid': errors?.from_class_id }"
          showClear
        />
        <small v-if="errors?.from_class_id" class="p-error">{{ errors.from_class_id }}</small>
      </div>

      <!-- To Class -->
      <div>
        <label class="block text-sm font-medium mb-2">Đến lớp *</label>
        <Select
          v-model="form.to_class_id"
          :options="toClassOptions"
          optionLabel="label"
          optionValue="value"
          placeholder="Chọn lớp đích"
          class="w-full"
          :class="{ 'p-invalid': errors?.to_class_id }"
          showClear
        />
        <small v-if="errors?.to_class_id" class="p-error">{{ errors.to_class_id }}</small>
      </div>

      <!-- Effective Date -->
      <div>
        <label class="block text-sm font-medium mb-2">Ngày hiệu lực *</label>
        <Calendar
          v-model="form.effective_date"
          showIcon
          placeholder="Chọn ngày"
          class="w-full"
          :class="{ 'p-invalid': errors?.effective_date }"
          dateFormat="dd/mm/yy"
        />
        <small v-if="errors?.effective_date" class="p-error">{{ errors.effective_date }}</small>
      </div>

      <!-- Start Session -->
      <div>
        <label class="block text-sm font-medium mb-2">Buổi bắt đầu *</label>
        <InputNumber
          v-model="form.start_session_no"
          :min="1"
          :max="50"
          placeholder="1"
          class="w-full"
          :class="{ 'p-invalid': errors?.start_session_no }"
        />
        <small class="text-gray-500">Buổi học đầu tiên tại lớp mới</small>
        <small v-if="errors?.start_session_no" class="p-error">{{ errors.start_session_no }}</small>
      </div>

      <!-- Reason -->
      <div>
        <label class="block text-sm font-medium mb-2">Lý do chuyển lớp</label>
        <Textarea
          v-model="form.reason"
          placeholder="Lý do chuyển lớp..."
          rows="3"
          class="w-full"
          :class="{ 'p-invalid': errors?.reason }"
        />
        <small v-if="errors?.reason" class="p-error">{{ errors.reason }}</small>
      </div>

      <!-- Options -->
      <div class="space-y-3">
        <div class="flex items-center gap-2">
          <Checkbox
            v-model="form.create_adjustments"
            inputId="create_adjustments"
            :binary="true"
          />
          <label for="create_adjustments" class="text-sm">Tạo phiếu điều chỉnh kế toán</label>
        </div>

        <div class="space-y-2">
          <label class="block text-sm font-medium">Phí chuyển lớp</label>
          <InputNumber
            v-model="form.transfer_fee"
            mode="currency"
            currency="VND"
            locale="vi-VN"
            placeholder="0 đ"
            class="w-full"
            :class="{ 'p-invalid': errors?.transfer_fee }"
          />
          <small class="text-gray-500">Để 0 nếu không tính phí</small>
          <small v-if="errors?.transfer_fee" class="p-error">{{ errors.transfer_fee }}</small>
        </div>
      </div>

      <!-- Notes -->
      <div>
        <label class="block text-sm font-medium mb-2">Ghi chú</label>
        <Textarea
          v-model="form.notes"
          placeholder="Ghi chú thêm..."
          rows="2"
          class="w-full"
          :class="{ 'p-invalid': errors?.notes }"
        />
        <small v-if="errors?.notes" class="p-error">{{ errors.notes }}</small>
      </div>
    </form>

    <template #footer>
      <div class="flex justify-end gap-2">
        <Button
          label="Hủy"
          text
          severity="secondary"
          @click="handleCancel"
          :disabled="loading"
        />
        <Button
          label="Chuyển lớp"
          @click="handleSubmit"
          :loading="loading"
          :disabled="!canSubmit"
        />
      </div>
    </template>
  </Dialog>
</template>

<script setup>
import { ref, reactive, computed, watch, onMounted } from 'vue'
import Dialog from 'primevue/dialog'
import Button from 'primevue/button'
import AutoComplete from 'primevue/autocomplete'
import Select from 'primevue/select'
import Calendar from 'primevue/calendar'
import InputNumber from 'primevue/inputnumber'
import Textarea from 'primevue/textarea'
import Checkbox from 'primevue/checkbox'
import { createTransferService } from '@/service/TransferService.js'

// Props
const props = defineProps({
  visible: {
    type: Boolean,
    default: false
  },
  student: {
    type: Object,
    default: null
  },
  studentId: {
    type: Number,
    default: null
  },
  fromClassId: {
    type: Number,
    default: null
  },
  classrooms: {
    type: Array,
    default: () => []
  }
})

// Emits
const emit = defineEmits(['update:visible', 'success', 'error'])

// Services
const transferService = createTransferService()

// Reactive data
const loading = ref(false)
const errors = ref({})
const studentSuggestions = ref([])
const selectedStudentObj = ref(null)
const selectedStudent = ref(null)

const form = reactive({
  student_id: null,
  from_class_id: null,
  to_class_id: null,
  effective_date: new Date(),
  start_session_no: 1,
  reason: '',
  notes: '',
  create_adjustments: true,
  transfer_fee: 0
})

// Computed
const modalTitle = computed(() => {
  return props.student?.name
    ? `Chuyển lớp: ${props.student.name}`
    : 'Chuyển lớp học viên'
})

const fromClassOptions = computed(() => {
  if (!selectedStudent.value?.enrollments) return []

  return selectedStudent.value.enrollments
    .filter(e => e.status === 'active')
    .map(e => ({
      label: `${e.classroom?.code} - ${e.classroom?.name}`,
      value: e.classroom?.id,
      enrollment: e
    }))
})

const toClassOptions = computed(() => {
  if (!props.classrooms) return []

  return props.classrooms
    .filter(c => c.id !== form.from_class_id)
    .map(c => ({
      label: `${c.code} - ${c.name}`,
      value: c.id,
      classroom: c
    }))
})

const canSubmit = computed(() => {
  return form.student_id &&
         form.from_class_id &&
         form.to_class_id &&
         form.effective_date &&
         form.start_session_no &&
         !loading.value
})

// Watchers
watch(() => props.visible, (newVal) => {
  if (newVal) {
    resetForm()
    initializeForm()
  }
})

watch(selectedStudentObj, async (newStudent) => {
  if (newStudent && typeof newStudent === 'object') {
    selectedStudent.value = newStudent
    form.student_id = newStudent.value || newStudent.id
    form.from_class_id = null
    form.to_class_id = null

    // Fetch full student details with enrollments if needed
    if (!newStudent.enrollments && (newStudent.value || newStudent.id)) {
      try {
        const fullStudentData = await transferService.getStudentWithEnrollments(newStudent.value || newStudent.id)
        if (fullStudentData && fullStudentData.enrollments) {
          selectedStudent.value = {
            ...newStudent,
            enrollments: fullStudentData.enrollments
          }
        }
      } catch (error) {
        console.error('Failed to load student enrollments:', error)
      }
    }
  }
})

// Methods
function resetForm() {
  loading.value = false
  errors.value = {}
  selectedStudentObj.value = null
  selectedStudent.value = null

  Object.assign(form, {
    student_id: null,
    from_class_id: null,
    to_class_id: null,
    effective_date: new Date(),
    start_session_no: 1,
    reason: '',
    notes: '',
    create_adjustments: true,
    transfer_fee: 0
  })
}

function initializeForm() {
  // Pre-fill if student is provided
  if (props.student) {
    selectedStudent.value = props.student
    form.student_id = props.student.id
  } else if (props.studentId) {
    form.student_id = props.studentId
    // Load student details
    loadStudentDetails(props.studentId)
  }

  // Pre-fill from class if provided
  if (props.fromClassId) {
    form.from_class_id = props.fromClassId
  }
}

async function loadStudentDetails(studentId) {
  try {
    const studentData = await transferService.getStudentWithEnrollments(studentId)
    if (studentData) {
      selectedStudent.value = studentData
      selectedStudentObj.value = {
        value: studentData.id,
        label: `${studentData.code} - ${studentData.name}`,
        ...studentData
      }
    }
  } catch (error) {
    console.error('Failed to load student details:', error)
  }
}

async function searchStudents(event) {
  const results = await transferService.searchStudents(event.query)
  studentSuggestions.value = results
}

function toYmdLocal(d) {
  if (!d) return null
  const dt = new Date(d)
  const y = dt.getFullYear()
  const m = String(dt.getMonth() + 1).padStart(2, '0')
  const day = String(dt.getDate()).padStart(2, '0')
  return `${y}-${m}-${day}`
}

async function handleSubmit() {
  if (!canSubmit.value) return

  loading.value = true
  errors.value = {}

  try {
    await transferService.create({
      student_id: form.student_id,
      from_class_id: form.from_class_id,
      to_class_id: form.to_class_id,
      effective_date: toYmdLocal(form.effective_date),
      start_session_no: form.start_session_no,
      reason: form.reason,
      notes: form.notes,
      create_adjustments: form.create_adjustments,
      transfer_fee: form.transfer_fee,
    }, {
      onError: (validationErrors) => {
        errors.value = validationErrors
      },
      onSuccess: () => {
        emit('success')
        emit('update:visible', false)
      }
    })
  } catch (error) {
    emit('error', error)
  } finally {
    loading.value = false
  }
}

function handleCancel() {
  if (!loading.value) {
    emit('update:visible', false)
  }
}
</script>
