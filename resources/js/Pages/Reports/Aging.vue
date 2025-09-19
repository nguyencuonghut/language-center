<template>
  <Head title="Báo cáo Công nợ (Aging)" />
  <AppLayout>
    <div class="p-3 md:p-5 space-y-6">
      <!-- Page header -->
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
          <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
            Báo cáo Công nợ (Aging)
          </h1>
          <p class="text-gray-600 dark:text-gray-400">
            Phân nhóm tuổi nợ và số dư công nợ theo học viên
          </p>
        </div>
      </div>

      <!-- Filter bar -->
      <FilterBar
        :filters="filters"
        :filter-options="filterOptions"
        :show-branch-filter="true"
        :show-date-range="true"
        :loading="loading"
        @apply="applyFilters"
        @reset="resetFilters"
        @export="exportCSV"
      />

      <!-- KPI Cards -->
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
        <KPICard
          label="Phát sinh phải thu (debit)"
          :value="kpi.total_debit"
          icon="pi pi-download"
          color="blue"
          format="currency"
        />
        <KPICard
          label="Đã thu (credit)"
          :value="kpi.total_credit"
          icon="pi pi-upload"
          color="green"
          format="currency"
        />
        <KPICard
          label="Tổng còn nợ"
          :value="kpi.total_outstanding"
          icon="pi pi-exclamation-triangle"
          color="red"
          format="currency"
        />
        <KPICard
          label="Tổng nộp dư"
          :value="kpi.total_overpaid"
          icon="pi pi-check-circle"
          color="green"
          format="currency"
        />
      </div>

      <!-- Charts -->
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Aging by bucket -->
        <Card>
          <template #title>Phân bổ công nợ theo nhóm tuổi</template>
          <template #content>
            <div class="h-80">
              <Chart
                type="doughnut"
                :data="agingPieData"
                :options="pieChartOptions"
                class="h-full"
              />
            </div>
          </template>
        </Card>

        <!-- Debit vs Credit -->
        <Card>
          <template #title>Phát sinh phải thu vs. Đã thu (khoảng lọc)</template>
          <template #content>
            <div class="h-80">
              <Chart
                type="bar"
                :data="debitCreditBarData"
                :options="barChartOptions"
                class="h-full"
              />
            </div>
          </template>
        </Card>
      </div>

      <!-- Detailed table -->
      <Card>
        <template #title>
          <div class="flex items-center justify-between">
            <span>Công nợ theo học viên</span>
            <div class="flex items-center gap-2">
              <IconField iconPosition="left">
                <InputIcon class="pi pi-search" />
                <InputText
                  v-model="searchQuery"
                  placeholder="Tìm mã / tên học viên..."
                  size="small"
                />
              </IconField>
            </div>
          </div>
        </template>
        <template #content>
          <DataTable
            :value="filteredRows"
            :paginator="true"
            :rows="20"
            :rows-per-page-options="[20, 50, 100]"
            paginator-template="FirstPageLink PrevPageLink PageLinks NextPageLink LastPageLink CurrentPageReport RowsPerPageDropdown"
            current-page-report-template="Hiển thị {first} đến {last} của {totalRecords} bản ghi"
            class="p-datatable-sm"
            :loading="loading"
          >
            <Column field="student_code" header="Mã HV" sortable style="width:120px" />
            <Column field="student_name" header="Học viên" sortable />
            <Column field="balance" header="Số dư" sortable style="width:160px">
              <template #body="{ data }">
                <span :class="data.balance > 0 ? 'text-red-600' : 'text-green-600'">
                  {{ formatCurrency(data.balance) }}
                </span>
              </template>
            </Column>
            <Column field="bucket" header="Tuổi nợ" sortable style="width:200px">
              <template #body="{ data }">
                <Tag :value="bucketLabel(data.bucket)" :severity="bucketSeverity(data.bucket)" />
              </template>
            </Column>
            <Column field="days" header="Số ngày" sortable style="width:100px" />
            <Column header="Chi tiết" style="width:150px">
              <template #body="{ data }">
                <Link :href="route('manager.students.show', data.student_id)"
                    class="px-3 py-1.5 rounded border border-emerald-300 hover:bg-emerald-50
                        dark:border-emerald-700 dark:hover:bg-emerald-900/20">
                  Xem học viên
                </Link>
              </template>
            </Column>

            <template #empty>
              <div class="text-center py-8">
                <i class="pi pi-clock text-4xl text-gray-400 mb-4"></i>
                <p class="text-gray-500">Chưa có công nợ trong khoảng lọc</p>
                <p class="text-sm text-gray-400">Thử đổi chi nhánh hoặc khoảng thời gian</p>
              </div>
            </template>
          </DataTable>
        </template>
      </Card>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, computed } from 'vue'
import { router, Link } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import FilterBar from '@/Components/Reports/FilterBar.vue'
import KPICard from '@/Components/Reports/KPICard.vue'
import Card from 'primevue/card'
import Chart from 'primevue/chart'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import Tag from 'primevue/tag'
import InputText from 'primevue/inputtext'
import IconField from 'primevue/iconfield'
import InputIcon from 'primevue/inputicon'

const props = defineProps({
  context: String,          // 'admin' | 'manager'
  filters: Object,
  filterOptions: Object,
  kpi: Object,
  charts: Object,
  tables: Object
})

const loading = ref(false)
const searchQuery = ref('')

// Charts data
const agingPieData = computed(() => ({
  labels: props.charts?.aging_pie?.labels || [],
  datasets: [{ data: props.charts?.aging_pie?.values || [] }]
}))
const debitCreditBarData = computed(() => ({
  labels: props.charts?.bar_debit_credit?.labels || [],
  datasets: [{ data: props.charts?.bar_debit_credit?.values || [] }]
}))

// Chart options (giống Revenue style)
const pieChartOptions = ref({
  responsive: true,
  maintainAspectRatio: false,
  plugins: { legend: { position: 'bottom' } }
})
const barChartOptions = ref({
  responsive: true,
  maintainAspectRatio: false,
  plugins: { legend: { display: false } },
  scales: { y: { beginAtZero: true } }
})

const bucketLabel = (b) => ({
  '0-30': 'Trong hạn (≤30 ngày)',
  '31-60': 'Quá hạn 31–60 ngày',
  '61-90': 'Quá hạn 61–90 ngày',
  '90+': 'Quá hạn trên 90 ngày',
}[b] ?? b)
const bucketSeverity = (b) => ({
  '0-30': 'success', '31-60': 'warning', '61-90': 'danger', '90+': 'danger',
}[b] ?? 'info')

// Table data
const filteredRows = computed(() => {
  const rows = props.tables?.details || []
  if (!searchQuery.value) return rows
  const q = searchQuery.value.toLowerCase()
  return rows.filter(r =>
    (r.student_code || '').toLowerCase().includes(q) ||
    (r.student_name || '').toLowerCase().includes(q)
  )
})

// Methods
const applyFilters = (filterData) => {
  loading.value = true
  router.get(route(props.context === 'admin' ? 'admin.reports.aging' : 'manager.reports.aging'), filterData, {
    preserveState: true, preserveScroll: true, onFinish: () => loading.value = false
  })
}
const resetFilters = () => {
  loading.value = true
  router.get(route(props.context === 'admin' ? 'admin.reports.aging' : 'manager.reports.aging'), {}, {
    preserveState: true, preserveScroll: true, onFinish: () => loading.value = false
  })
}
const exportCSV = () => {
  // TODO: export CSV/Excel nếu cần
  console.log('Export CSV - todo')
}
const formatCurrency = (v) => new Intl.NumberFormat('vi-VN', {
  style: 'currency', currency: 'VND', minimumFractionDigits: 0
}).format(Number(v) || 0)
</script>
