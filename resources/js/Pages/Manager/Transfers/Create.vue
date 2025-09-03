<script setup>
import { reactive, ref, watch } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'

// PrimeVue
import Select from 'primevue/select'
import InputNumber from 'primevue/inputnumber'
import DatePicker from 'primevue/datepicker'
import Textarea from 'primevue/textarea'
import Button from 'primevue/button'
import ToggleSwitch from 'primevue/toggleswitch'

defineOptions({ layout: AppLayout })

/**
 * Props gợi ý:
 * - students:   [{id, code, name, label, value}]
 * - sources:    danh sách lớp mà HV đang học [{id, code, name, label, value}]
 * - targets:    danh sách lớp có thể chuyển đến (cùng course/branch…) [{...}]
 * - defaults:   { start_session_no_default: number|null, auto_invoice_default: boolean, tuition_diff_default: number|null }
 */
const props = defineProps({
  students: { type: Array, default: () => [] },
  sources:  { type: Array, default: () => [] },
  targets:  { type: Array, default: () => [] },
  defaults: {
    type: Object,
    default: () => ({
      start_session_no_default: 1,
      auto_invoice_default: true,
      tuition_diff_default: null,
    })
  }
})

/* -------- helpers -------- */
function toYmdLocal(d) {
  if (!d) return null
  const dt = new Date(d)
  const y = dt.getFullYear()
  const m = String(dt.getMonth() + 1).padStart(2, '0')
  const day = String(dt.getDate()).padStart(2, '0')
  return `${y}-${m}-${day}`
}
function fmtVnd(n){
  if (n == null) return '—'
  try { return new Intl.NumberFormat('vi-VN', { style:'currency', currency:'VND' }).format(n) }
  catch { return String(n) }
}

/* -------- form -------- */
const isAutoInvoice = ref(!!props.defaults?.auto_invoice_default)

const form = reactive({
  student_id: null,
  from_class_id: null,
  to_class_id: null,
  effective_date: null,              // optional: ngày hiệu lực chuyển
  start_session_no: props.defaults?.start_session_no_default ?? 1,
  note: '',
  auto_invoice: isAutoInvoice.value,
  errors: {},
  saving: false,
})

/* Tổng chênh lệch học phí — do BE gợi ý (tuỳ chọn) */
const tuitionDiff = ref(props.defaults?.tuition_diff_default ?? null)

/* -------- partial reload để BE gợi ý lớp/giá trị mặc định -------- */
function refreshSuggestions(){
  const q = {}
  if (form.student_id) q.student_id = form.student_id
  if (form.from_class_id) q.from_class_id = form.from_class_id
  if (form.to_class_id) q.to_class_id = form.to_class_id

  router.visit(route('manager.transfers.create', q), {
    preserveScroll: true,
    preserveState: true,
    // Nếu BE hỗ trợ partial reload:
    // only: ['sources','targets','defaults'],
    onSuccess: () => {
      // Sau reload, props.* đã cập nhật (Inertia sẽ thay); đồng bộ lại local
      if (props.defaults) {
        tuitionDiff.value = props.defaults?.tuition_diff_default ?? null
        if (form.start_session_no == null || String(form.start_session_no).trim() === '') {
          form.start_session_no = props.defaults?.start_session_no_default ?? 1
        }
        if (isAutoInvoice.value) {
          form.auto_invoice = true
        }
      }
    }
  })
}

/* Watch biến để gợi ý */
watch(() => form.student_id, () => refreshSuggestions())
watch(() => form.from_class_id, () => refreshSuggestions())
watch(() => form.to_class_id,   () => refreshSuggestions())

/* Toggle auto invoice */
watch(isAutoInvoice, (v) => { form.auto_invoice = !!v })

/* -------- submit -------- */
function save(){
  form.errors = {}

  if (!form.student_id)    form.errors.student_id = 'Vui lòng chọn học viên.'
  if (!form.from_class_id) form.errors.from_class_id = 'Vui lòng chọn lớp nguồn.'
  if (!form.to_class_id)   form.errors.to_class_id = 'Vui lòng chọn lớp đích.'
  if (form.from_class_id && form.to_class_id && String(form.from_class_id) === String(form.to_class_id)) {
    form.errors.to_class_id = 'Lớp đích phải khác lớp nguồn.'
  }
  if (!form.start_session_no || Number(form.start_session_no) < 1) {
    form.errors.start_session_no = '“Bắt đầu từ buổi số” phải ≥ 1.'
  }

  if (Object.keys(form.errors).length) return

  form.saving = true
  router.post(route('manager.transfers.store'), {
    student_id: Number(form.student_id),
    from_class_id: Number(form.from_class_id),
    to_class_id: Number(form.to_class_id),
    start_session_no: Number(form.start_session_no),
    effective_date: form.effective_date ? toYmdLocal(form.effective_date) : null,
    note: form.note || null,
    auto_invoice: !!form.auto_invoice,
  }, {
    preserveScroll: true,
    onFinish: () => { form.saving = false }
  })
}
</script>

<template>
  <Head title="Chuyển lớp" />

  <div class="mb-3 flex justify-between items-center">
    <h1 class="text-xl md:text-2xl font-heading font-semibold">Chuyển lớp học viên</h1>
    <Link
      :href="route('manager.transfers.index')"
      class="px-3 py-2 text-sm rounded border border-slate-300 dark:border-slate-600 hover:bg-slate-100 dark:hover:bg-slate-800"
    >
      ← Quay lại danh sách
    </Link>
  </div>

  <div class="rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 p-4 max-w-2xl mx-auto">
    <div class="flex flex-col gap-4">
      <!-- Student -->
      <div>
        <label class="block text-sm font-medium mb-1">Học viên</label>
        <Select
          v-model="form.student_id"
          :options="(props.students||[]).map(s => ({label: `${s.code} · ${s.name}`, value: String(s.id)}))"
          optionLabel="label"
          optionValue="value"
          class="w-full"
          placeholder="Chọn học viên…"
        />
        <div v-if="form.errors?.student_id" class="text-red-500 text-xs mt-1">{{ form.errors.student_id }}</div>
      </div>

      <!-- From / To class -->
      <div class="grid md:grid-cols-2 gap-3">
        <div>
          <label class="block text-sm font-medium mb-1">Lớp nguồn</label>
          <Select
            v-model="form.from_class_id"
            :options="(props.sources||[]).map(c => ({label:`${c.code} · ${c.name}`, value:String(c.id)}))"
            optionLabel="label"
            optionValue="value"
            class="w-full"
            placeholder="Chọn lớp hiện tại…"
          />
          <div v-if="form.errors?.from_class_id" class="text-red-500 text-xs mt-1">{{ form.errors.from_class_id }}</div>
        </div>

        <div>
          <label class="block text-sm font-medium mb-1">Lớp đích</label>
          <Select
            v-model="form.to_class_id"
            :options="(props.targets||[]).map(c => ({label:`${c.code} · ${c.name}`, value:String(c.id)}))"
            optionLabel="label"
            optionValue="value"
            class="w-full"
            placeholder="Chọn lớp chuyển đến…"
          />
          <div v-if="form.errors?.to_class_id" class="text-red-500 text-xs mt-1">{{ form.errors.to_class_id }}</div>
        </div>
      </div>

      <!-- Effective date + start_session_no -->
      <div class="grid md:grid-cols-2 gap-3">
        <div>
          <label class="block text-sm font-medium mb-1">Ngày hiệu lực (tuỳ chọn)</label>
          <DatePicker v-model="form.effective_date" dateFormat="dd/mm/yy" showIcon iconDisplay="input" class="w-full" />
        </div>
        <div>
          <label class="block text-sm font-medium mb-1">Bắt đầu từ buổi số</label>
          <InputNumber v-model="form.start_session_no" class="w-full" :min="1" inputId="startNo" />
          <div v-if="form.errors?.start_session_no" class="text-red-500 text-xs mt-1">{{ form.errors.start_session_no }}</div>
        </div>
      </div>

      <!-- Note -->
      <div>
        <label class="block text-sm font-medium mb-1">Ghi chú</label>
        <Textarea v-model="form.note" rows="3" autoResize class="w-full" placeholder="Lý do chuyển lớp…" />
      </div>

      <!-- Auto invoice toggle + diff preview -->
      <div class="grid md:grid-cols-2 gap-3 items-start">
        <div class="rounded-lg border border-slate-200 dark:border-slate-700 p-3">
          <div class="flex items-center justify-between">
            <span class="text-sm">Tự động tạo hoá đơn chênh lệch</span>
            <ToggleSwitch v-model="isAutoInvoice" />
          </div>
          <p class="text-xs mt-2 text-slate-500 dark:text-slate-400">
            Chênh lệch dự kiến: <span class="font-medium text-slate-900 dark:text-slate-100">{{ fmtVnd(tuitionDiff) }}</span>
          </p>
        </div>
      </div>

      <!-- Actions -->
      <div class="flex justify-end gap-2 mt-2">
        <Link
          :href="route('manager.transfers.index')"
          class="px-3 py-2 rounded border border-slate-300 dark:border-slate-600 hover:bg-slate-100 dark:hover:bg-slate-800"
        >
          Huỷ
        </Link>
        <Button label="Lưu" icon="pi pi-check" :loading="form.saving" @click="save" />
      </div>
    </div>
  </div>
</template>
