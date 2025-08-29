<!-- resources/js/Pages/Manager/Students/Create.vue -->
<script setup>
import { reactive, ref } from 'vue'
import { Head, Link } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import { createStudentService } from '@/service/StudentService'

// PrimeVue
import InputText from 'primevue/inputtext'
import DatePicker from 'primevue/datepicker'
import Select from 'primevue/select'
import Textarea from 'primevue/textarea'
import ToggleSwitch from 'primevue/toggleswitch'
import Button from 'primevue/button'

defineOptions({ layout: AppLayout })

const studentService = createStudentService()

const genders = [
  { label: 'Nam',  value: 'Nam'  },
  { label: 'Nữ',   value: 'Nữ'   },
  { label: 'Khác', value: 'Khác' },
]

// Chuẩn hoá Date → YYYY-MM-DD (tránh lệch timezone)
function toYmdLocal(d) {
  if (!d) return null
  const dt = new Date(d)
  const y = dt.getFullYear()
  const m = String(dt.getMonth() + 1).padStart(2, '0')
  const day = String(dt.getDate()).padStart(2, '0')
  return `${y}-${m}-${day}`
}

const form = reactive({
  code: '',
  name: '',
  gender: null,
  dob: null,        // Date object từ DatePicker
  email: '',
  phone: '',
  address: '',
  active: true,

  saving: false,
  errors: {},
})

function save() {
  form.errors = {}

  // validate nhanh phía FE (BE vẫn là chuẩn)
  if (!form.name || !form.name.trim()) form.errors.name = 'Vui lòng nhập họ tên'
  if (!form.code || !form.code.trim()) form.errors.code = 'Vui lòng nhập mã học viên'
  if (Object.keys(form.errors).length) return

  form.saving = true
  studentService.create({
    code: form.code?.trim(),
    name: form.name?.trim(),
    gender: form.gender || null,
    dob: form.dob ? toYmdLocal(form.dob) : null,
    email: form.email?.trim() || null,
    phone: form.phone?.trim() || null,
    address: form.address?.trim() || null,
    active: !!form.active,
  }, {
    onSuccess: () => { /* BE set flash; FE không bắn toast */ },
    onError: (errors) => { form.errors = errors || {} },
    onFinish: () => { form.saving = false },
  })
}
</script>

<template>
  <Head title="Thêm học viên" />

  <div class="mb-3 flex justify-between items-center">
    <h1 class="text-xl md:text-2xl font-heading font-semibold">Thêm học viên</h1>
    <Link
      :href="route('manager.students.index')"
      class="px-3 py-2 text-sm rounded border border-slate-300 dark:border-slate-600 hover:bg-slate-100 dark:hover:bg-slate-800"
    >
      ← Quay lại danh sách
    </Link>
  </div>

  <div class="rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 p-4 max-w-2xl mx-auto">
    <div class="flex flex-col gap-4">
      <!-- Mã học viên -->
      <div>
        <label class="block text-sm font-medium mb-1">Mã học viên</label>
        <InputText v-model="form.code" class="w-full" placeholder="VD: STU001" />
        <div v-if="form.errors?.code" class="text-red-500 text-xs mt-1">{{ form.errors.code }}</div>
      </div>

      <!-- Họ tên -->
      <div>
        <label class="block text-sm font-medium mb-1">Họ tên</label>
        <InputText v-model="form.name" class="w-full" placeholder="Nguyễn Văn A" />
        <div v-if="form.errors?.name" class="text-red-500 text-xs mt-1">{{ form.errors.name }}</div>
      </div>

      <!-- Giới tính & Ngày sinh -->
      <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
        <div>
          <label class="block text-sm font-medium mb-1">Giới tính</label>
          <Select
            v-model="form.gender"
            :options="genders"
            optionLabel="label"
            optionValue="value"
            class="w-full"
            showClear
            placeholder="Chọn giới tính"
          />
          <div v-if="form.errors?.gender" class="text-red-500 text-xs mt-1">{{ form.errors.gender }}</div>
        </div>

        <div>
          <label class="block text-sm font-medium mb-1">Ngày sinh</label>
          <DatePicker
            v-model="form.dob"
            dateFormat="dd/mm/yy"
            class="w-full"
            showIcon
            iconDisplay="input"
            placeholder="dd/mm/yyyy"
          />
          <div v-if="form.errors?.dob" class="text-red-500 text-xs mt-1">{{ form.errors.dob }}</div>
        </div>
      </div>

      <!-- Email & Điện thoại -->
      <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
        <div>
          <label class="block text-sm font-medium mb-1">Email</label>
          <InputText v-model="form.email" class="w-full" placeholder="name@example.com" />
          <div v-if="form.errors?.email" class="text-red-500 text-xs mt-1">{{ form.errors.email }}</div>
        </div>
        <div>
          <label class="block text-sm font-medium mb-1">Điện thoại</label>
          <InputText v-model="form.phone" class="w-full" placeholder="09xx xxx xxx" />
          <div v-if="form.errors?.phone" class="text-red-500 text-xs mt-1">{{ form.errors.phone }}</div>
        </div>
      </div>

      <!-- Địa chỉ -->
      <div>
        <label class="block text-sm font-medium mb-1">Địa chỉ</label>
        <Textarea v-model="form.address" rows="2" autoResize class="w-full" />
        <div v-if="form.errors?.address" class="text-red-500 text-xs mt-1">{{ form.errors.address }}</div>
      </div>

      <!-- Trạng thái -->
      <div>
        <label class="block text-sm font-medium mb-1">Trạng thái</label>
        <div class="flex items-center gap-3 rounded-lg border border-slate-200 dark:border-slate-700 p-2">
          <span class="text-sm" :class="{ 'font-medium': form.active }">Đang hoạt động</span>
          <ToggleSwitch v-model="form.active" />
          <span class="text-sm" :class="{ 'font-medium': !form.active }">Ngừng</span>
        </div>
        <div v-if="form.errors?.active" class="text-red-500 text-xs mt-1">{{ form.errors.active }}</div>
      </div>

      <!-- Actions -->
      <div class="flex justify-end gap-2 mt-4">
        <Link
          :href="route('manager.students.index')"
          class="px-3 py-2 rounded border border-slate-300 dark:border-slate-600 hover:bg-slate-100 dark:hover:bg-slate-800"
        >
          Huỷ
        </Link>
        <Button label="Lưu" icon="pi pi-check" :loading="form.saving" @click="save" />
      </div>
    </div>
  </div>
</template>
