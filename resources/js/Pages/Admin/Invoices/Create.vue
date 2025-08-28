<script setup>
import { reactive, ref } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'

// PrimeVue
import InputText from 'primevue/inputtext'
import InputNumber from 'primevue/inputnumber'
import Select from 'primevue/select'
import DatePicker from 'primevue/datepicker'
import Button from 'primevue/button'
import Textarea from 'primevue/textarea'

defineOptions({ layout: AppLayout })

const props = defineProps({
  branches: Array,   // [{ id, name }]
  students: Array,   // [{ id, name, code, label, value }]
  classes: Array     // [{ id, name, code, label, value }]
})

const form = reactive({
  branch_id: null,
  student_id: null,
  class_id: null,
  total: null,
  due_date: null,
  note: '',
  errors: {},
  saving: false
})

function toYmdLocal(d) {
  if (!d) return null
  const dt = new Date(d)
  const y = dt.getFullYear()
  const m = String(dt.getMonth() + 1).padStart(2, '0')
  const day = String(dt.getDate()).padStart(2, '0')
  return `${y}-${m}-${day}`
}

function save() {
  form.errors = {}
  if (!form.student_id) form.errors.student_id = 'Vui lòng chọn học viên'
  if (!form.total) form.errors.total = 'Vui lòng nhập tổng tiền'

  if (Object.keys(form.errors).length) return

  form.saving = true
  router.post(route('admin.invoices.store'), {
    branch_id: form.branch_id ? Number(form.branch_id) : null,
    student_id: Number(form.student_id),
    class_id: form.class_id ? Number(form.class_id) : null,
    total: form.total,
    due_date: form.due_date ? toYmdLocal(form.due_date) : null,
    note: form.note || null
  }, {
    preserveScroll: true,
    onFinish: () => { form.saving = false }
  })
}
</script>

<template>
  <Head title="Tạo hoá đơn" />

  <div class="mb-3 flex justify-between items-center">
    <h1 class="text-xl md:text-2xl font-heading font-semibold">Tạo hoá đơn</h1>
    <Link :href="route('admin.invoices.index')" class="px-3 py-2 text-sm rounded border border-slate-300 dark:border-slate-600 hover:bg-slate-100 dark:hover:bg-slate-800">
      ← Quay lại danh sách
    </Link>
  </div>

  <div class="rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 p-4 max-w-2xl mx-auto">
    <div class="flex flex-col gap-4">
      <!-- Branch -->
      <div>
        <label class="block text-sm font-medium mb-1">Chi nhánh</label>
        <Select
          v-model="form.branch_id"
          :options="[{label:'Chọn chi nhánh', value:null}, ...(props.branches||[]).map(b => ({label:b.name, value:String(b.id)}))]"
          optionLabel="label"
          optionValue="value"
          class="w-full"
          showClear
        />
      </div>

      <!-- Student -->
      <div>
        <label class="block text-sm font-medium mb-1">Học viên</label>
        <Select
          v-model="form.student_id"
          :options="(props.students||[]).map(s => ({label:`${s.code} · ${s.name}`, value:String(s.id)}))"
          optionLabel="label"
          optionValue="value"
          class="w-full"
        />
        <div v-if="form.errors?.student_id" class="text-red-500 text-xs mt-1">{{ form.errors.student_id }}</div>
      </div>

      <!-- Class -->
      <div>
        <label class="block text-sm font-medium mb-1">Lớp (tuỳ chọn)</label>
        <Select
          v-model="form.class_id"
          :options="(props.classes||[]).map(c => ({label:`${c.code} · ${c.name}`, value:String(c.id)}))"
          optionLabel="label"
          optionValue="value"
          class="w-full"
          showClear
        />
      </div>

      <!-- Total -->
      <div>
        <label class="block text-sm font-medium mb-1">Tổng tiền</label>
        <InputNumber v-model="form.total" class="w-full" mode="currency" currency="VND" locale="vi-VN" />
        <div v-if="form.errors?.total" class="text-red-500 text-xs mt-1">{{ form.errors.total }}</div>
      </div>

      <!-- Due Date -->
      <div>
        <label class="block text-sm font-medium mb-1">Hạn thanh toán</label>
        <DatePicker v-model="form.due_date" dateFormat="dd/mm/yy" class="w-full" showIcon iconDisplay="input" />
      </div>

      <!-- Note -->
      <div>
        <label class="block text-sm font-medium mb-1">Ghi chú</label>
        <Textarea v-model="form.note" rows="3" autoResize class="w-full" />
      </div>

      <!-- Actions -->
      <div class="flex justify-end gap-2 mt-4">
        <Link :href="route('admin.invoices.index')" class="px-3 py-2 rounded border border-slate-300 dark:border-slate-600 hover:bg-slate-100 dark:hover:bg-slate-800">
          Huỷ
        </Link>
        <Button label="Lưu" icon="pi pi-check" :loading="form.saving" @click="save" />
      </div>
    </div>
  </div>
</template>
