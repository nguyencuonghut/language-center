<script setup>
import { reactive, ref, computed } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'

// PrimeVue
import InputText from 'primevue/inputtext'
import Textarea from 'primevue/textarea'
import DatePicker from 'primevue/datepicker'
import Select from 'primevue/select'
import Button from 'primevue/button'

defineOptions({ layout: AppLayout })

const props = defineProps({
  // Gợi ý: controller trả về invoice đã load kèm student, branch
  // { id, code, status, due_date, note, branch:{id,name}, student:{id,code,name} }
  invoice: Object,
  // (tuỳ) danh sách trạng thái từ BE, nếu không có sẽ dùng defaultOptions
  statusOptions: {
    type: Array,
    default: () => ([
      { label: 'Chưa thanh toán', value: 'unpaid' },
      { label: 'Thanh toán một phần', value: 'partial' },
      { label: 'Đã thanh toán', value: 'paid' },
      { label: 'Hoàn tiền', value: 'refunded' },
    ])
  }
})

/* Helpers date: gửi về BE dạng YYYY-MM-DD */
function toYmdLocal(d) {
  if (!d) return null
  const dt = new Date(d)
  const y = dt.getFullYear()
  const m = String(dt.getMonth() + 1).padStart(2, '0')
  const day = String(dt.getDate()).padStart(2, '0')
  return `${y}-${m}-${day}`
}
function fromYmdToDate(ymd) {
  if (!ymd) return null
  // Tạo Date theo local để tránh lệch múi giờ
  const [y, m, d] = String(ymd).split('-').map(Number)
  return new Date(y, (m - 1), d)
}

/* Form state */
const form = reactive({
  code: props.invoice?.code ?? '',
  branchName: props.invoice?.branch?.name ?? '—',
  studentName: (props.invoice?.student?.code ? props.invoice.student.code + ' · ' : '') + (props.invoice?.student?.name ?? '—'),
  status: props.invoice?.status ?? 'unpaid',
  due_date: props.invoice?.due_date ? fromYmdToDate(props.invoice.due_date) : null,
  note: props.invoice?.note ?? '',
  saving: false,
  errors: {}
})

function submit() {
  form.errors = {}
  form.saving = true

  router.put(route('admin.invoices.update', props.invoice.id), {
    status: form.status,
    due_date: toYmdLocal(form.due_date),
    note: form.note || null,
  }, {
    preserveScroll: true,
    onFinish: () => { form.saving = false },
    onError: (errors) => { form.errors = errors || {} }
  })
}
</script>

<template>
  <Head :title="`Sửa hoá đơn #${invoice?.code || invoice?.id}`" />

  <!-- Header actions -->
  <div class="mb-4 flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
    <div>
      <h1 class="text-xl md:text-2xl font-heading font-semibold">Sửa hoá đơn</h1>
      <div class="text-slate-500 dark:text-slate-400 text-sm">
        Mã hoá đơn: <span class="font-medium text-slate-900 dark:text-slate-100">{{ invoice?.code }}</span>
      </div>
    </div>

    <div class="flex flex-wrap items-center gap-2">
      <Link
        :href="route('admin.invoices.show', invoice.id)"
        class="px-3 py-1.5 rounded-lg border border-slate-300 dark:border-slate-600 hover:bg-slate-100 dark:hover:bg-slate-800"
      >
        Quay lại chi tiết
      </Link>

      <Link
        :href="route('admin.invoices.index')"
        class="px-3 py-1.5 rounded-lg border border-slate-300 dark:border-slate-600 hover:bg-slate-100 dark:hover:bg-slate-800"
      >
        ← Danh sách
      </Link>
    </div>
  </div>

  <!-- Card form centered -->
  <div class="max-w-3xl mx-auto">
    <div class="rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 p-4 md:p-6">
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <!-- Code (readonly) -->
        <div>
          <label class="block text-sm font-medium mb-1">Mã hoá đơn</label>
          <InputText v-model="form.code" class="w-full" disabled />
        </div>

        <!-- Trạng thái -->
        <div>
          <label class="block text-sm font-medium mb-1">Trạng thái</label>
          <Select
            v-model="form.status"
            :options="statusOptions"
            optionLabel="label"
            optionValue="value"
            class="w-full"
          />
          <div v-if="form.errors?.status" class="text-red-500 text-xs mt-1">{{ form.errors.status }}</div>
        </div>

        <!-- Branch (readonly) -->
        <div>
          <label class="block text-sm font-medium mb-1">Chi nhánh</label>
          <InputText :value="form.branchName" class="w-full" disabled />
        </div>

        <!-- Student (readonly) -->
        <div>
          <label class="block text-sm font-medium mb-1">Học viên</label>
          <InputText :value="form.studentName" class="w-full" disabled />
        </div>

        <!-- Due date -->
        <div>
          <label class="block text-sm font-medium mb-1">Hạn thanh toán</label>
          <DatePicker
            v-model="form.due_date"
            dateFormat="dd/mm/yy"
            showIcon
            iconDisplay="input"
            class="w-full"
          />
          <div v-if="form.errors?.due_date" class="text-red-500 text-xs mt-1">{{ form.errors.due_date }}</div>
        </div>

        <!-- Note -->
        <div class="md:col-span-2">
          <label class="block text-sm font-medium mb-1">Ghi chú</label>
          <Textarea v-model="form.note" autoResize rows="3" class="w-full" />
          <div v-if="form.errors?.note" class="text-red-500 text-xs mt-1">{{ form.errors.note }}</div>
        </div>
      </div>

      <div class="mt-6 flex items-center justify-end gap-2">
        <Link
          :href="route('admin.invoices.show', invoice.id)"
          class="px-3 py-1.5 rounded-lg border border-slate-300 dark:border-slate-600 hover:bg-slate-100 dark:hover:bg-slate-800"
        >
          Quay lại chi tiết
        </Link>
        <Button label="Lưu thay đổi" icon="pi pi-check" :loading="form.saving" @click="submit" />
      </div>
    </div>
  </div>
</template>
