<script setup>
import { reactive, ref, watch, computed } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'

// PrimeVue
import InputNumber from 'primevue/inputnumber'
import Select from 'primevue/select'
import DatePicker from 'primevue/datepicker'
import Button from 'primevue/button'
import Textarea from 'primevue/textarea'
import ToggleSwitch from 'primevue/toggleswitch'

defineOptions({ layout: AppLayout })

const props = defineProps({
  invoice: Object,     // {id, branch_id, student_id, class_id, total, due_date, note}
  branches: Array,     // [{ id, name }]
  students: Array,     // [{ id, name, code, label, value }]
  classrooms: Array,      // [{ id, name, code, label, value }]
  defaults: {          // { total_default: number|null } gợi ý do BE tính
    type: Object,
    default: () => ({ total_default: null })
  }
})

/* helpers */
function toYmdLocal(d) {
  if (!d) return null
  const dt = new Date(d)
  const y = dt.getFullYear()
  const m = String(dt.getMonth() + 1).padStart(2, '0')
  const day = String(dt.getDate()).padStart(2, '0')
  return `${y}-${m}-${day}`
}
function fromYmd(d){
  if (!d) return null
  const [y,m,day] = String(d).split('-').map(v=>parseInt(v,10))
  if (!y || !m || !day) return null
  return new Date(y, m-1, day)
}
function fmtVnd(n){
  if (n == null) return '—'
  try { return new Intl.NumberFormat('vi-VN', { style:'currency', currency:'VND' }).format(n) }
  catch { return String(n) }
}

/* isAutoCalc mặc định: nếu total hiện tại bằng gợi ý → coi như đang tự tính */
const initialAuto = computed(() => {
  const cur = Number(props.invoice?.total ?? 0)
  const def = props.defaults?.total_default
  return def != null && Number(def) === cur
})
const isAutoCalc = ref(initialAuto.value)

/* form */
const form = reactive({
  branch_id: props.invoice?.branch_id ? String(props.invoice.branch_id) : null,
  student_id: props.invoice?.student_id ? String(props.invoice.student_id) : null,
  class_id:   props.invoice?.class_id   ? String(props.invoice.class_id)   : null,
  total:      props.invoice?.total ?? null,
  due_date:   fromYmd(props.invoice?.due_date),
  note:       props.invoice?.note || '',
  errors: {},
  saving: false
})

/* preview defaults (partial reload) */
function refreshPreview(){
  // reset để tránh user hiểu nhầm số cũ
  form.total = null

  const q = {}
  if (form.branch_id)  q.branch_id  = form.branch_id
  if (form.student_id) q.student_id = form.student_id
  if (form.class_id)   q.class_id   = form.class_id

  router.visit(route('manager.invoices.edit', { invoice: props.invoice.id, ...q }), {
    preserveScroll: true,
    preserveState: true,
    only: ['defaults'],
  })
}

/* khi đổi hv/lớp và đang tự tính → refresh */
watch(() => form.student_id, () => { if (isAutoCalc.value) refreshPreview() })
watch(() => form.class_id,   () => { if (isAutoCalc.value) refreshPreview() })

/* khi bật lại tự tính → refresh */
watch(isAutoCalc, (on) => {
  if (on) refreshPreview()
})

/* khi defaults.total_default thay đổi → nếu đang tự tính thì đổ vào input */
watch(() => props.defaults?.total_default, (v) => {
  if (isAutoCalc.value) form.total = v ?? null
})

/* submit */
function update(){
  form.errors = {}
  if (!form.student_id) form.errors.student_id = 'Vui lòng chọn học viên'
  if (form.total == null || form.total === '' || Number(form.total) <= 0) {
    form.errors.total = 'Vui lòng nhập tổng tiền'
  }
  if (Object.keys(form.errors).length) return

  form.saving = true
  router.put(route('manager.invoices.update', { invoice: props.invoice.id }), {
    branch_id: form.branch_id ? Number(form.branch_id) : null,
    student_id: Number(form.student_id),
    class_id: form.class_id ? Number(form.class_id) : null,
    total: Number(form.total),
    due_date: form.due_date ? toYmdLocal(form.due_date) : null,
    note: form.note || null
  }, {
    preserveScroll: true,
    onFinish: () => { form.saving = false }
  })
}
</script>

<template>
  <Head :title="`Sửa hoá đơn #${invoice?.id}`" />

  <div class="mb-3 flex justify-between items-center">
    <h1 class="text-xl md:text-2xl font-heading font-semibold">Sửa hoá đơn</h1>
    <div class="flex gap-2">
      <Link
        :href="route('manager.invoices.show', { invoice: invoice.id })"
        class="px-3 py-2 text-sm rounded border border-slate-300 dark:border-slate-600 hover:bg-slate-100 dark:hover:bg-slate-800"
      >
        ← Quay lại chi tiết
      </Link>
      <Link
        :href="route('manager.invoices.index')"
        class="px-3 py-2 text-sm rounded border border-slate-300 dark:border-slate-600 hover:bg-slate-100 dark:hover:bg-slate-800"
      >
        Danh sách
      </Link>
    </div>
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
          :options="(props.classrooms||[]).map(c => ({label:`${c.code} · ${c.name}`, value:String(c.id)}))"
          optionLabel="label"
          optionValue="value"
          class="w-full"
          showClear
        />
      </div>

      <!-- Total (Auto/Manual) -->
      <div class="grid grid-cols-1 md:grid-cols-3 gap-3 items-start">
        <div class="md:col-span-2">
          <label class="block text-sm font-medium mb-1">Tổng tiền</label>
          <InputNumber
            v-model="form.total"
            class="w-full"
            mode="currency"
            currency="VND"
            locale="vi-VN"
            :disabled="isAutoCalc"
          />
          <div v-if="form.errors?.total" class="text-red-500 text-xs mt-1">{{ form.errors.total }}</div>

          <p class="text-xs mt-1 text-slate-500 dark:text-slate-400">
            Gợi ý: <span class="font-medium text-slate-900 dark:text-slate-100">{{ fmtVnd(props.defaults?.total_default) }}</span>
          </p>
        </div>

        <div class="md:col-span-1">
          <label class="block text-sm font-medium mb-1">Chế độ tổng tiền</label>
          <div class="flex items-center justify-between rounded-lg border border-slate-200 dark:border-slate-700 p-2">
            <span class="text-sm" :class="{ 'font-medium': isAutoCalc }">Tự tính</span>
            <ToggleSwitch v-model="isAutoCalc" />
            <span class="text-sm" :class="{ 'font-medium': !isAutoCalc }">Tuỳ chỉnh</span>
          </div>
        </div>
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
        <Link :href="route('manager.invoices.show', { invoice: invoice.id })" class="px-3 py-2 rounded border border-slate-300 dark:border-slate-600 hover:bg-slate-100 dark:hover:bg-slate-800">
          Huỷ
        </Link>
        <Button label="Cập nhật" icon="pi pi-check" :loading="form.saving" @click="update" />
      </div>
    </div>
  </div>
</template>
