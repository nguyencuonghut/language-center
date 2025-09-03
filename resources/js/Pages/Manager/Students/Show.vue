<script setup>
import { computed, ref } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import TransferDialog from '@/Components/TransferDialog.vue'

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

defineOptions({ layout: AppLayout })

const props = defineProps({
  student: Object, // {id, code, name, phone, email, active, created_at}
  enrollments: Array, // [{id,class_id,class_code,class_name,start_session_no,enrolled_at,status}]
  invoices: Array, // [{id,total,status,due_date,created_at,invoice_items:[],payments:[]}]
  attendanceSummary: Object, // {present:0,absent:0,late:0,excused:0}
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

// Transfer dialog state
const showTransferDialog = ref(false)
const transferSaving = ref(false)
const availableClasses = ref([])

// Retarget transfer dialog state
const showRetargetDialog = ref(false)
const retargetSaving = ref(false)
const retargetEnrollment = ref(null)

// Check if there are transfer operations available
const hasActiveTransfer = computed(() => {
  const hasTransferred = props.enrollments?.some(e => e.status === 'transferred')
  const hasActive = props.enrollments?.some(e => e.status === 'active')
  return hasTransferred && hasActive
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

const openTransferDialog = async () => {
  // Load available classes when opening dialog
  try {
    const response = await fetch(route('manager.classrooms.search') + '?available_for_transfer=1')
    if (response.ok) {
      const data = await response.json()
      availableClasses.value = data
    }
  } catch (error) {
    console.error('Failed to load available classes:', error)
    // Fallback to empty array
    availableClasses.value = []
  }

  showTransferDialog.value = true
}

const handleTransferSubmit = async (transferData) => {
  transferSaving.value = true

  try {
    router.post(
      route('manager.students.transfer', props.student.id),
      transferData,
      {
        onSuccess: () => {
          showTransferDialog.value = false
        },
        onError: (errors) => {
          console.error('Transfer failed:', errors)
        },
        onFinish: () => {
          transferSaving.value = false
        }
      }
    )
  } catch (error) {
    console.error('Transfer error:', error)
    transferSaving.value = false
  }
}

const handleTransferCancel = () => {
  showTransferDialog.value = false
}

// Revert transfer function
const revertTransfer = async (transferredEnrollment) => {
  if (!confirm('Bạn có chắc chắn muốn hoàn tác việc chuyển lớp này?')) {
    return
  }

  // Find the current active enrollment (the target class we want to remove)
  const activeEnrollment = props.enrollments?.find(e => e.status === 'active')

  if (!activeEnrollment) {
    alert('Không tìm thấy lớp hiện tại để hoàn tác.')
    return
  }

  try {
    router.post(
      route('manager.transfers.revert'),
      {
        student_id: props.student.id,
        to_class_id: activeEnrollment.class_id, // current active class (to be removed)
        from_class_id: transferredEnrollment.class_id // original class (to be restored)
      },
      {
        onSuccess: () => {
          // Page will be refreshed with updated data
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

// Open retarget dialog
const openRetargetDialog = async (transferredEnrollment) => {
  // Find the current active enrollment
  const activeEnrollment = props.enrollments?.find(e => e.status === 'active')

  if (!activeEnrollment) {
    alert('Không tìm thấy lớp hiện tại để chuyển.')
    return
  }

  retargetEnrollment.value = {
    transferredEnrollment,
    activeEnrollment
  }

  // Load available classes when opening dialog
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
        from_class_id: retargetEnrollment.value.transferredEnrollment.class_id, // original class
        old_to_class_id: retargetEnrollment.value.activeEnrollment.class_id, // current wrong target
        new_to_class_id: transferData.to_class_id, // new correct target
        start_session_no: transferData.start_session_no,
        due_date: transferData.effective_date,
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
        @click="openTransferDialog"
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
                <div v-if="data.status === 'transferred' && hasActiveTransfer" class="flex gap-2">
                  <Button
                    label="Hoàn tác"
                    icon="pi pi-undo"
                    size="small"
                    severity="warning"
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
                  <!-- Show transfer button for active enrollments -->
                  <Button
                    label="Chuyển lớp"
                    icon="pi pi-refresh"
                    size="small"
                    severity="info"
                    @click="openTransferDialog"
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
                  :href="route('admin.invoices.show', data.id)"
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
  </Tabs>

  <!-- Transfer Dialog -->
  <TransferDialog
    v-model="showTransferDialog"
    :student="student"
    :fromClass="currentClass"
    :classOptions="availableClasses"
    :defaults="{
      start_session_no: 1,
      effective_date: new Date().toISOString().split('T')[0],
      create_adjustments: true
    }"
    :saving="transferSaving"
    @submit="handleTransferSubmit"
    @cancel="handleTransferCancel"
  />

  <!-- Retarget Transfer Dialog -->
  <TransferDialog
    v-model="showRetargetDialog"
    :student="student"
    :fromClass="retargetEnrollment ? {
      id: retargetEnrollment.activeEnrollment.class_id,
      code: retargetEnrollment.activeEnrollment.class_code,
      name: retargetEnrollment.activeEnrollment.class_name
    } : null"
    :classOptions="availableClasses"
    :defaults="{
      start_session_no: 1,
      effective_date: new Date().toISOString().split('T')[0],
      create_adjustments: true
    }"
    :saving="retargetSaving"
    @submit="handleRetargetSubmit"
    @cancel="handleRetargetCancel"
  />
</template>
