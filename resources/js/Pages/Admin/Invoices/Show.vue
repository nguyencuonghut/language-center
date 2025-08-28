<script setup>
import { reactive, computed, ref } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'

// PrimeVue
import Tag from 'primevue/tag'
import Button from 'primevue/button'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import Dialog from 'primevue/dialog'
import Select from 'primevue/select'
import DatePicker from 'primevue/datepicker'
import InputNumber from 'primevue/inputnumber'
import InputText from 'primevue/inputtext'

defineOptions({ layout: AppLayout })

const props = defineProps({
  invoice: Object, // {id, branch, student, class, total, status, due_date, created_at, note, items?:[], payments?:[]}
  can: Object      // {update?:bool, delete?:bool}
})

/** Hiển thị dd/mm/yyyy (chỉ hiển thị – không đổi timezone) */
function toDdMmYyyy(d) {
  if (!d) return '—'
  const dt = new Date(String(d).replace(' ', 'T'))
  if (isNaN(dt.getTime())) {
    const [y,m,day] = String(d).split('-')
    if (y && m && day) return `${day.padStart(2,'0')}/${m.padStart(2,'0')}/${y}`
    return String(d)
  }
  const dd = String(dt.getDate()).padStart(2,'0')
  const mm = String(dt.getMonth()+1).padStart(2,'0')
  const yy = dt.getFullYear()
  return `${dd}/${mm}/${yy}`
}

/** Chuẩn hoá Date → YYYY-MM-DD (tránh lệch timezone) */
function toYmdLocal(d) {
  if (!d) return null
  const dt = new Date(d)
  const y = dt.getFullYear()
  const m = String(dt.getMonth()+1).padStart(2,'0')
  const day = String(dt.getDate()).padStart(2,'0')
  return `${y}-${m}-${day}`
}

const statusSeverity = (s) => {
  switch (s) {
    case 'paid': return 'success'
    case 'partial': return 'warning'
    case 'refunded': return 'info'
    default: return 'danger' // unpaid
  }
}

const itemsValue = computed(() => props.invoice?.items ?? [])
const paymentsValue = computed(() => props.invoice?.payments ?? [])

function destroyInvoice() {
  if (!confirm('Xác nhận xoá hoá đơn này?')) return
  router.delete(route('admin.invoices.destroy', props.invoice.id), { preserveScroll: true })
}

/* --------- Dialog: Tạo thanh toán --------- */
const showPayment = ref(false)
const paymentForm = reactive({
  method: 'cash',
  paid_at: new Date(), // mặc định hôm nay
  amount: null,
  ref_no: '',
  errors: {},
  saving: false
})

const methodOptions = [
  { label: 'Tiền mặt', value: 'cash' },
  { label: 'Chuyển khoản', value: 'bank' },
  { label: 'MoMo', value: 'momo' },
  { label: 'ZaloPay', value: 'zalopay' }
]

function openPayment() {
  paymentForm.method = 'cash'
  paymentForm.paid_at = new Date()
  paymentForm.amount = null
  paymentForm.ref_no = ''
  paymentForm.errors = {}
  showPayment.value = true
}

function savePayment() {
  paymentForm.errors = {}
  if (!paymentForm.amount || Number(paymentForm.amount) <= 0) {
    paymentForm.errors.amount = 'Vui lòng nhập số tiền > 0'
  }
  if (!paymentForm.paid_at) {
    paymentForm.errors.paid_at = 'Vui lòng chọn ngày thanh toán'
  }
  if (Object.keys(paymentForm.errors).length) return

  paymentForm.saving = true
  router.post(route('admin.invoices.payments.store', props.invoice.id), {
    method: paymentForm.method,
    paid_at: toYmdLocal(paymentForm.paid_at),
    amount: Number(paymentForm.amount),
    ref_no: paymentForm.ref_no || null
  }, {
    preserveScroll: true,
    onFinish: () => { paymentForm.saving = false },
    onSuccess: () => { showPayment.value = false }
  })
}
</script>

<template>
  <Head :title="`Hóa đơn #${invoice.id}`" />

  <!-- Header -->
  <div class="mb-3 flex flex-col md:flex-row md:items-center md:justify-between gap-3">
    <div>
      <h1 class="text-xl md:text-2xl font-heading font-semibold">Hoá đơn #{{ invoice.id }}</h1>
      <div class="text-slate-500 dark:text-slate-400 text-sm">
        Trạng thái:
        <Tag :value="invoice.status" :severity="statusSeverity(invoice.status)" class="ml-1" />
      </div>
    </div>

    <div class="flex flex-wrap items-center gap-2">
      <Button label="Thêm thanh toán" icon="pi pi-plus-circle" @click="openPayment" />
      <Link
        :href="route('admin.invoices.edit', invoice.id)"
        class="px-3 py-1.5 rounded-lg border border-emerald-300 text-emerald-700 hover:bg-emerald-50
               dark:border-emerald-700 dark:text-emerald-300 dark:hover:bg-emerald-900/20"
        v-if="can?.update !== false"
      >
        <i class="pi pi-pencil mr-1"></i> Sửa
      </Link>
      <button
        class="px-3 py-1.5 rounded-lg border border-red-300 text-red-600 hover:bg-red-50
               dark:border-red-700 dark:text-red-300 dark:hover:bg-red-900/20"
        @click="destroyInvoice"
        v-if="can?.delete !== false"
      >
        <i class="pi pi-trash mr-1"></i> Xoá
      </button>
      <Link :href="route('admin.invoices.index')" class="px-3 py-1.5 rounded-lg border border-slate-300 dark:border-slate-600 hover:bg-slate-100 dark:hover:bg-slate-800">
        ← Danh sách
      </Link>
    </div>
  </div>

  <!-- Summary -->
  <div class="grid gap-4 md:grid-cols-2 mb-4">
    <div class="rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 p-4">
      <div class="text-sm text-slate-500 mb-1">Học viên</div>
      <div class="font-medium">{{ invoice.student?.code }} · {{ invoice.student?.name }}</div>

      <div class="mt-3 text-sm text-slate-500 mb-1">Chi nhánh</div>
      <div class="font-medium">{{ invoice.branch?.name ?? '—' }}</div>

      <div class="mt-3 text-sm text-slate-500 mb-1">Lớp (nếu có)</div>
      <div class="font-medium">{{ invoice.class?.code }} · {{ invoice.class?.name }}</div>
    </div>

    <div class="rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 p-4">
      <div class="text-sm text-slate-500 mb-1">Tổng tiền</div>
      <div class="font-semibold text-lg">
        {{ new Intl.NumberFormat('vi-VN',{style:'currency',currency:'VND'}).format(invoice.total||0) }}
      </div>

      <div class="mt-3 text-sm text-slate-500 mb-1">Hạn thanh toán</div>
      <div class="font-medium">{{ toDdMmYyyy(invoice.due_date) }}</div>

      <div class="mt-3 text-sm text-slate-500 mb-1">Ngày tạo</div>
      <div class="font-medium">{{ toDdMmYyyy(invoice.created_at) }}</div>
    </div>
  </div>

  <!-- Items -->
  <div class="rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 p-3 mb-4">
    <div class="flex items-center justify-between mb-2">
      <div class="font-medium">Chi tiết</div>
    </div>
    <DataTable :value="itemsValue" dataKey="id" size="small" responsiveLayout="scroll">
      <Column field="type" header="Loại" style="width: 160px" />
      <Column field="description" header="Mô tả" />
      <Column field="qty" header="SL" style="width: 80px" />
      <Column field="unit_price" header="Đơn giá" style="width: 160px">
        <template #body="{ data }">
          {{ new Intl.NumberFormat('vi-VN',{style:'currency',currency:'VND'}).format(data.unit_price||0) }}
        </template>
      </Column>
      <Column field="amount" header="Thành tiền" style="width: 180px">
        <template #body="{ data }">
          {{ new Intl.NumberFormat('vi-VN',{style:'currency',currency:'VND'}).format(data.amount||0) }}
        </template>
      </Column>
      <template #empty>
        <div class="p-4 text-center text-slate-500 dark:text-slate-400">Chưa có mục chi tiết.</div>
      </template>
    </DataTable>
  </div>

  <!-- Payments -->
  <div class="rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 p-3">
    <div class="flex items-center justify-between mb-2">
      <div class="font-medium">Thanh toán</div>
    </div>
    <DataTable :value="paymentsValue" dataKey="id" size="small" responsiveLayout="scroll">
      <Column field="method" header="Phương thức" style="width: 160px" />
      <Column field="paid_at" header="Ngày thanh toán" style="width: 180px">
        <template #body="{ data }">{{ toDdMmYyyy(data.paid_at) }}</template>
      </Column>
      <Column field="amount" header="Số tiền" style="width: 180px">
        <template #body="{ data }">
          {{ new Intl.NumberFormat('vi-VN',{style:'currency',currency:'VND'}).format(data.amount||0) }}
        </template>
      </Column>
      <Column field="ref_no" header="Mã tham chiếu" style="width: 200px" />
      <template #empty>
        <div class="p-4 text-center text-slate-500 dark:text-slate-400">Chưa có thanh toán.</div>
      </template>
    </DataTable>
  </div>

  <!-- Dialog: Tạo thanh toán -->
  <Dialog v-model:visible="showPayment" modal header="Thêm thanh toán" :style="{ width: '520px' }">
    <div class="flex flex-col gap-4">
      <div>
        <label class="block text-sm font-medium mb-1">Phương thức</label>
        <Select v-model="paymentForm.method" :options="methodOptions" optionLabel="label" optionValue="value" class="w-full" />
      </div>

      <div>
        <label class="block text-sm font-medium mb-1">Ngày thanh toán</label>
        <DatePicker v-model="paymentForm.paid_at" dateFormat="dd/mm/yy" showIcon iconDisplay="input" class="w-full" />
        <div v-if="paymentForm.errors?.paid_at" class="text-red-500 text-xs mt-1">{{ paymentForm.errors.paid_at }}</div>
      </div>

      <div>
        <label class="block text-sm font-medium mb-1">Số tiền</label>
        <InputNumber v-model="paymentForm.amount" class="w-full" mode="currency" currency="VND" locale="vi-VN" :min="0" />
        <div v-if="paymentForm.errors?.amount" class="text-red-500 text-xs mt-1">{{ paymentForm.errors.amount }}</div>
      </div>

      <div>
        <label class="block text-sm font-medium mb-1">Mã tham chiếu (tuỳ chọn)</label>
        <InputText v-model="paymentForm.ref_no" class="w-full" placeholder="VD: UTR/Ref số ..." />
      </div>
    </div>

    <template #footer>
      <Button label="Huỷ" icon="pi pi-times" text @click="showPayment=false" />
      <Button label="Lưu" icon="pi pi-check" :loading="paymentForm.saving" @click="savePayment" autofocus />
    </template>
  </Dialog>
</template>
