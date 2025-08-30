<script setup>
import { reactive } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import { createTeachingAssignmentService } from '@/service/TeachingAssignmentService'

// PrimeVue
import Select from 'primevue/select'
import InputNumber from 'primevue/inputnumber'
import DatePicker from 'primevue/datepicker'
import Button from 'primevue/button'

defineOptions({ layout: AppLayout })

const props = defineProps({
  classroom: Object, // {id, code, name}
  teachers: Array    // [{id,name,email,label?,value?}]
})

const svc = createTeachingAssignmentService()

function toYmdLocal(d) {
  if (!d) return null
  const dt = new Date(d)
  const y = dt.getFullYear()
  const m = String(dt.getMonth() + 1).padStart(2, '0')
  const day = String(dt.getDate()).padStart(2, '0')
  return `${y}-${m}-${day}`
}

const form = reactive({
  teacher_id: null,
  rate_per_session: null,
  effective_from: null,
  effective_to: null,
  errors: {},
  saving: false
})

function save() {
  form.errors = {}
  // FE check nhẹ, BE vẫn validate chính
  if (!form.teacher_id) form.errors.teacher_id = 'Vui lòng chọn giáo viên'
  if (!form.rate_per_session || Number(form.rate_per_session) <= 0) form.errors.rate_per_session = 'Vui lòng nhập đơn giá > 0'
  if (Object.keys(form.errors).length) return

  form.saving = true
  svc.create(props.classroom.id, {
    teacher_id: Number(form.teacher_id),
    rate_per_session: Number(form.rate_per_session),
    effective_from: form.effective_from ? toYmdLocal(form.effective_from) : null,
    effective_to: form.effective_to ? toYmdLocal(form.effective_to) : null
  }, {
    onFinish: () => { form.saving = false },
    onSuccess: () => { /* BE flash */ },
    onError: (errors) => { form.errors = errors || {} }
  })
}
</script>

<template>
  <Head :title="`Phân công GV - ${classroom.name}`" />

  <div class="mb-3 flex justify-between items-center">
    <h1 class="text-xl md:text-2xl font-heading font-semibold">
      Phân công giáo viên · {{ classroom.code }} — {{ classroom.name }}
    </h1>
    <div class="flex gap-2">
      <Link
        :href="route('admin.classrooms.assignments.index', { classroom: classroom.id })"
        class="px-3 py-2 text-sm rounded border border-slate-300 dark:border-slate-600 hover:bg-slate-100 dark:hover:bg-slate-800"
      >
        ← Danh sách phân công
      </Link>
      <Link
        :href="route('admin.classrooms.edit', { classroom: classroom.id })"
        class="px-3 py-2 text-sm rounded border border-slate-300 dark:border-slate-600 hover:bg-slate-100 dark:hover:bg-slate-800"
      >
        Chi tiết lớp
      </Link>
    </div>
  </div>

  <div class="rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 p-4 max-w-2xl mx-auto">
    <div class="flex flex-col gap-4">
      <!-- Teacher -->
      <div>
        <label class="block text-sm font-medium mb-1">Giáo viên</label>
        <Select
          v-model="form.teacher_id"
          :options="(teachers||[]).map(t => ({label: t.label ?? `${t.name} (${t.email||'—'})`, value: String(t.id)}))"
          optionLabel="label" optionValue="value" class="w-full"
          placeholder="Chọn giáo viên"
        />
        <div v-if="form.errors?.teacher_id" class="text-red-500 text-xs mt-1">{{ form.errors.teacher_id }}</div>
      </div>

      <!-- Rate -->
      <div>
        <label class="block text-sm font-medium mb-1">Đơn giá / buổi</label>
        <InputNumber v-model="form.rate_per_session" class="w-full" mode="currency" currency="VND" locale="vi-VN" :min="0" />
        <div v-if="form.errors?.rate_per_session" class="text-red-500 text-xs mt-1">{{ form.errors.rate_per_session }}</div>
      </div>

      <!-- Effective dates -->
      <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
        <div>
          <label class="block text-sm font-medium mb-1">Hiệu lực từ</label>
          <DatePicker v-model="form.effective_from" dateFormat="dd/mm/yy" showIcon iconDisplay="input" class="w-full" />
          <div v-if="form.errors?.effective_from" class="text-red-500 text-xs mt-1">{{ form.errors.effective_from }}</div>
        </div>
        <div>
          <label class="block text-sm font-medium mb-1">Đến (tuỳ chọn)</label>
          <DatePicker v-model="form.effective_to" dateFormat="dd/mm/yy" showIcon iconDisplay="input" class="w-full" />
          <div v-if="form.errors?.effective_to" class="text-red-500 text-xs mt-1">{{ form.errors.effective_to }}</div>
        </div>
      </div>

      <!-- Actions -->
      <div class="flex justify-end gap-2 mt-2">
        <Link
          :href="route('admin.classrooms.assignments.index', { classroom: classroom.id })"
          class="px-3 py-2 rounded border border-slate-300 dark:border-slate-600 hover:bg-slate-100 dark:hover:bg-slate-800"
        >
          Huỷ
        </Link>
        <Button label="Lưu" icon="pi pi-check" :loading="form.saving" @click="save" />
      </div>
    </div>
  </div>
</template>
