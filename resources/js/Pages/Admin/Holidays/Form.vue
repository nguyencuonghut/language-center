<template>
  <div class="p-3 md:p-5 max-w-2xl mx-auto">
    <h1 class="text-2xl font-bold mb-4">{{ isEdit ? 'Cập nhật' : 'Thêm' }} ngày nghỉ lễ</h1>
    <form @submit.prevent="submit">
      <div class="mb-4">
        <label class="block mb-1 font-medium">Tên ngày nghỉ <span class="text-red-500">*</span></label>
        <InputText v-model="form.name" class="w-full" :class="{'p-invalid': errors.name}" />
        <div v-if="errors.name" class="text-red-500 text-sm mt-1">{{ errors.name }}</div>
      </div>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div class="field">
            <label for="start_date" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
              Ngày bắt đầu *
            </label>
            <DatePicker
              id="start_date"
              v-model="form.start_date"
              dateFormat="yy-mm-dd"
              :showIcon="true"
              :showButtonBar="true"
              class="w-full"
              :class="{ 'p-invalid': errors.start_date }"
            />
            <small v-if="errors.start_date" class="p-error text-red-500">{{ Array.isArray(errors.start_date) ? errors.start_date[0] : errors.start_date }}</small>
          </div>

          <div class="field">
            <label for="end_date" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
              Ngày kết thúc *
            </label>
            <DatePicker
              id="end_date"
              v-model="form.end_date"
              dateFormat="yy-mm-dd"
              :showIcon="true"
              :showButtonBar="true"
              class="w-full"
              :class="{ 'p-invalid': errors.end_date }"
            />
            <small v-if="errors.end_date" class="p-error text-red-500">{{ Array.isArray(errors.end_date) ? errors.end_date[0] : errors.end_date }}</small>
          </div>
        </div>
      <div class="mb-4">
        <label class="block mb-1 font-medium">Phạm vi áp dụng <span class="text-red-500">*</span></label>
        <Select v-model="form.scope" :options="scopeOptions" optionLabel="label" optionValue="value" class="w-full" />
        <div v-if="errors.scope" class="text-red-500 text-sm mt-1">{{ errors.scope }}</div>
      </div>
      <div class="mb-4" v-if="form.scope === 'branch'">
        <label class="block mb-1 font-medium">Chi nhánh</label>
        <Select v-model="form.branch_id" :options="branches" optionLabel="name" optionValue="id" class="w-full" />
        <div v-if="errors.branch_id" class="text-red-500 text-sm mt-1">{{ errors.branch_id }}</div>
      </div>
      <div class="mb-4" v-if="form.scope === 'class'">
        <label class="block mb-1 font-medium">Lớp học</label>
        <Select v-model="form.class_id" :options="classrooms" optionLabel="name" optionValue="id" class="w-full" />
        <div v-if="errors.class_id" class="text-red-500 text-sm mt-1">{{ errors.class_id }}</div>
      </div>
      <div class="mb-4 flex items-center gap-3">
        <Checkbox v-model="form.recurring_yearly" :binary="true" inputId="recurring" />
        <label for="recurring">Lặp lại hàng năm</label>
      </div>
      <div class="flex gap-2 mt-6">
        <Button type="submit" :label="isEdit ? 'Cập nhật' : 'Thêm mới'" icon="pi pi-check" class="p-button-success" :loading="loading" />
        <Link :href="route('admin.holidays.index')">
          <Button label="Huỷ" class="p-button-text" />
        </Link>
      </div>
    </form>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue'
import { usePage, Link, router } from '@inertiajs/vue3'
import InputText from 'primevue/inputtext'
import DatePicker from 'primevue/datepicker'
import Button from 'primevue/button'
import Select from 'primevue/select'
import Checkbox from 'primevue/checkbox'
import { usePageToast } from '@/composables/usePageToast'
import { createHolidayService } from '@/service/HolidayService'
import AppLayout from '@/Layouts/AppLayout.vue'

defineOptions({ layout: AppLayout })

const page = usePage()
const isEdit = computed(() => !!page.props.holiday)
const branches = page.props.branches || []
const classrooms = page.props.classrooms || []
const errors = ref(page.props.errors || {})
const { showSuccess, showError } = usePageToast()

const scopeOptions = [
  { label: 'Toàn hệ thống', value: 'global' },
  { label: 'Chi nhánh', value: 'branch' },
  { label: 'Lớp học', value: 'class' },
]

// helper: yyyy-mm-dd (local, không lệch timezone)
function toYmdLocal(d) {
  if (!d) return null
  const dt = new Date(d)
  const y = dt.getFullYear()
  const m = String(dt.getMonth() + 1).padStart(2, '0')
  const day = String(dt.getDate()).padStart(2, '0')
  return `${y}-${m}-${day}`
}

const form = ref({
  name: page.props.holiday?.name || '',
  start_date: page.props.holiday?.start_date ? new Date(page.props.holiday.start_date) : null,
  end_date: page.props.holiday?.end_date ? new Date(page.props.holiday.end_date) : null,
  scope: page.props.holiday?.scope || 'global',
  branch_id: page.props.holiday?.branch_id || null,
  class_id: page.props.holiday?.class_id || null,
  recurring_yearly: page.props.holiday?.recurring_yearly || false,
})

const loading = ref(false)

const holidayService = createHolidayService({ showSuccess, showError })

function submit() {
  loading.value = true
  errors.value = {}

  // Convert Date objects to strings for backend
  const formData = {
    ...form.value,
    start_date: toYmdLocal(form.value.start_date),
    end_date: toYmdLocal(form.value.end_date),
  }

  if (isEdit.value) {
    holidayService.update(page.props.holiday.id, formData, {
      onSuccess: () => {
        router.visit(route('admin.holidays.index'))
      },
      onError: (errs) => {
        errors.value = errs
      }
    })
  } else {
    holidayService.create(formData, {
      onSuccess: () => {
        router.visit(route('admin.holidays.index'))
      },
      onError: (errs) => {
        errors.value = errs
      }
    })
  }
  loading.value = false
}
</script>

<style scoped>
.p-invalid {
  border-color: #f87171;
}
</style>
