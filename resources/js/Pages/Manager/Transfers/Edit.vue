<script setup>
import { reactive, ref } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'

// PrimeVue
import Select from 'primevue/select'
import DatePicker from 'primevue/datepicker'
import InputText from 'primevue/inputtext'
import Textarea from 'primevue/textarea'
import Button from 'primevue/button'
import ToggleSwitch from 'primevue/toggleswitch'
import Tag from 'primevue/tag'

defineOptions({ layout: AppLayout })

const props = defineProps({
  transfer: Object, // {id, student:{code,name}, from_class:{id,code,name}, to_class_id, effective_date, auto_invoice, note}
  classes: Array    // [{id, code, name, label, value}]
})

/* ---------- helpers ---------- */
function toYmdLocal(d) {
  if (!d) return null
  const dt = new Date(d)
  const y = dt.getFullYear()
  const m = String(dt.getMonth() + 1).padStart(2, '0')
  const day = String(dt.getDate()).padStart(2, '0')
  return `${y}-${m}-${day}`
}
function parseDateYmd(s) {
  if (!s) return null
  // s: 'YYYY-MM-DD' → Date (local midnight)
  const [y, m, d] = String(s).split('-').map(Number)
  if (!y || !m || !d) return null
  return new Date(y, m - 1, d)
}

/* ---------- form ---------- */
const form = reactive({
  to_class_id: props.transfer?.to_class_id ? String(props.transfer.to_class_id) : null,
  effective_date: parseDateYmd(props.transfer?.effective_date),
  auto_invoice: !!props.transfer?.auto_invoice,
  note: props.transfer?.note ?? '',
  errors: {},
  saving: false
})

/* ---------- submit ---------- */
function save() {
  form.errors = {}
  if (!form.to_class_id) form.errors.to_class_id = 'Vui lòng chọn lớp mới.'
  if (!form.effective_date) form.errors.effective_date = 'Vui lòng chọn ngày hiệu lực.'

  if (Object.keys(form.errors).length) return

  form.saving = true
  router.put(route('admin.transfers.update', props.transfer.id), {
    to_class_id: Number(form.to_class_id),
    effective_date: toYmdLocal(form.effective_date),
    auto_invoice: form.auto_invoice ? 1 : 0,
    note: form.note || null
  }, {
    preserveScroll: true,
    onFinish: () => { form.saving = false }
  })
}

function destroyTransfer() {
  if (!confirm('Xác nhận xoá bản ghi chuyển lớp này?')) return
  router.delete(route('admin.transfers.destroy', props.transfer.id), {
    preserveScroll: true
  })
}
</script>

<template>
  <Head :title="`Sửa chuyển lớp #${transfer.id}`" />

  <div class="mb-3 flex items-center justify-between">
    <h1 class="text-xl md:text-2xl font-heading font-semibold">
      Sửa chuyển lớp #{{ transfer.id }}
    </h1>
    <div class="flex items-center gap-2">
      <button
        class="px-3 py-2 rounded border border-red-300 text-red-600 hover:bg-red-50
               dark:border-red-700 dark:text-red-300 dark:hover:bg-red-900/20"
        @click="destroyTransfer"
      >
        <i class="pi pi-trash mr-1"></i> Xoá
      </button>
      <Link
        :href="route('admin.transfers.index')"
        class="px-3 py-2 rounded border border-slate-300 dark:border-slate-600 hover:bg-slate-100 dark:hover:bg-slate-800"
      >
        ← Danh sách
      </Link>
    </div>
  </div>

  <div class="rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 p-4 max-w-2xl mx-auto">
    <div class="flex flex-col gap-4">
      <!-- Student (readonly) -->
      <div>
        <label class="block text-sm font-medium mb-1">Học viên</label>
        <div class="px-3 py-2 rounded-lg bg-slate-50 dark:bg-slate-900/40 border border-slate-200 dark:border-slate-700">
          <span class="font-medium">{{ transfer.student?.code }} · {{ transfer.student?.name }}</span>
        </div>
      </div>

      <!-- From class (readonly) -->
      <div>
        <label class="block text-sm font-medium mb-1">Lớp cũ</label>
        <div class="px-3 py-2 rounded-lg bg-slate-50 dark:bg-slate-900/40 border border-slate-200 dark:border-slate-700">
          <span class="font-medium">{{ transfer.from_class?.code }} · {{ transfer.from_class?.name }}</span>
        </div>
      </div>

      <!-- To class -->
      <div>
        <label class="block text-sm font-medium mb-1">Lớp mới</label>
        <Select
          v-model="form.to_class_id"
          :options="(classes||[]).map(c => ({ label: `${c.code} · ${c.name}`, value: String(c.id) }))"
          optionLabel="label"
          optionValue="value"
          class="w-full"
          :pt="{ root: { class: 'min-w-full' } }"
          showClear
          placeholder="Chọn lớp mới..."
        />
        <div v-if="form.errors?.to_class_id" class="text-red-500 text-xs mt-1">{{ form.errors.to_class_id }}</div>
      </div>

      <!-- Effective date -->
      <div>
        <label class="block text-sm font-medium mb-1">Ngày hiệu lực</label>
        <DatePicker
          v-model="form.effective_date"
          dateFormat="dd/mm/yy"
          showIcon
          iconDisplay="input"
          class="w-full"
        />
        <div v-if="form.errors?.effective_date" class="text-red-500 text-xs mt-1">{{ form.errors.effective_date }}</div>
      </div>

      <!-- Auto invoice -->
      <div>
        <label class="block text-sm font-medium mb-1">Tự động tạo hoá đơn phát sinh chênh lệch</label>
        <div class="flex items-center justify-between rounded-lg border border-slate-200 dark:border-slate-700 p-2">
          <span class="text-sm" :class="{ 'font-medium': form.auto_invoice }">Bật</span>
          <ToggleSwitch v-model="form.auto_invoice" />
          <span class="text-sm" :class="{ 'font-medium': !form.auto_invoice }">Tắt</span>
        </div>
      </div>

      <!-- Note -->
      <div>
        <label class="block text-sm font-medium mb-1">Ghi chú</label>
        <Textarea v-model="form.note" rows="3" autoResize class="w-full" placeholder="Lý do chuyển lớp, ghi chú thêm..." />
      </div>

      <!-- Actions -->
      <div class="flex justify-end gap-2 mt-2">
        <Link
          :href="route('admin.transfers.index')"
          class="px-3 py-2 rounded border border-slate-300 dark:border-slate-600 hover:bg-slate-100 dark:hover:bg-slate-800"
        >
          Huỷ
        </Link>
        <Button label="Lưu" icon="pi pi-check" :loading="form.saving" @click="save" />
      </div>
    </div>
  </div>
</template>
