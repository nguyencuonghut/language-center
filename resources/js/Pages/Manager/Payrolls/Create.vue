<script setup>
import { reactive } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'

// PrimeVue
import InputText from 'primevue/inputtext'
import DatePicker from 'primevue/datepicker'
import Select from 'primevue/select'
import Textarea from 'primevue/textarea'
import Button from 'primevue/button'

defineOptions({ layout: AppLayout })

// Nhận props mặc định từ Inertia để tránh warning
defineProps({
  errors: Object,
  auth: Object,
  flash: Object,
  branches: {
    type: Array,
    default: () => [] // [{id, name}]
  },
  defaults: {
    type: Object,
    default: () => ({ // giá trị mặc định cho form nếu có
      period_from: null,
      period_to: null,
      branch_id: 'all'
    })
  }
})

const form = reactive({
  branch_id: 'all', // 'all' | branchId
  period_from: null, // Date object (local)
  period_to: null,   // Date object (local)
  note: '',
  saving: false,
  vErrors: {}
})

// helper: yyyy-mm-dd (local, không lệch timezone)
function toYmdLocal(d) {
  if (!d) return null
  const dt = new Date(d)
  const y = dt.getFullYear()
  const m = String(dt.getMonth() + 1).padStart(2, '0')
  const day = String(dt.getDate()).padStart(2, '0')
  return `${y}-${m}-${day}`
}

function submit() {
  form.vErrors = {}
  form.saving = true
  router.post(route('manager.payrolls.store'), {
    branch_id: form.branch_id === 'all' ? null : Number(form.branch_id),
    period_from: toYmdLocal(form.period_from),
    period_to: toYmdLocal(form.period_to),
    note: form.note || null
  }, {
    onFinish: () => { form.saving = false },
    onError: (errs) => { form.vErrors = errs || {} }
  })
}
</script>

<template>
  <Head title="Tạo bảng lương" />

  <!-- CONTAINER CĂN GIỮA -->
  <div class="max-w-3xl mx-auto">
    <!-- Tiêu đề -->
    <div class="mb-4">
      <h1 class="text-2xl font-heading font-semibold">Tạo bảng lương</h1>
      <p class="text-sm text-slate-500 dark:text-slate-400">
        Chọn kỳ tính lương và phạm vi chi nhánh.
      </p>
    </div>

    <!-- Card -->
    <div class="rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 shadow-sm">
      <div class="p-5">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium mb-1">Chi nhánh</label>
            <Select
              v-model="form.branch_id"
              :options="[{label:'Tất cả chi nhánh', value:'all'}, ...branches.map(b => ({label:b.name, value:String(b.id)}))]"
              optionLabel="label" optionValue="value"
              class="w-full"
            />
            <div v-if="form.vErrors?.branch_id" class="text-red-500 text-xs mt-1">{{ form.vErrors.branch_id }}</div>
          </div>

          <div class="grid grid-cols-2 gap-4 md:col-span-1 md:grid-cols-2">
            <div>
              <label class="block text-sm font-medium mb-1">Từ ngày</label>
              <DatePicker v-model="form.period_from" dateFormat="yy-mm-dd" showIcon iconDisplay="input" class="w-full" />
              <div v-if="form.vErrors?.period_from" class="text-red-500 text-xs mt-1">{{ form.vErrors.period_from }}</div>
            </div>
            <div>
              <label class="block text-sm font-medium mb-1">Đến ngày</label>
              <DatePicker v-model="form.period_to" dateFormat="yy-mm-dd" showIcon iconDisplay="input" class="w-full" />
              <div v-if="form.vErrors?.period_to" class="text-red-500 text-xs mt-1">{{ form.vErrors.period_to }}</div>
            </div>
          </div>
        </div>

        <div class="mt-4">
          <label class="block text-sm font-medium mb-1">Ghi chú</label>
          <Textarea v-model="form.note" autoResize rows="3" class="w-full" placeholder="Ghi chú nội bộ (tuỳ chọn)" />
          <div v-if="form.vErrors?.note" class="text-red-500 text-xs mt-1">{{ form.vErrors.note }}</div>
        </div>
      </div>

      <div class="px-5 py-3 border-t border-slate-200 dark:border-slate-700 flex items-center justify-end gap-2">
        <Button label="Huỷ" icon="pi pi-times" text @click="$inertia.visit(route('manager.payrolls.index'))" />
        <Button label="Tạo bảng lương" icon="pi pi-check" :loading="form.saving" @click="submit" />
      </div>
    </div>
  </div>
</template>
