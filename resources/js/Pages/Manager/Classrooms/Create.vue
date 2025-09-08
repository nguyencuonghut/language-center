<script setup>
import { Head, useForm, Link } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import { createManagerClassroomService } from '@/service/ManagerClassroomService'
import { usePageToast } from '@/composables/usePageToast'

const { showSuccess, showError } = usePageToast()
const classroomService = createManagerClassroomService({ showSuccess, showError })

import Select from 'primevue/select'
import InputText from 'primevue/inputtext'
import InputNumber from 'primevue/inputnumber'
import DatePicker from 'primevue/datepicker'
import RadioButton from 'primevue/radiobutton'
import Button from 'primevue/button'
import FormLabel from '@/Components/FormLabel.vue'

defineOptions({ layout: AppLayout })

const props = defineProps({
  branches: Array,
  courses: Array,
  suggestBranchId: Number,
  errors: Object,
})

const form = useForm({
  code: '',
  name: '',
  term_code: '',
  course_id: null,
  branch_id: props.suggestBranchId ?? null,
  start_date: null,
  sessions_total: 24,
  tuition_fee: 0,
  status: 'open',
})

function toYMD(val) {
  if (!val) return null
  if (typeof val === 'string' && /^\d{4}-\d{2}-\d{2}$/.test(val)) return val
  const d = (val instanceof Date) ? val : new Date(val)
  if (isNaN(d)) return null
  const y = d.getFullYear()
  const m = String(d.getMonth() + 1).padStart(2, '0')
  const day = String(d.getDate()).padStart(2, '0')
  return `${y}-${m}-${day}`
}

function submit() {
  const payload = { ...form.data(), start_date: toYMD(form.start_date) }
  classroomService.create(payload, {
    onError: (errors) => {
      form.setError(errors)
    }
  })
}
</script>

<template>
  <Head title="Tạo lớp" />

  <div class="max-w-4xl mx-auto">
    <h1 class="text-xl md:text-2xl font-heading font-semibold mb-4">Tạo lớp</h1>

    <div class="rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 p-4 space-y-4">
      <div class="grid md:grid-cols-2 gap-4">
        <div>
          <FormLabel value="Mã lớp" required />
          <InputText v-model="form.code" class="w-full" placeholder="VD: ENG-A01" />
          <small v-if="form.errors.code" class="text-red-500">{{ form.errors.code }}</small>
        </div>

        <div>
          <FormLabel value="Tên lớp" required />
          <InputText v-model="form.name" class="w-full" placeholder="VD: Tiếng Anh thiếu nhi A01" />
          <small v-if="form.errors.name" class="text-red-500">{{ form.errors.name }}</small>
        </div>

        <div>
          <FormLabel value="Học kỳ" />
          <InputText v-model="form.term_code" class="w-full" placeholder="VD: K1/K2..." />
          <small v-if="form.errors.term_code" class="text-red-500">{{ form.errors.term_code }}</small>
        </div>

        <div>
          <FormLabel value="Khóa học" required />
          <Select
            v-model="form.course_id"
            :options="(props.courses||[]).map(x=>({label:x.name,value:x.id}))"
            optionLabel="label" optionValue="value"
            placeholder="Chọn khóa học"
            :pt="{ root: { class: 'w-full' } }"
          />
          <small v-if="form.errors.course_id" class="text-red-500">{{ form.errors.course_id }}</small>
        </div>

        <div>
          <FormLabel value="Chi nhánh" required />
          <Select
            v-model="form.branch_id"
            :options="(props.branches||[]).map(x=>({label:x.name,value:x.id}))"
            optionLabel="label" optionValue="value"
            placeholder="Chọn chi nhánh"
            :pt="{ root: { class: 'w-full' } }"
          />
          <small v-if="form.errors.branch_id" class="text-red-500">{{ form.errors.branch_id }}</small>
        </div>

        <div>
          <FormLabel value="Ngày bắt đầu" required />
          <DatePicker
            v-model="form.start_date"
            dateFormat="yy-mm-dd"
            showIcon
            fluid
          />
          <small v-if="form.errors.start_date" class="text-red-500">{{ form.errors.start_date }}</small>
        </div>

        <div>
          <FormLabel value="Số buổi" required />
          <InputNumber v-model="form.sessions_total" class="w-full" :min="1" :max="500" />
          <small v-if="form.errors.sessions_total" class="text-red-500">{{ form.errors.sessions_total }}</small>
        </div>

        <div>
          <FormLabel value="Học phí (VND)" required />
          <InputNumber v-model="form.tuition_fee" class="w-full" :min="0" :useGrouping="true" />
          <small v-if="form.errors.tuition_fee" class="text-red-500">{{ form.errors.tuition_fee }}</small>
        </div>

        <div>
          <FormLabel value="Trạng thái" required />
          <div class="flex items-center gap-4 mt-2">
            <div class="inline-flex items-center gap-2">
              <RadioButton v-model="form.status" inputId="st1" value="open" />
              <label for="st1">Mở</label>
            </div>
            <div class="inline-flex items-center gap-2">
              <RadioButton v-model="form.status" inputId="st2" value="closed" />
              <label for="st2">Đóng</label>
            </div>
          </div>
          <small v-if="form.errors.status" class="text-red-500">{{ form.errors.status }}</small>
        </div>
      </div>

      <div class="flex items-center gap-2">
        <Button label="Lưu" icon="pi pi-check" severity="success" :loading="form.processing" @click="submit" />
        <Link :href="route('manager.classrooms.index')" class="px-3 py-2 rounded-lg border hover:bg-slate-50 dark:hover:bg-slate-700/30">
          Huỷ
        </Link>
      </div>
    </div>
  </div>
</template>
