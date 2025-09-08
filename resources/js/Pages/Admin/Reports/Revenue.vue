<template>
  <AppLayout>
    <div class="p-3 md:p-5 space-y-6">
      <!-- Page header -->
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
          <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
            Báo cáo Doanh thu
          </h1>
          <p class="text-gray-600 dark:text-gray-400">
            Tổng hợp doanh thu và công nợ toàn hệ thống
          </p>
        </div>
      </div>

      <!-- Filter bar -->
      <FilterBar
        :filters="filters"
        :filter-options="filterOptions"
        :show-branch-filter="true"
        :loading="loading"
        @apply="applyFilters"
        @reset="resetFilters"
        @export="exportCSV"
      />

      <!-- KPI Cards -->
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
        <KPICard
          label="Doanh thu (khoảng lọc)"
          :value="kpi.revenue_range.total"
          icon="pi pi-chart-line"
          color="green"
          format="currency"
        />

        <KPICard
          label="Doanh thu tháng này"
          :value="kpi.revenue_month.total"
          icon="pi pi-calendar"
          color="blue"
          format="currency"
        />

        <KPICard
          label="Hóa đơn đã thu"
          :value="kpi.invoices.paid"
          icon="pi pi-check-circle"
          color="green"
        />

        <KPICard
          label="Công nợ"
          :value="kpi.outstanding.total"
          icon="pi pi-exclamation-triangle"
          color="red"
          format="currency"
        />
      </div>

      <!-- Charts section -->
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Revenue trend chart -->
        <Card>
          <template #title>Xu hướng doanh thu</template>
          <template #content>
            <div class="h-80">
              <Chart
                type="line"
                :data="revenueTrendData"
                :options="lineChartOptions"
                class="h-full"
              />
            </div>
          </template>
        </Card>

        <!-- Invoice status chart -->
        <Card>
          <template #title>Trạng thái hóa đơn theo tháng</template>
          <template #content>
            <div class="h-80">
              <Chart
                type="bar"
                :data="invoiceStatusData"
                :options="barChartOptions"
                class="h-full"
              />
            </div>
          </template>
        </Card>

        <!-- Revenue by branch/course -->
        <Card>
          <template #title>
            <div class="flex items-center justify-between">
              <span>Cơ cấu doanh thu</span>
              <SelectButton
                v-model="chartToggle"
                :options="chartToggleOptions"
                option-label="label"
                option-value="value"
                size="small"
              />
            </div>
          </template>
          <template #content>
            <div class="h-80">
              <Chart
                type="doughnut"
                :data="pieChartData"
                :options="pieChartOptions"
                class="h-full"
              />
            </div>
          </template>
        </Card>

        <!-- Summary stats -->
        <Card>
          <template #title>Thống kê tổng hợp</template>
          <template #content>
            <div class="space-y-4">
              <div class="flex justify-between items-center py-2 border-b border-gray-200 dark:border-gray-700">
                <span class="text-gray-600 dark:text-gray-400">Tổng hóa đơn</span>
                <span class="font-semibold">{{ kpi.invoices.total }}</span>
              </div>
              <div class="flex justify-between items-center py-2 border-b border-gray-200 dark:border-gray-700">
                <span class="text-gray-600 dark:text-gray-400">Chưa thu</span>
                <span class="font-semibold text-red-600">{{ kpi.invoices.unpaid }}</span>
              </div>
              <div class="flex justify-between items-center py-2 border-b border-gray-200 dark:border-gray-700">
                <span class="text-gray-600 dark:text-gray-400">Thu một phần</span>
                <span class="font-semibold text-yellow-600">{{ kpi.invoices.partial }}</span>
              </div>
              <div class="flex justify-between items-center py-2">
                <span class="text-gray-600 dark:text-gray-400">Tỷ lệ thu hồi</span>
                <span class="font-semibold text-green-600">
                  {{ ((kpi.invoices.paid / kpi.invoices.total) * 100).toFixed(1) }}%
                </span>
              </div>
            </div>
          </template>
        </Card>
      </div>

      <!-- Detailed table -->
      <Card>
        <template #title>
          <div class="flex items-center justify-between">
            <span>Hóa đơn theo tháng/chi nhánh</span>
            <div class="flex items-center gap-2">
              <IconField iconPosition="left">
                <InputIcon class="pi pi-search" />
                <InputText
                  v-model="searchQuery"
                  placeholder="Tìm kiếm..."
                  size="small"
                />
              </IconField>
            </div>
          </div>
        </template>
        <template #content>
          <DataTable
            :value="filteredTableData"
            :paginator="true"
            :rows="20"
            :rows-per-page-options="[20, 50, 100]"
            paginator-template="FirstPageLink PrevPageLink PageLinks NextPageLink LastPageLink CurrentPageReport RowsPerPageDropdown"
            current-page-report-template="Hiển thị {first} đến {last} của {totalRecords} bản ghi"
            class="p-datatable-sm"
            :loading="loading"
          >
            <Column field="month" header="Tháng" sortable />
            <Column field="branch" header="Chi nhánh" sortable />
            <Column field="invoice_count" header="Số HĐ" sortable />
            <Column field="total_amount" header="Tổng tiền" sortable>
              <template #body="{ data }">
                {{ formatCurrency(data.total_amount) }}
              </template>
            </Column>
            <Column field="paid_amount" header="Đã thu" sortable>
              <template #body="{ data }">
                {{ formatCurrency(data.paid_amount) }}
              </template>
            </Column>
            <Column field="unpaid_amount" header="Chưa thu" sortable>
              <template #body="{ data }">
                {{ formatCurrency(data.unpaid_amount) }}
              </template>
            </Column>
            <Column field="partial_amount" header="Thu một phần" sortable>
              <template #body="{ data }">
                {{ formatCurrency(data.partial_amount) }}
              </template>
            </Column>

            <template #empty>
              <div class="text-center py-8">
                <i class="pi pi-wallet text-4xl text-gray-400 mb-4"></i>
                <p class="text-gray-500">Chưa có dữ liệu trong khoảng lọc</p>
                <p class="text-sm text-gray-400">Thử mở rộng khoảng thời gian</p>
              </div>
            </template>
          </DataTable>
        </template>
      </Card>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, computed, watch, onMounted } from 'vue'
import { router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import FilterBar from '@/Components/Reports/FilterBar.vue'
import KPICard from '@/Components/Reports/KPICard.vue'
import Card from 'primevue/card'
import Chart from 'primevue/chart'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import InputText from 'primevue/inputtext'
import IconField from 'primevue/iconfield'
import InputIcon from 'primevue/inputicon'
import SelectButton from 'primevue/selectbutton'

const props = defineProps({
  filters: Object,
  filterOptions: Object,
  kpi: Object,
  charts: Object,
  tables: Object
})

const loading = ref(false)
const searchQuery = ref('')
const chartToggle = ref('branch')

const chartToggleOptions = [
  { label: 'Chi nhánh', value: 'branch' },
  { label: 'Khóa học', value: 'course' }
]

// Chart data computeds
const revenueTrendData = computed(() => ({
  labels: props.charts?.revenue_trend?.map(item => item.period) || [],
  datasets: [{
    label: 'Doanh thu',
    data: props.charts?.revenue_trend?.map(item => item.value) || [],
    borderColor: '#10b981',
    backgroundColor: 'rgba(16, 185, 129, 0.1)',
    tension: 0.4,
    fill: true
  }]
}))

const invoiceStatusData = computed(() => {
  const data = props.charts?.invoice_status_by_month || []
  return {
    labels: data.map(item => item.month),
    datasets: [
      {
        label: 'Đã thu',
        data: data.map(item => item.paid),
        backgroundColor: '#10b981'
      },
      {
        label: 'Thu một phần',
        data: data.map(item => item.partial),
        backgroundColor: '#f59e0b'
      },
      {
        label: 'Chưa thu',
        data: data.map(item => item.unpaid),
        backgroundColor: '#ef4444'
      }
    ]
  }
})

const pieChartData = computed(() => {
  const source = chartToggle.value === 'branch'
    ? props.charts?.revenue_by_branch
    : props.charts?.revenue_by_course

  if (!source || source.length === 0) {
    return { labels: [], datasets: [{ data: [] }] }
  }

  return {
    labels: source.map(item => item.name),
    datasets: [{
      data: source.map(item => item.value),
      backgroundColor: [
        '#10b981', '#3b82f6', '#f59e0b', '#ef4444', '#8b5cf6',
        '#06b6d4', '#84cc16', '#f97316', '#ec4899', '#6366f1'
      ]
    }]
  }
})

// Chart options
const lineChartOptions = ref({
  responsive: true,
  maintainAspectRatio: false,
  plugins: {
    legend: {
      display: false
    }
  },
  scales: {
    y: {
      beginAtZero: true,
      ticks: {
        callback: function(value) {
          return new Intl.NumberFormat('vi-VN', {
            notation: 'compact',
            compactDisplay: 'short'
          }).format(value)
        }
      }
    }
  }
})

const barChartOptions = ref({
  responsive: true,
  maintainAspectRatio: false,
  plugins: {
    legend: {
      position: 'bottom'
    }
  },
  scales: {
    x: {
      stacked: true
    },
    y: {
      stacked: true,
      beginAtZero: true
    }
  }
})

const pieChartOptions = ref({
  responsive: true,
  maintainAspectRatio: false,
  plugins: {
    legend: {
      position: 'bottom'
    }
  }
})

// Table data
const filteredTableData = computed(() => {
  const data = props.tables?.monthly_summary || []
  if (!searchQuery.value) return data

  const query = searchQuery.value.toLowerCase()
  return data.filter(item =>
    item.month.toLowerCase().includes(query) ||
    item.branch.toLowerCase().includes(query)
  )
})

// Methods
const applyFilters = (filterData) => {
  loading.value = true
  router.get(route('admin.reports.revenue'), filterData, {
    preserveState: true,
    preserveScroll: true,
    onFinish: () => loading.value = false
  })
}

const resetFilters = () => {
  loading.value = true
  router.get(route('admin.reports.revenue'), {}, {
    preserveState: true,
    preserveScroll: true,
    onFinish: () => loading.value = false
  })
}

const exportCSV = () => {
  // Implementation for CSV export
  console.log('Export CSV functionality to be implemented')
}

const formatCurrency = (value) => {
  const numValue = Number(value) || 0
  return new Intl.NumberFormat('vi-VN', {
    style: 'currency',
    currency: 'VND',
    minimumFractionDigits: 0
  }).format(numValue)
}
</script>
