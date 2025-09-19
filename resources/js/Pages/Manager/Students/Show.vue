<script setup>
import { computed, ref } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import TransferFormModal from '@/Components/TransferFormModal.vue'

// PrimeVue
import Tag from 'primevue/tag'
import Tabs from 'primevue/tabs'
import TabList from 'primevue/tablist'
import Tab from 'primevue/tab'
import TabPanels from 'primevue/tabpanels'
import TabPanel from 'primevue/tabpanel'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import Button from 'primevue/button'
import Dialog from 'primevue/dialog'
import Textarea from 'primevue/textarea'
import Select from 'primevue/select'
import InputNumber from 'primevue/inputnumber'
import DatePicker from 'primevue/datepicker'

defineOptions({ layout: AppLayout })

const props = defineProps({
  student: Object, // {id, code, name, phone, email, active, created_at}
  enrollments: Array, // [{id,class_id,class_code,class_name,start_session_no,enrolled_at,status}]
  invoices: Array, // [{id,total,status,due_date,created_at,invoice_items:[],payments:[]}]
  attendanceSummary: Object, // {present:0,absent:0,late:0,excused:0}
  ledger: Object, // 👈 thêm
})

function toDdMmYyyy(d){
  if (!d) return '—'
  const dt = new Date(String(d).replace(' ', 'T'))
  if (isNaN(dt.getTime())) {
    const parts = String(d).split('-') // 2025-08-31
    if (parts.length === 3) return `${parts[2]}/${parts[1]}/${parts[0]}`
    return String(d)
  }
  const dd = String(dt.getDate()).padStart(2,'0')
  const mm = String(dt.getMonth()+1).padStart(2,'0')
  const yy = dt.getFullYear()
  return `${dd}/${mm}/${yy}`
}
const statusSeverity = s => s==='paid' ? 'success' : s==='partial' ? 'warning' : s==='refunded' ? 'info' : 'danger'
const activeSeverity = a => a ? 'success' : 'danger'

const totalPaid = (inv) => (inv.payments||[]).reduce((sum,p)=>sum + (Number(p.amount)||0), 0)
const balance  = (inv) => Math.max(0, (Number(inv.total)||0) - totalPaid(inv))

const att = computed(()=> props.attendanceSummary || {present:0,absent:0,late:0,excused:0})

// Transfer modal state
const showTransferModal = ref(false)
const availableClasses = ref([])

// Retarget transfer dialog state
const showRetargetDialog = ref(false)
const retargetSaving = ref(false)
const retargetEnrollment = ref(null)

// Revert transfer dialog state
const showRevertDialog = ref(false)
const revertData = ref({
  reason: '',
  notes: '',
  activeEnrollment: null,
  transfer: null
})

// Check if there are transfer operations available
const hasActiveTransfer = computed(() => {
  // Check if any enrollment has an active transfer that can be reverted
  return props.enrollments?.some(e => e.active_transfer?.can_revert)
})

// Get current active class from enrollments
const currentClass = computed(() => {
  const activeEnrollment = props.enrollments?.find(e => e.status === 'active')
  if (!activeEnrollment) return null

  return {
    id: activeEnrollment.class_id,
    code: activeEnrollment.class_code,
    name: activeEnrollment.class_name
  }
})

const openTransferModal = async () => {
  // Load available classes when opening modal
  try {
    const response = await fetch(route('manager.classrooms.search') + '?available_for_transfer=1')
    if (response.ok) {
      const data = await response.json()
      availableClasses.value = data
    }
  } catch (error) {
    console.error('Failed to load available classes:', error)
    availableClasses.value = []
  }

  showTransferModal.value = true
}

const handleTransferSuccess = () => {
  // Reload page to show updated data
  router.reload({ only: ['student', 'enrollments'] })
}

const handleTransferCancel = () => {
  showTransferModal.value = false
}

// Revert transfer function
const revertTransfer = (activeEnrollment) => {
  // This should only be called for enrollments with active_transfer data
  if (!activeEnrollment.active_transfer) {
    alert('Không có transfer record để hoàn tác.')
    return
  }

  // Set up revert data and show dialog
  revertData.value = {
    reason: '',
    notes: '',
    activeEnrollment: activeEnrollment,
    transfer: activeEnrollment.active_transfer
  }
  showRevertDialog.value = true
}

// Confirm revert with reason and notes
const confirmRevert = async () => {
  if (!revertData.value.reason.trim()) {
    return // Validation handled by dialog
  }

  if (!revertData.value.transfer) {
    alert('Không có transfer record để hoàn tác.')
    return
  }

  try {
    router.post(
      route('manager.transfers.revert'),
      {
        student_id: props.student.id,
        to_class_id: revertData.value.transfer.to_class_id, // current active class (to be removed)
        from_class_id: revertData.value.transfer.from_class_id, // original class (to be restored)
        reason: revertData.value.reason,
        notes: revertData.value.notes,
      },
      {
        onSuccess: () => {
          showRevertDialog.value = false
          revertData.value = { reason: '', notes: '', activeEnrollment: null, transfer: null }
        },
        onError: (errors) => {
          console.error('Revert transfer failed:', errors)
        }
      }
    )
  } catch (error) {
    console.error('Revert transfer error:', error)
  }
}

// Cancel revert dialog
const cancelRevert = () => {
  showRevertDialog.value = false
  revertData.value = { reason: '', notes: '', activeEnrollment: null, transfer: null }
}

// Retarget dialog data
const retargetData = ref({
  to_class_id: null,
  start_session_no: 1,
  amount: 0,
  due_date: new Date(new Date().getTime() + 7 * 24 * 60 * 60 * 1000), // 7 days from now
  note: ''
})

// Open retarget dialog
const openRetargetDialog = async (activeEnrollment) => {
  // This should only be called for enrollments with active_transfer data
  if (!activeEnrollment.active_transfer) {
    alert('Không có transfer record để sửa hướng.')
    return
  }

  retargetEnrollment.value = {
    activeEnrollment: activeEnrollment,
    transfer: activeEnrollment.active_transfer
  }

  // Load available classes when opening dialog
  try {
    const response = await fetch(route('manager.classrooms.search') + '?available_for_transfer=1')
    if (response.ok) {
      const data = await response.json()
      availableClasses.value = data.filter(cls => cls.id !== activeEnrollment.class_id) // Exclude current class
    }
  } catch (error) {
    console.error('Failed to load available classes:', error)
    availableClasses.value = []
  }

  showRetargetDialog.value = true
}

// Handle retarget transfer submit
const handleRetargetSubmit = async (transferData) => {
  retargetSaving.value = true

  try {
    router.post(
      route('manager.transfers.retarget'),
      {
        student_id: props.student.id,
        from_class_id: retargetEnrollment.value.transfer.from_class_id, // original class
        old_to_class_id: retargetEnrollment.value.transfer.to_class_id, // current target (wrong)
        new_to_class_id: transferData.to_class_id, // new correct target
        start_session_no: transferData.start_session_no,
        amount: transferData.amount,
        due_date: transferData.due_date,
        note: transferData.note
      },
      {
        onSuccess: () => {
          showRetargetDialog.value = false
          retargetEnrollment.value = null
        },
        onError: (errors) => {
          console.error('Retarget transfer failed:', errors)
        },
        onFinish: () => {
          retargetSaving.value = false
        }
      }
    )
  } catch (error) {
    console.error('Retarget transfer error:', error)
    retargetSaving.value = false
  }
}

// Handle retarget cancel
const handleRetargetCancel = () => {
  showRetargetDialog.value = false
  retargetEnrollment.value = null
  retargetData.value = {
    to_class_id: null,
    start_session_no: 1,
    amount: 0,
    due_date: new Date(new Date().getTime() + 7 * 24 * 60 * 60 * 1000),
    note: ''
  }
}

// Confirm retarget
const confirmRetarget = () => {
  if (!retargetData.value.to_class_id) {
    alert('Vui lòng chọn lớp đích mới.')
    return
  }

  // Format due_date for submission
  const submitData = {
    ...retargetData.value,
    due_date: retargetData.value.due_date ?
      retargetData.value.due_date.toISOString().split('T')[0] :
      null
  }

  handleRetargetSubmit(submitData)
}
</script>

<template>
  <Head :title="`Học viên ${student?.name || ''}`" />

  <!-- Header -->
  <div class="mb-3 flex flex-col md:flex-row md:items-center md:justify-between gap-3">
    <div>
      <h1 class="text-xl md:text-2xl font-heading font-semibold">
        {{ student?.code }} · {{ student?.name }}
      </h1>
      <div class="text-slate-500 dark:text-slate-400 text-sm flex flex-wrap items-center gap-3 mt-1">
        <span>Điện thoại: <span class="font-medium text-slate-900 dark:text-slate-100">{{ student?.phone || '—' }}</span></span>
        <span>Email: <span class="font-medium text-slate-900 dark:text-slate-100">{{ student?.email || '—' }}</span></span>
        <span class="flex items-center gap-1">Trạng thái:
          <Tag :value="student?.active ? 'Đang hoạt động' : 'Ngừng hoạt động'" :severity="activeSeverity(student?.active)" />
        </span>
      </div>
    </div>

    <div class="flex flex-wrap items-center gap-2">
      <button
        @click="openTransferModal"
        class="px-3 py-1.5 rounded-lg border border-blue-300 text-blue-700 hover:bg-blue-50
               dark:border-blue-700 dark:text-blue-300 dark:hover:bg-blue-900/20"
        :disabled="!currentClass"
      >
        <i class="pi pi-refresh mr-1"></i> Chuyển lớp
      </button>
      <Link
        :href="route('manager.students.edit', student.id)"
        class="px-3 py-1.5 rounded-lg border border-emerald-300 text-emerald-700 hover:bg-emerald-50
               dark:border-emerald-700 dark:text-emerald-300 dark:hover:bg-emerald-900/20"
      >
        <i class="pi pi-pencil mr-1"></i> Sửa
      </Link>
      <Link
        :href="route('manager.students.index')"
        class="px-3 py-1.5 rounded-lg border border-slate-300 dark:border-slate-600 hover:bg-slate-100 dark:hover:bg-slate-800"
      >
        ← Danh sách
      </Link>
    </div>
  </div>

  <!-- KPI -->
  <div class="grid gap-3 md:grid-cols-4 mb-4">
    <div class="rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 p-3">
      <div class="text-xs text-slate-500">Có mặt</div>
      <div class="text-xl font-semibold">{{ att.present || 0 }}</div>
    </div>
    <div class="rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 p-3">
      <div class="text-xs text-slate-500">Vắng</div>
      <div class="text-xl font-semibold">{{ att.absent || 0 }}</div>
    </div>
    <div class="rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 p-3">
      <div class="text-xs text-slate-500">Đi muộn</div>
      <div class="text-xl font-semibold">{{ att.late || 0 }}</div>
    </div>
    <div class="rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 p-3">
      <div class="text-xs text-slate-500">Có phép</div>
      <div class="text-xl font-semibold">{{ att.excused || 0 }}</div>
    </div>
  </div>

  <Tabs value="0">
    <TabList>
      <Tab value="0">Ghi danh</Tab>
      <Tab value="1">Hoá đơn & thanh toán</Tab>
      <Tab value="2">Điểm danh (tổng quan)</Tab>
      <Tab value="3">Công nợ</Tab>
    </TabList>

    <TabPanels>
      <!-- ENROLLMENTS -->
      <TabPanel value="0">
        <div class="rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 p-2">
          <DataTable :value="enrollments || []" dataKey="id" size="small" responsiveLayout="scroll">
            <Column field="class_code" header="Mã lớp" style="width: 140px" />
            <Column field="class_name" header="Tên lớp" />
            <Column field="start_session_no" header="Bắt đầu từ buổi" style="width: 160px" />
            <Column field="enrolled_at" header="Ngày ghi danh" style="width: 160px">
              <template #body="{ data }">{{ toDdMmYyyy(data.enrolled_at) }}</template>
            </Column>
            <Column field="status" header="Trạng thái" style="width: 140px">
              <template #body="{ data }">
                <Tag
                  :value="data.status === 'active' ? 'Đang học' :
                          data.status === 'transferred' ? 'Đã chuyển' :
                          data.status === 'completed' ? 'Hoàn thành' :
                          data.status === 'cancelled' ? 'Hủy' : data.status"
                  :severity="data.status === 'active' ? 'success' :
                            data.status === 'transferred' ? 'warning' :
                            data.status === 'completed' ? 'info' :
                            data.status === 'cancelled' ? 'danger' : 'secondary'"
                />
              </template>
            </Column>
            <Column header="Thao tác" style="width: 280px">
              <template #body="{ data }">
                <div v-if="data.status === 'active' && data.active_transfer?.can_revert" class="flex gap-2">
                  <Button
                    label="Hoàn tác"
                    icon="pi pi-undo"
                    size="small"
                    severity="warn"
                    @click="revertTransfer(data)"
                  />
                  <Button
                    label="Sửa chuyển lớp"
                    icon="pi pi-pencil"
                    size="small"
                    severity="info"
                    @click="openRetargetDialog(data)"
                  />
                </div>
                <div v-else-if="data.status === 'active'" class="flex gap-2">
                  <!-- Show transfer button for active enrollments without active transfers -->
                  <Button
                    label="Chuyển lớp"
                    icon="pi pi-refresh"
                    size="small"
                    severity="info"
                    @click="openTransferModal"
                    :disabled="!currentClass"
                  />
                </div>
              </template>
            </Column>
            <template #empty>
              <div class="p-4 text-center text-slate-500 dark:text-slate-400">Chưa có ghi danh.</div>
            </template>
          </DataTable>
        </div>
      </TabPanel>

      <!-- INVOICES -->
      <TabPanel value="1">
        <div class="rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 p-2">
          <DataTable :value="invoices || []" dataKey="id" size="small" responsiveLayout="scroll">
            <Column field="id" header="#" style="width: 80px" />
            <Column field="created_at" header="Ngày tạo" style="width: 160px">
              <template #body="{ data }">{{ toDdMmYyyy(data.created_at) }}</template>
            </Column>
            <Column field="due_date" header="Hạn TT" style="width: 160px">
              <template #body="{ data }">{{ toDdMmYyyy(data.due_date) }}</template>
            </Column>
            <Column field="total" header="Tổng tiền" style="width: 160px">
              <template #body="{ data }">
                {{ new Intl.NumberFormat('vi-VN',{style:'currency',currency:'VND'}).format(data.total||0) }}
              </template>
            </Column>
            <Column header="Đã thu" style="width: 160px">
              <template #body="{ data }">
                {{ new Intl.NumberFormat('vi-VN',{style:'currency',currency:'VND'}).format(totalPaid(data)) }}
              </template>
            </Column>
            <Column header="Còn nợ" style="width: 160px">
              <template #body="{ data }">
                {{ new Intl.NumberFormat('vi-VN',{style:'currency',currency:'VND'}).format(balance(data)) }}
              </template>
            </Column>
            <Column field="status" header="Trạng thái" style="width: 140px">
              <template #body="{ data }">
                <Tag :value="data.status" :severity="statusSeverity(data.status)" />
              </template>
            </Column>
            <Column header="" style="width: 160px">
              <template #body="{ data }">
                <Link
                  :href="route('manager.invoices.show', data.id)"
                  class="px-3 py-1.5 rounded-lg border border-slate-300 dark:border-slate-600 hover:bg-slate-100 dark:hover:bg-slate-800"
                >
                  Chi tiết
                </Link>
              </template>
            </Column>

            <template #empty>
              <div class="p-4 text-center text-slate-500 dark:text-slate-400">Chưa có hoá đơn.</div>
            </template>
          </DataTable>
        </div>
      </TabPanel>

      <!-- ATTENDANCE (summary table nhỏ, chi tiết bạn có thể mở trang lớp) -->
      <TabPanel value="2">
        <div class="rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 p-4">
          <ul class="text-sm leading-7">
            <li> Có mặt: <b>{{ att.present || 0 }}</b> buổi</li>
            <li> Vắng: <b>{{ att.absent || 0 }}</b> buổi</li>
            <li> Đi muộn: <b>{{ att.late || 0 }}</b> buổi</li>
            <li> Có phép: <b>{{ att.excused || 0 }}</b> buổi</li>
          </ul>
          <div class="mt-3 text-slate-500 text-sm">
            Chi tiết buổi học xem tại: Lớp → Buổi học, lọc theo học viên này.
          </div>
        </div>
      </TabPanel>
    </TabPanels>

    <!-- LEDGER / CÔNG NỢ -->
    <TabPanel value="3">
    <div class="rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 p-4 space-y-4">
        <div class="text-lg font-semibold">
        Số dư hiện tại:
        <span :class="props.ledger.balance > 0 ? 'text-red-600' : 'text-green-600'">
            {{ new Intl.NumberFormat('vi-VN',{style:'currency',currency:'VND'}).format(props.ledger.balance) }}
        </span>
        </div>

        <DataTable :value="props.ledger.recent || []" size="small" responsiveLayout="scroll">
        <Column field="entry_date" header="Ngày" style="width: 140px" />
        <Column field="type" header="Loại" style="width: 140px" />
        <Column field="note" header="Ghi chú" />
        <Column field="debit" header="Nợ" style="width: 140px">
            <template #body="{ data }">
            <span v-if="data.debit > 0">
                {{ new Intl.NumberFormat('vi-VN',{style:'currency',currency:'VND'}).format(data.debit) }}
            </span>
            <span v-else>—</span>
            </template>
        </Column>
        <Column field="credit" header="Có" style="width: 140px">
            <template #body="{ data }">
            <span v-if="data.credit > 0">
                {{ new Intl.NumberFormat('vi-VN',{style:'currency',currency:'VND'}).format(data.credit) }}
            </span>
            <span v-else>—</span>
            </template>
        </Column>

        <template #empty>
            <div class="p-4 text-center text-slate-500 dark:text-slate-400">Chưa có phát sinh công nợ.</div>
        </template>
        </DataTable>
    </div>
    </TabPanel>

  </Tabs>

  <!-- Transfer Form Modal -->
  <TransferFormModal
    v-model:visible="showTransferModal"
    :student="student"
    :classrooms="availableClasses"
    @success="handleTransferSuccess"
  />

  <!-- Revert Transfer Dialog -->
  <Dialog
    v-model:visible="showRevertDialog"
    modal
    header="Hoàn tác chuyển lớp"
    :style="{width: '500px'}"
    class="revert-dialog"
  >
    <div class="space-y-4">
      <p class="text-slate-600 dark:text-slate-400 mb-4">
        Bạn có chắc chắn muốn hoàn tác việc chuyển lớp này không?
        Học viên sẽ được chuyển về lớp cũ.
      </p>

      <div class="space-y-3">
        <div>
          <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">
            Lý do hoàn tác <span class="text-red-500">*</span>
          </label>
          <Textarea
            v-model="revertData.reason"
            placeholder="Nhập lý do hoàn tác..."
            rows="3"
            class="w-full"
            :class="{'border-red-500': !revertData.reason.trim()}"
          />
        </div>

        <div>
          <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">
            Ghi chú thêm
          </label>
          <Textarea
            v-model="revertData.notes"
            placeholder="Ghi chú bổ sung (tùy chọn)..."
            rows="2"
            class="w-full"
          />
        </div>
      </div>
    </div>

    <template #footer>
      <div class="flex justify-end gap-2">
        <Button
          label="Hủy"
          severity="secondary"
          @click="cancelRevert"
          class="px-4 py-2"
        />
        <Button
          label="Xác nhận hoàn tác"
          severity="danger"
          @click="confirmRevert"
          :disabled="!revertData.reason.trim()"
          class="px-4 py-2"
        />
      </div>
    </template>
  </Dialog>

  <!-- Retarget Transfer Dialog -->
  <Dialog
    v-model:visible="showRetargetDialog"
    modal
    header="Sửa hướng chuyển lớp"
    :style="{width: '600px'}"
    class="retarget-dialog"
  >
    <div class="space-y-4">
      <div class="bg-blue-50 dark:bg-blue-900/20 p-4 rounded-lg mb-4">
        <h4 class="font-medium mb-2">Thông tin chuyển lớp hiện tại:</h4>
        <div class="text-sm space-y-1">
          <div><strong>Từ lớp:</strong> {{ retargetEnrollment?.transfer?.from_class_code }} - {{ retargetEnrollment?.transfer?.from_class_name || 'Lớp gốc' }}</div>
          <div><strong>Đến lớp hiện tại:</strong> {{ retargetEnrollment?.activeEnrollment?.class_code }} - {{ retargetEnrollment?.activeEnrollment?.class_name }}</div>
        </div>
      </div>

      <div class="space-y-4">
        <div>
          <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">
            Lớp đích mới <span class="text-red-500">*</span>
          </label>
          <Select
            v-model="retargetData.to_class_id"
            :options="availableClasses"
            optionLabel="name"
            optionValue="id"
            placeholder="Chọn lớp đích mới..."
            class="w-full"
            :class="{'border-red-500': !retargetData.to_class_id}"
          >
            <template #option="slotProps">
              <div class="flex items-center">
                <span class="font-medium">{{ slotProps.option.code }}</span>
                <span class="ml-2 text-slate-600">{{ slotProps.option.name }}</span>
              </div>
            </template>
          </Select>
        </div>

        <div>
          <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">
            Bắt đầu từ buổi
          </label>
          <InputNumber
            v-model="retargetData.start_session_no"
            :min="1"
            :max="100"
            class="w-full"
            placeholder="Nhập số buổi..."
          />
        </div>

        <div>
          <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">
            Phí sửa đổi (VND)
          </label>
          <InputNumber
            v-model="retargetData.amount"
            :min="0"
            mode="currency"
            currency="VND"
            locale="vi-VN"
            class="w-full"
            placeholder="Nhập phí sửa đổi..."
          />
        </div>

        <div>
          <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">
            Hạn thanh toán
          </label>
          <DatePicker
            v-model="retargetData.due_date"
            dateFormat="dd/mm/yy"
            placeholder="dd/mm/yyyy"
            class="w-full"
          />
        </div>

        <div>
          <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">
            Ghi chú
          </label>
          <Textarea
            v-model="retargetData.note"
            placeholder="Ghi chú lý do sửa hướng chuyển lớp..."
            rows="3"
            class="w-full"
          />
        </div>
      </div>
    </div>

    <template #footer>
      <div class="flex justify-end gap-2">
        <Button
          label="Hủy"
          severity="secondary"
          @click="handleRetargetCancel"
          class="px-4 py-2"
        />
        <Button
          label="Xác nhận sửa hướng"
          severity="warn"
          @click="confirmRetarget"
          :disabled="!retargetData.to_class_id || retargetSaving"
          :loading="retargetSaving"
          class="px-4 py-2"
        />
      </div>
    </template>
  </Dialog>
</template>

<script>
// Register components
export default {
  components: {
    TransferFormModal
  }
}
</script>
