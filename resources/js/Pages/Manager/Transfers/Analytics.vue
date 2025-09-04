<script setup>
import { ref, computed, onMounted } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'

// PrimeVue
import Card from 'primevue/card'
import Select from 'primevue/select'
import DatePicker from 'primevue/datepicker'
import Button from 'primevue/button'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import Chart from 'primevue/chart'

defineOptions({ layout: AppLayout })

const props = defineProps({
  analytics: Object, // Transfer analytics data from backend
  filters: Object,
})

// Reactive data
const selectedPeriod = ref(props.filters?.period || 'month')
const selectedDateRange = ref([
  props.filters?.start_date ? new Date(props.filters.start_date) : new Date(new Date().getFullYear(), new Date().getMonth(), 1),
  props.filters?.end_date ? new Date(props.filters.end_date) : new Date()
])

const periodOptions = [
  { label: 'Tuần này', value: 'week' },
  { label: 'Tháng này', value: 'month' },
  { label: 'Quý này', value: 'quarter' },
  { label: 'Năm này', value: 'year' },
  { label: 'Tùy chọn', value: 'custom' }
]

// Filters
const currencyFilter = (value) => {
  if (!value) return '0 VND'
  return new Intl.NumberFormat('vi-VN', {
    style: 'currency',
    currency: 'VND'
  }).format(value)
}

// Computed
const chartData = computed(() => ({
  labels: props.analytics?.chart_data?.labels || [],
  datasets: [
    {
      label: 'Số lượng chuyển lớp',
      data: props.analytics?.chart_data?.data || [],
      backgroundColor: 'rgba(54, 162, 235, 0.2)',
      borderColor: 'rgba(54, 162, 235, 1)',
      borderWidth: 2,
      tension: 0.4
    }
  ]
}))

const chartOptions = {
  responsive: true,
  maintainAspectRatio: false,
  plugins: {
    legend: {
      position: 'top',
    },
    title: {
      display: true,
      text: 'Biểu đồ chuyển lớp theo thời gian'
    }
  },
  scales: {
    y: {
      beginAtZero: true,
      ticks: {
        stepSize: 1
      }
    }
  }
}

const statusChartData = computed(() => ({
  labels: ['Đang hoạt động', 'Đã hoàn tác', 'Đã đổi hướng'],
  datasets: [
    {
      data: [
        props.analytics?.status_breakdown?.active || 0,
        props.analytics?.status_breakdown?.reverted || 0,
        props.analytics?.status_breakdown?.retargeted || 0
      ],
      backgroundColor: [
        '#10B981', // green for active
        '#F59E0B', // orange for reverted
        '#8B5CF6'  // purple for retargeted
      ]
    }
  ]
}))

const statusChartOptions = {
  responsive: true,
  maintainAspectRatio: false,
  plugins: {
    legend: {
      position: 'bottom'
    },
    title: {
      display: true,
      text: 'Phân bố trạng thái chuyển lớp'
    }
  }
}

// Methods
function applyFilters() {
  const params = {
    period: selectedPeriod.value
  }

  if (selectedPeriod.value === 'custom' && selectedDateRange.value?.length === 2) {
    params.start_date = selectedDateRange.value[0].toISOString().split('T')[0]
    params.end_date = selectedDateRange.value[1].toISOString().split('T')[0]
  }

  router.visit(route('manager.transfers.analytics', params), {
    preserveScroll: true,
    preserveState: true
  })
}

function exportData() {
  window.open(route('manager.transfers.analytics.export', props.filters), '_blank')
}
</script>

<template>
  <Head title="Transfer Analytics" />

  <div class="max-w-7xl mx-auto space-y-6">
    <!-- Page Header -->
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-bold text-slate-900 dark:text-slate-100">
          Phân tích chuyển lớp
        </h1>
        <p class="text-slate-600 dark:text-slate-400">Thống kê và báo cáo chi tiết</p>
      </div>

      <Button
        label="Xuất báo cáo"
        icon="pi pi-download"
        @click="exportData"
        severity="success"
      />
    </div>

    <!-- Filters -->
    <Card class="bg-white dark:bg-slate-800">
      <template #content>
        <div class="flex flex-wrap items-end gap-4">
          <div class="flex-1 min-w-[200px]">
            <label class="block text-sm font-medium mb-1">Thời gian</label>
            <Select
              v-model="selectedPeriod"
              :options="periodOptions"
              optionLabel="label"
              optionValue="value"
              class="w-full"
            />
          </div>

          <div v-if="selectedPeriod === 'custom'" class="flex-1 min-w-[250px]">
            <label class="block text-sm font-medium mb-1">Khoảng thời gian</label>
            <DatePicker
              v-model="selectedDateRange"
              selectionMode="range"
              dateFormat="dd/mm/yy"
              class="w-full"
            />
          </div>

          <div class="flex-shrink-0">
            <Button
              label="Áp dụng"
              icon="pi pi-search"
              @click="applyFilters"
              class="h-[42px]"
            />
          </div>
        </div>
      </template>
    </Card>

    <!-- Key Metrics -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
      <Card class="bg-white dark:bg-slate-800">
        <template #content>
          <div class="flex items-center justify-between">
            <div>
              <div class="text-2xl font-bold text-blue-600">
                {{ analytics?.total_transfers || 0 }}
              </div>
              <div class="text-sm text-slate-500">Tổng chuyển lớp</div>
            </div>
            <i class="pi pi-refresh text-3xl text-blue-600"></i>
          </div>
        </template>
      </Card>

      <Card class="bg-white dark:bg-slate-800">
        <template #content>
          <div class="flex items-center justify-between">
            <div>
              <div class="text-2xl font-bold text-green-600">
                {{ analytics?.success_rate || 0 }}%
              </div>
              <div class="text-sm text-slate-500">Tỷ lệ thành công</div>
            </div>
            <i class="pi pi-check-circle text-3xl text-green-600"></i>
          </div>
        </template>
      </Card>

      <Card class="bg-white dark:bg-slate-800">
        <template #content>
          <div class="flex items-center justify-between">
            <div>
              <div class="text-2xl font-bold text-purple-600">
                {{ currencyFilter(analytics?.total_fee || 0) }}
              </div>
              <div class="text-sm text-slate-500">Tổng phí thu</div>
            </div>
            <i class="pi pi-wallet text-3xl text-purple-600"></i>
          </div>
        </template>
      </Card>

      <Card class="bg-white dark:bg-slate-800">
        <template #content>
          <div class="flex items-center justify-between">
            <div>
              <div class="text-2xl font-bold text-orange-600">
                {{ analytics?.avg_processing_days || 0 }}
              </div>
              <div class="text-sm text-slate-500">Ngày xử lý TB</div>
            </div>
            <i class="pi pi-clock text-3xl text-orange-600"></i>
          </div>
        </template>
      </Card>
    </div>

    <!-- Charts -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
      <!-- Transfer Trend Chart -->
      <Card class="bg-white dark:bg-slate-800">
        <template #title>Xu hướng chuyển lớp</template>
        <template #content>
          <div class="h-80">
            <Chart
              type="line"
              :data="chartData"
              :options="chartOptions"
              class="h-full"
            />
          </div>
        </template>
      </Card>

      <!-- Status Distribution -->
      <Card class="bg-white dark:bg-slate-800">
        <template #title>Phân bố trạng thái</template>
        <template #content>
          <div class="h-80">
            <Chart
              type="doughnut"
              :data="statusChartData"
              :options="statusChartOptions"
              class="h-full"
            />
          </div>
        </template>
      </Card>
    </div>

    <!-- Top Classes Table -->
    <Card class="bg-white dark:bg-slate-800">
      <template #title>
        <div class="flex items-center gap-2">
          <i class="pi pi-star text-yellow-500"></i>
          Top lớp học có nhiều chuyển lớp
        </div>
      </template>
      <template #content>
        <DataTable
          :value="analytics?.top_classes || []"
          :paginator="false"
          stripedRows
        >
          <Column field="class_code" header="Mã lớp" :sortable="true" />
          <Column field="class_name" header="Tên lớp" />
          <Column field="transfers_in" header="Chuyển vào" :sortable="true">
            <template #body="{ data }">
              <span class="text-green-600 font-medium">+{{ data.transfers_in }}</span>
            </template>
          </Column>
          <Column field="transfers_out" header="Chuyển ra" :sortable="true">
            <template #body="{ data }">
              <span class="text-red-600 font-medium">-{{ data.transfers_out }}</span>
            </template>
          </Column>
          <Column field="net_change" header="Thay đổi ròng" :sortable="true">
            <template #body="{ data }">
              <span :class="data.net_change >= 0 ? 'text-green-600' : 'text-red-600'" class="font-medium">
                {{ data.net_change >= 0 ? '+' : '' }}{{ data.net_change }}
              </span>
            </template>
          </Column>
        </DataTable>
      </template>
    </Card>

    <!-- Reasons Analysis -->
    <Card class="bg-white dark:bg-slate-800">
      <template #title>
        <div class="flex items-center gap-2">
          <i class="pi pi-list text-blue-500"></i>
          Phân tích lý do chuyển lớp
        </div>
      </template>
      <template #content>
        <DataTable
          :value="analytics?.reasons_analysis || []"
          :paginator="false"
          stripedRows
        >
          <Column field="reason" header="Lý do" />
          <Column field="count" header="Số lượng" :sortable="true">
            <template #body="{ data }">
              <div class="flex items-center gap-2">
                <span class="font-medium">{{ data.count }}</span>
                <div class="flex-1 bg-slate-200 dark:bg-slate-600 rounded-full h-2">
                  <div
                    class="bg-blue-500 h-2 rounded-full"
                    :style="{ width: `${(data.count / analytics.total_transfers) * 100}%` }"
                  ></div>
                </div>
                <span class="text-sm text-slate-500">
                  {{ Math.round((data.count / analytics.total_transfers) * 100) }}%
                </span>
              </div>
            </template>
          </Column>
        </DataTable>
      </template>
    </Card>
  </div>
</template>

<style scoped>
/* Custom styles for charts */
</style>
