<template>
  <AppLayout>
    <div class="p-3 md:p-5 space-y-6">
      <!-- Page header -->
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
          <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
            Báo cáo Tài chính
          </h1>
          <p class="text-gray-600 dark:text-gray-400">
            Theo dõi doanh thu và công nợ chi nhánh
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
          label="Doanh thu tháng"
          :value="kpi.revenue_month.total"
          icon="pi pi-chart-line"
          color="green"
          format="currency"
        />

        <KPICard
          label="Hóa đơn chưa thu"
          :value="kpi.unpaid_invoices.total"
          icon="pi pi-exclamation-triangle"
          color="red"
        />

        <KPICard
          label="Tỷ lệ thu hồi"
          :value="kpi.collection_rate.rate"
          icon="pi pi-percentage"
          color="blue"
          format="percentage"
        />

        <KPICard
          label="Công nợ"
          :value="kpi.outstanding.total"
          icon="pi pi-clock"
          color="yellow"
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
          <template #title>Phân bố trạng thái hóa đơn</template>
          <template #content>
            <div class="h-80">
              <Chart
                type="doughnut"
                :data="invoiceStatusData"
                :options="pieChartOptions"
                class="h-full"
              />
            </div>
          </template>
        </Card>

        <!-- Revenue by course -->
        <Card>
          <template #title>Doanh thu theo khóa học</template>
          <template #content>
            <div class="h-80">
              <Chart
                type="bar"
                :data="revenueByCourseData"
                :options="barChartOptions"
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
                <span class="text-gray-600 dark:text-gray-400">Tổng doanh thu</span>
                <span class="font-semibold text-green-600">
                  {{ formatCurrency(kpi.revenue_month.total) }}
                </span>
              </div>
              <div class="flex justify-between items-center py-2 border-b border-gray-200 dark:border-gray-700">
                <span class="text-gray-600 dark:text-gray-400">Số HĐ chưa thu</span>
                <span class="font-semibold text-red-600">{{ kpi.unpaid_invoices.total }}</span>
              </div>
              <div class="flex justify-between items-center py-2 border-b border-gray-200 dark:border-gray-700">
                <span class="text-gray-600 dark:text-gray-400">Công nợ</span>
                <span class="font-semibold text-yellow-600">
                  {{ formatCurrency(kpi.outstanding.total) }}
                </span>
              </div>
              <div class="flex justify-between items-center py-2">
                <span class="text-gray-600 dark:text-gray-400">Tỷ lệ thu hồi</span>
                <span class="font-semibold text-blue-600">{{ kpi.collection_rate.rate }}%</span>
              </div>
            </div>
          </template>
        </Card>
      </div>

      <!-- Detailed invoice table -->
      <Card>
        <template #title>
          <div class="flex items-center justify-between">
            <span>Chi tiết hóa đơn</span>
            <div class="flex items-center gap-2">
              <IconField iconPosition="left">
                <InputIcon class="pi pi-search" />
                <InputText
                  v-model="searchQuery"
                  placeholder="Tìm hóa đơn..."
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
            <Column field="code" header="Mã HĐ" sortable />
            <Column field="student" header="Học viên" sortable />
            <Column field="class" header="Lớp" sortable />
            <Column field="total" header="Tổng tiền" sortable>
              <template #body="{ data }">
                {{ formatCurrency(data.total) }}
              </template>
            </Column>
            <Column field="paid_amount" header="Đã thu" sortable>
              <template #body="{ data }">
                {{ formatCurrency(data.paid_amount) }}
              </template>
            </Column>
            <Column field="remaining" header="Còn lại" sortable>
              <template #body="{ data }">
                {{ formatCurrency(data.remaining) }}
              </template>
            </Column>
            <Column field="due_date" header="Hạn thu" sortable />
            <Column field="status" header="Trạng thái" sortable>
              <template #body="{ data }">
                <Tag
                  :value="data.status"
                  :severity="getStatusSeverity(data.status)"
                />
              </template>
            </Column>

            <template #empty>
              <div class="text-center py-8">
                <i class="pi pi-file-o text-4xl text-gray-400 mb-4"></i>
                <p class="text-gray-500">Chưa có hóa đơn trong khoảng lọc</p>
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
import { ref, computed } from 'vue'
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
import Tag from 'primevue/tag'

const props = defineProps({
  filters: Object,
  filterOptions: Object,
  kpi: Object,
  charts: Object,
  tables: Object
})

const loading = ref(false)
const searchQuery = ref('')

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
  const data = props.charts?.invoice_status || []
  return {
    labels: data.map(item => item.name),
    datasets: [{
      data: data.map(item => item.value),
      backgroundColor: ['#10b981', '#ef4444', '#f59e0b', '#6b7280']
    }]
  }
})

const revenueByCourseData = computed(() => {
  const data = props.charts?.revenue_by_course || []
  return {
    labels: data.map(item => item.name),
    datasets: [{
      label: 'Doanh thu',
      data: data.map(item => item.value),
      backgroundColor: '#3b82f6'
    }]
  }
})

// Chart options
const lineChartOptions = ref({
  responsive: true,
  maintainAspectRatio: false,
  plugins: {
    legend: { display: false }
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

const pieChartOptions = ref({
  responsive: true,
  maintainAspectRatio: false,
  plugins: {
    legend: { position: 'bottom' }
  }
})

const barChartOptions = ref({
  responsive: true,
  maintainAspectRatio: false,
  plugins: {
    legend: { display: false }
  },
  scales: {
    y: { beginAtZero: true }
  }
})

// Table data
const filteredTableData = computed(() => {
  const data = props.tables?.invoice_details || []
  if (!searchQuery.value) return data

  const query = searchQuery.value.toLowerCase()
  return data.filter(item =>
    item.code?.toLowerCase().includes(query) ||
    item.student?.toLowerCase().includes(query) ||
    item.class?.toLowerCase().includes(query)
  )
})

// Methods
const getStatusSeverity = (status) => {
  const statusMap = {
    'Đã thu': 'success',
    'Chưa thu': 'danger',
    'Thu một phần': 'warning',
    'Đã hoàn': 'info'
  }
  return statusMap[status] || 'secondary'
}

const applyFilters = (filterData) => {
  loading.value = true
  router.get(route('manager.reports.finance'), filterData, {
    preserveState: true,
    preserveScroll: true,
    onFinish: () => loading.value = false
  })
}

const resetFilters = () => {
  loading.value = true
  router.get(route('manager.reports.finance'), {}, {
    preserveState: true,
    preserveScroll: true,
    onFinish: () => loading.value = false
  })
}

const exportCSV = () => {
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
