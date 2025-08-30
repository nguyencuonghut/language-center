<script setup>
import { computed } from 'vue'
import { Head, Link } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'

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
            <Column field="status" header="Trạng thái" style="width: 140px" />
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
</template>
