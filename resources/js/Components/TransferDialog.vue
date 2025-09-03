<script setup>
import { reactive, watch, computed } from 'vue'
import Dialog from 'primevue/dialog'
import Select from 'primevue/select'
import DatePicker from 'primevue/datepicker'
import InputNumber from 'primevue/inputnumber'
import Textarea from 'primevue/textarea'
import Button from 'primevue/button'
import ToggleSwitch from 'primevue/toggleswitch'

const props = defineProps({
  modelValue: { type: Boolean, default: false },  // visible
  student: { type: Object, default: () => ({}) },
  fromClass: { type: Object, default: () => ({}) },
  classOptions: { type: Array, default: () => [] },
  defaults: { type: Object, default: () => ({}) },
  saving: { type: Boolean, default: false },
})

const emit = defineEmits(['update:modelValue','submit','cancel'])

/* ---- Bridge v-model:visible <-> modelValue ---- */
const visibleProxy = computed({
  get: () => props.modelValue,
  set: (v) => emit('update:modelValue', v)
})

/* ---------- Helpers ---------- */
function toYmdLocal(d) {
  if (!d) return null
  const dt = new Date(d)
  const y = dt.getFullYear()
  const m = String(dt.getMonth()+1).padStart(2,'0')
  const day = String(dt.getDate()).padStart(2,'0')
  return `${y}-${m}-${day}`
}

const formattedClassOptions = computed(() =>
  (props.classOptions || []).map(c => ({
    label: `${c.code ?? `CL${c.id}`} · ${c.name}`,
    value: String(c.id),
  }))
)

/* ---------- Local form state ---------- */
const form = reactive({
  to_class_id: null,
  start_session_no: props.defaults?.start_session_no ?? 1,
  effective_date: props.defaults?.effective_date ? new Date(props.defaults.effective_date) : new Date(),
  create_adjustments: props.defaults?.create_adjustments ?? true,
  note: props.defaults?.note ?? '',
  errors: {},
})

/* Reset mỗi khi mở dialog */
watch(() => props.modelValue, (open) => {
  if (open) {
    form.to_class_id = null
    form.start_session_no = props.defaults?.start_session_no ?? 1
    form.effective_date = props.defaults?.effective_date ? new Date(props.defaults.effective_date) : new Date()
    form.create_adjustments = props.defaults?.create_adjustments ?? true
    form.note = props.defaults?.note ?? ''
    form.errors = {}
  }
})

function close() {
  emit('update:modelValue', false)
  emit('cancel')
}

function submit() {
  form.errors = {}
  if (!form.to_class_id) {
    form.errors.to_class_id = 'Vui lòng chọn lớp chuyển đến.'
  } else if (props.fromClass?.id && String(props.fromClass.id) === String(form.to_class_id)) {
    form.errors.to_class_id = 'Lớp chuyển đến phải khác lớp hiện tại.'
  }
  if (!form.start_session_no || Number(form.start_session_no) < 1) {
    form.errors.start_session_no = 'Số buổi bắt đầu phải từ 1.'
  }
  if (!form.effective_date) {
    form.errors.effective_date = 'Vui lòng chọn ngày hiệu lực.'
  }
  if (Object.keys(form.errors).length) return

  const payload = {
    student_id: props.student?.id ?? null,
    from_class_id: props.fromClass?.id ?? null,
    to_class_id: Number(form.to_class_id),
    start_session_no: Number(form.start_session_no),
    effective_date: toYmdLocal(form.effective_date),
    create_adjustments: !!form.create_adjustments,
    note: form.note || null,
  }

  emit('submit', payload)
}
</script>

<template>
  <Dialog
    v-model:visible="visibleProxy"
    modal
    :style="{ width: '640px', maxWidth: '95vw' }"
    :breakpoints="{ '640px': '95vw' }"
    header="Chuyển lớp"
  >
    <div class="space-y-4">
      <!-- Info -->
      <div class="rounded-md bg-slate-50 dark:bg-slate-800/60 p-3 text-sm">
        <div><span class="text-slate-500">Học viên:</span> <span class="font-medium">{{ student?.code }} · {{ student?.name }}</span></div>
        <div class="mt-1">
          <span class="text-slate-500">Lớp hiện tại:</span>
          <span class="font-medium">{{ fromClass?.code }} · {{ fromClass?.name }}</span>
        </div>
      </div>

      <!-- Target class -->
      <div>
        <label class="block text-sm font-medium mb-1">Chuyển sang lớp</label>
        <Select
          v-model="form.to_class_id"
          :options="formattedClassOptions"
          optionLabel="label"
          optionValue="value"
          class="w-full"
          placeholder="Chọn lớp đích…"
          showClear
        />
        <div v-if="form.errors?.to_class_id" class="text-red-500 text-xs mt-1">{{ form.errors.to_class_id }}</div>
      </div>

      <!-- Start session + Effective date -->
      <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
        <div>
          <label class="block text-sm font-medium mb-1">Bắt đầu từ buổi số</label>
          <InputNumber v-model="form.start_session_no" class="w-full" :min="1" :useGrouping="false" />
          <div v-if="form.errors?.start_session_no" class="text-red-500 text-xs mt-1">{{ form.errors.start_session_no }}</div>
        </div>
        <div>
          <label class="block text-sm font-medium mb-1">Ngày hiệu lực</label>
          <DatePicker v-model="form.effective_date" dateFormat="dd/mm/yy" showIcon iconDisplay="input" class="w-full" />
          <div v-if="form.errors?.effective_date" class="text-red-500 text-xs mt-1">{{ form.errors.effective_date }}</div>
        </div>
      </div>

      <!-- Billing adjustments -->
      <div class="rounded-lg border border-slate-200 dark:border-slate-700 p-3">
        <div class="flex items-center justify-between gap-3">
          <div>
            <div class="font-medium">Hoá đơn điều chỉnh</div>
            <p class="text-sm text-slate-500">
              Tự động tạo mục <em>chuyển lớp ra/vào</em> trong hoá đơn (nếu cần).
            </p>
          </div>
          <div class="flex items-center gap-3">
            <span class="text-sm" :class="{ 'font-medium': form.create_adjustments }">Bật</span>
            <ToggleSwitch v-model="form.create_adjustments" />
          </div>
        </div>
      </div>

      <!-- Note -->
      <div>
        <label class="block text-sm font-medium mb-1">Ghi chú (tuỳ chọn)</label>
        <Textarea v-model="form.note" rows="3" autoResize class="w-full" />
      </div>
    </div>

    <template #footer>
      <Button label="Huỷ" icon="pi pi-times" text @click="close" />
      <Button
        label="Xác nhận chuyển"
        icon="pi pi-check"
        :loading="saving"
        @click="submit"
        autofocus
      />
    </template>
  </Dialog>
</template>

<style scoped>
@media (max-width: 640px) {
  .p-dialog .p-dialog-content { padding-top: 0.75rem; }
}
</style>
