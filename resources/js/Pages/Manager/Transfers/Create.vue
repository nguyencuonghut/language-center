<script setup>
import { reactive, ref, computed, onMounted } from 'vue'
import { Head } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import { createTransferService } from '@/service/TransferService.js'

// PrimeVue
import Card from 'primevue/card'
import Button from 'primevue/button'
import InputText from 'primevue/inputtext'
import Select from 'primevue/select'
import DatePicker from 'primevue/datepicker'
import InputNumber from 'primevue/inputnumber'
import Textarea from 'primevue/textarea'
import Checkbox from 'primevue/checkbox'
import AutoComplete from 'primevue/autocomplete'

defineOptions({ layout: AppLayout })

const props = defineProps({
  student: Object,
  classrooms: Array,
})

// Initialize TransferService (no toast injection - handled by AppLayout)
const transferService = createTransferService()

// Form state
const form = reactive({
  student_id: props.student?.id ?? null,
  from_class_id: null,
  to_class_id: null,
  effective_date: new Date().toISOString().split('T')[0],
  start_session_no: 1,
  reason: '',
  notes: '',
  create_adjustments: true,
  transfer_fee: 0,
  saving: false,
  errors: {},
})

// Student search
const studentQuery = ref('')
const studentSuggestions = ref([])
const selectedStudent = ref(props.student)

// Available classes for from/to
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
  return props.classrooms
    .filter(c => c.id !== form.from_class_id)
    .map(c => ({
      label: `${c.code} - ${c.name}`,
      value: c.id,
      classroom: c
    }))
})

// Methods using TransferService
async function searchStudents(event) {
  studentSuggestions.value = await transferService.searchStudents(event.query)
}

function onStudentSelect(student) {
  selectedStudent.value = student
  form.student_id = student.id
  form.from_class_id = null
  form.to_class_id = null
}

function submit() {
  form.saving = true
  form.errors = {}

  transferService.create({
    student_id: form.student_id,
    from_class_id: form.from_class_id,
    to_class_id: form.to_class_id,
    effective_date: form.effective_date,
    start_session_no: form.start_session_no,
    reason: form.reason,
    notes: form.notes,
    create_adjustments: form.create_adjustments,
    transfer_fee: form.transfer_fee,
  }, {
    onError: (errors) => {
      form.errors = errors
    },
    onSuccess: () => {
      // TransferService handles success navigation
    }
  })

  // Always reset saving state
  setTimeout(() => {
    form.saving = false
  }, 100)
}

// Initialize student query if student is pre-selected
onMounted(() => {
  if (props.student) {
    studentQuery.value = `${props.student.code} - ${props.student.name}`
  }
})
</script>

<template>
  <Head title="Tạo chuyển lớp" />

  <div class="max-w-2xl mx-auto space-y-6">
    <!-- Page Header -->
    <div>
      <h1 class="text-2xl font-bold text-slate-900 dark:text-slate-100">Tạo chuyển lớp</h1>
      <p class="text-slate-600 dark:text-slate-400">Tạo phiếu chuyển lớp cho học viên</p>
    </div>

    <!-- Form -->
    <Card class="bg-white dark:bg-slate-800">
      <template #content>
        <form @submit.prevent="submit" class="space-y-6">
          <!-- Student Selection -->
          <div>
            <label class="block text-sm font-medium mb-2">Học viên *</label>
            <AutoComplete
              v-model="studentQuery"
              :suggestions="studentSuggestions"
              option-label="label"
              @complete="searchStudents"
              @option-select="onStudentSelect"
              placeholder="Tìm học viên..."
              class="w-full"
              :disabled="!!props.student"
            />
            <div v-if="form.errors?.student_id" class="text-red-500 text-sm mt-1">
              {{ form.errors.student_id }}
            </div>
          </div>

          <!-- Current Enrollments Info -->
          <div v-if="selectedStudent?.enrollments?.length" class="bg-blue-50 dark:bg-blue-900/20 p-4 rounded-lg">
            <h3 class="font-medium text-blue-900 dark:text-blue-100 mb-2">Ghi danh hiện tại:</h3>
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
              option-label="label"
              option-value="value"
              placeholder="Chọn lớp hiện tại"
              class="w-full"
            />
            <div v-if="form.errors?.from_class_id" class="text-red-500 text-sm mt-1">
              {{ form.errors.from_class_id }}
            </div>
          </div>

          <!-- To Class -->
          <div>
            <label class="block text-sm font-medium mb-2">Đến lớp *</label>
            <Select
              v-model="form.to_class_id"
              :options="toClassOptions"
              option-label="label"
              option-value="value"
              placeholder="Chọn lớp đích"
              class="w-full"
            />
            <div v-if="form.errors?.to_class_id" class="text-red-500 text-sm mt-1">
              {{ form.errors.to_class_id }}
            </div>
          </div>

          <!-- Effective Date -->
          <div>
            <label class="block text-sm font-medium mb-2">Ngày hiệu lực *</label>
            <DatePicker
              v-model="form.effective_date"
              date-format="yy-mm-dd"
              show-icon
              icon-display="input"
              class="w-full"
            />
            <div v-if="form.errors?.effective_date" class="text-red-500 text-sm mt-1">
              {{ form.errors.effective_date }}
            </div>
          </div>

          <!-- Start Session -->
          <div>
            <label class="block text-sm font-medium mb-2">Buổi bắt đầu *</label>
            <InputNumber
              v-model="form.start_session_no"
              :min="1"
              :max="100"
              class="w-full"
            />
            <small class="text-slate-500">Buổi học đầu tiên tại lớp mới</small>
            <div v-if="form.errors?.start_session_no" class="text-red-500 text-sm mt-1">
              {{ form.errors.start_session_no }}
            </div>
          </div>

          <!-- Reason -->
          <div>
            <label class="block text-sm font-medium mb-2">Lý do chuyển lớp</label>
            <Textarea
              v-model="form.reason"
              placeholder="Lý do chuyển lớp..."
              rows="3"
              class="w-full"
            />
            <div v-if="form.errors?.reason" class="text-red-500 text-sm mt-1">
              {{ form.errors.reason }}
            </div>
          </div>

          <!-- Additional Options -->
          <div class="space-y-4 border-t pt-4">
            <h3 class="font-medium">Tùy chọn bổ sung</h3>

            <!-- Create Adjustments -->
            <div class="flex items-center space-x-2">
              <Checkbox v-model="form.create_adjustments" binary />
              <label class="text-sm">Tạo phiếu điều chỉnh kế toán</label>
            </div>

            <!-- Transfer Fee -->
            <div>
              <label class="block text-sm font-medium mb-2">Phí chuyển lớp</label>
              <InputNumber
                v-model="form.transfer_fee"
                :min="0"
                :step="1000"
                mode="currency"
                currency="VND"
                locale="vi-VN"
                class="w-full"
              />
              <small class="text-slate-500">Để 0 nếu không thu phí</small>
            </div>

            <!-- Notes -->
            <div>
              <label class="block text-sm font-medium mb-2">Ghi chú</label>
              <Textarea
                v-model="form.notes"
                placeholder="Ghi chú thêm..."
                rows="2"
                class="w-full"
              />
            </div>
          </div>

          <!-- Submit Buttons -->
          <div class="flex justify-end space-x-3 pt-4 border-t">
            <Button
              label="Hủy"
              severity="secondary"
              @click="router.visit(route('manager.transfers.index'))"
            />
            <Button
              type="submit"
              label="Tạo chuyển lớp"
              icon="pi pi-check"
              :loading="form.saving"
            />
          </div>
        </form>
      </template>
    </Card>
  </div>
</template>
