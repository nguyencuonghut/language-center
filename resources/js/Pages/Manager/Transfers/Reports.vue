<script setup>
import { reactive, ref, computed, watch } from 'vue'
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
import Tabs from 'primevue/tabs'
import TabList from 'primevue/tablist'
import Tab from 'primevue/tab'
import TabPanels from 'primevue/tabpanels'
import TabPanel from 'primevue/tabpanel'
import ProgressBar from 'primevue/progressbar'

defineOptions({ layout: AppLayout })

const props = defineProps({
  reportData: Object,
  allReportsData: Object,
  filters: Object,
  reportOptions: Object,
})

// Reactive filters
const filters = reactive({
  report_type: props.filters?.report_type ?? 'summary',
  date_from: props.filters?.date_from ? new Date(props.filters.date_from) : new Date(new Date().getFullYear(), new Date().getMonth(), 1),
  date_to: props.filters?.date_to ? new Date(props.filters.date_to) : new Date(),
  group_by: props.filters?.group_by ?? 'month',
  branch_id: props.filters?.branch_id ?? null,
  class_id: props.filters?.class_id ?? null,
  teacher_id: props.filters?.teacher_id ?? null,
})

// Watch for props changes and update filters
watch(() => props.filters, (newFilters) => {
  if (newFilters) {
    filters.report_type = newFilters.report_type ?? 'summary'
    filters.date_from = newFilters.date_from ? new Date(newFilters.date_from) : new Date(new Date().getFullYear(), new Date().getMonth(), 1)
    filters.date_to = newFilters.date_to ? new Date(newFilters.date_to) : new Date()
    filters.group_by = newFilters.group_by ?? 'month'
    filters.branch_id = newFilters.branch_id ?? null
    filters.class_id = newFilters.class_id ?? null
    filters.teacher_id = newFilters.teacher_id ?? null
  }
}, { immediate: true, deep: true })

// Watch for group_by changes to auto-regenerate trends report
watch(() => filters.group_by, (newValue, oldValue) => {
  // Only auto-generate if we're on trends tab and value actually changed
  if (filters.report_type === 'trends' && newValue !== oldValue && oldValue !== undefined) {
    generateReport()
  }
})

const isLoading = ref(false)

// Chart configurations
const chartOptions = {
  responsive: true,
  maintainAspectRatio: false,
  plugins: {
    legend: {
      position: 'bottom',
      labels: {
        usePointStyle: true,
        padding: 20,
        font: {
          size: 12
        }
      }
    },
    tooltip: {
      mode: 'index',
      intersect: false,
      backgroundColor: 'rgba(0, 0, 0, 0.8)',
      titleColor: '#fff',
      bodyColor: '#fff',
      borderColor: 'rgba(255, 255, 255, 0.1)',
      borderWidth: 1
    }
  },
  scales: {
    x: {
      grid: {
        color: 'rgba(0, 0, 0, 0.1)',
        drawBorder: false
      },
      ticks: {
        font: {
          size: 11
        }
      }
    },
    y: {
      beginAtZero: true,
      grid: {
        color: 'rgba(0, 0, 0, 0.1)',
        drawBorder: false
      },
      ticks: {
        stepSize: 1,
        font: {
          size: 11
        }
      }
    }
  },
  interaction: {
    mode: 'nearest',
    axis: 'x',
    intersect: false
  },
  elements: {
    line: {
      tension: 0.4
    },
    point: {
      radius: 4,
      hoverRadius: 6
    }
  }
}

const doughnutOptions = {
  responsive: true,
  maintainAspectRatio: false,
  plugins: {
    legend: {
      position: 'right'
    }
  }
}

// Computed với data động theo tab active
const currentReportData = computed(() => {
  if (props.allReportsData) {
    return props.allReportsData[filters.report_type] || props.allReportsData.summary
  }
  return props.reportData || {}
})

const summaryData = computed(() => currentReportData.value?.totals || {})
const trendsData = computed(() => {
  // Nếu đang ở tab trends, sử dụng currentReportData
  if (filters.report_type === 'trends') {
    return currentReportData.value?.trends || []
  }
  // Cho các tab khác, sử dụng trends data từ allReportsData
  return props.allReportsData?.trends?.trends || []
})
const chartData = computed(() => {
  // Nếu là tab trends và có chart_data từ backend
  if (filters.report_type === 'trends' && currentReportData.value?.chart_data) {
    const baseData = currentReportData.value.chart_data

    // Cải thiện màu sắc và style cho line chart
    if (baseData.datasets && baseData.datasets.length > 0) {
      return {
        labels: baseData.labels,
        datasets: baseData.datasets.map((dataset, index) => {
          const colors = [
            {
              // Tổng chuyển lớp - Blue
              backgroundColor: 'rgba(59, 130, 246, 0.1)',
              borderColor: 'rgb(59, 130, 246)',
              pointBackgroundColor: 'rgb(59, 130, 246)',
              pointBorderColor: '#fff',
            },
            {
              // Đang hoạt động - Green
              backgroundColor: 'rgba(16, 185, 129, 0.1)',
              borderColor: 'rgb(16, 185, 129)',
              pointBackgroundColor: 'rgb(16, 185, 129)',
              pointBorderColor: '#fff',
            },
            {
              // Hoàn tác - Orange
              backgroundColor: 'rgba(245, 158, 11, 0.1)',
              borderColor: 'rgb(245, 158, 11)',
              pointBackgroundColor: 'rgb(245, 158, 11)',
              pointBorderColor: '#fff',
            },
            {
              // Ưu tiên - Purple
              backgroundColor: 'rgba(139, 92, 246, 0.1)',
              borderColor: 'rgb(139, 92, 246)',
              pointBackgroundColor: 'rgb(139, 92, 246)',
              pointBorderColor: '#fff',
            }
          ]

          return {
            ...dataset,
            ...colors[index % colors.length],
            borderWidth: 3,
            pointRadius: 4,
            pointHoverRadius: 6,
            pointBorderWidth: 2,
            fill: true,
            tension: 0.4
          }
        })
      }
    }
    return baseData
  }

  // Cho các tab khác, tạo chart data từ trends data trong allReportsData
  if (props.allReportsData?.trends?.chart_data) {
    const trendsData = props.allReportsData.trends.chart_data
    
    if (trendsData.datasets && trendsData.datasets.length > 0) {
      return {
        labels: trendsData.labels,
        datasets: trendsData.datasets.map((dataset, index) => {
          const colors = [
            {
              backgroundColor: 'rgba(59, 130, 246, 0.1)',
              borderColor: 'rgb(59, 130, 246)',
              pointBackgroundColor: 'rgb(59, 130, 246)',
              pointBorderColor: '#fff',
            },
            {
              backgroundColor: 'rgba(16, 185, 129, 0.1)',
              borderColor: 'rgb(16, 185, 129)',
              pointBackgroundColor: 'rgb(16, 185, 129)',
              pointBorderColor: '#fff',
            }
          ]

          return {
            ...dataset,
            ...colors[index % colors.length],
            borderWidth: 3,
            pointRadius: 4,
            pointHoverRadius: 6,
            pointBorderWidth: 2,
            fill: true,
            tension: 0.4
          }
        })
      }
    }
    return trendsData
  }

  return { labels: [], datasets: [] }
})

const performanceChartData = computed(() => {
  if (!currentReportData.value?.user_performance) return { labels: [], datasets: [] }

  const users = currentReportData.value.user_performance
  return {
    labels: users.map(u => u.name),
    datasets: [
      {
        label: 'Tổng transfers',
        data: users.map(u => u.total_transfers),
        backgroundColor: 'rgba(59, 130, 246, 0.5)',
        borderColor: 'rgba(59, 130, 246, 1)',
        borderWidth: 1
      },
      {
        label: 'Thành công',
        data: users.map(u => u.successful),
        backgroundColor: 'rgba(16, 185, 129, 0.5)',
        borderColor: 'rgba(16, 185, 129, 1)',
        borderWidth: 1
      }
    ]
  }
})

// Helper function to format date without timezone issues
function formatDateForServer(date) {
  if (!date) return null
  const year = date.getFullYear()
  const month = String(date.getMonth() + 1).padStart(2, '0')
  const day = String(date.getDate()).padStart(2, '0')
  return `${year}-${month}-${day}`
}

// Helper function to translate group_by to Vietnamese
function getGroupByLabel(groupBy) {
  const labels = {
    'day': 'ngày',
    'week': 'tuần',
    'month': 'tháng',
    'quarter': 'quý'
  }
  return labels[groupBy] || groupBy
}

// Methods
function generateReport() {
  isLoading.value = true

  const params = { ...filters }
  if (params.date_from) params.date_from = formatDateForServer(params.date_from)
  if (params.date_to) params.date_to = formatDateForServer(params.date_to)

  router.visit(route('manager.transfers.advanced.reports'), {
    method: 'get',
    data: params,
    preserveState: false,
    preserveScroll: true,
    onFinish: () => {
      isLoading.value = false
    }
  })
}

function handleTabChange(tabValue) {
  filters.report_type = tabValue
  // Không tự động generate report khi đổi tab
  // Để user có thể chuyển đổi giữa các tab mà vẫn giữ data
}

function exportReport() {
  const params = { ...filters }
  if (params.date_from) params.date_from = formatDateForServer(params.date_from)
  if (params.date_to) params.date_to = formatDateForServer(params.date_to)

  window.open(route('manager.transfers.advanced.reports.export', params), '_blank')
}

function formatCurrency(value) {
  if (!value) return '0 VND'
  return new Intl.NumberFormat('vi-VN', {
    style: 'currency',
    currency: 'VND'
  }).format(value)
}

function formatNumber(value) {
  return new Intl.NumberFormat('vi-VN').format(value || 0)
}

function formatPercentage(value) {
  return `${(value || 0).toFixed(1)}%`
}
</script>

<template>
  <Head title="Báo cáo nâng cao - Chuyển lớp" />

  <div class="space-y-6">
    <!-- Page Header -->
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-bold text-slate-900 dark:text-slate-100">
          Báo cáo nâng cao
        </h1>
        <p class="text-slate-600 dark:text-slate-400">
          Báo cáo chi tiết và phân tích xu hướng chuyển lớp
        </p>
      </div>
      <div class="flex items-center gap-2">
        <Button
          label="Xuất báo cáo"
          icon="pi pi-download"
          @click="exportReport"
          severity="success"
          :disabled="isLoading"
        />
        <Button
          label="Tìm kiếm nâng cao"
          icon="pi pi-search"
          @click="router.visit(route('manager.transfers.advanced.search'))"
          severity="info"
          outlined
        />
      </div>
    </div>

    <!-- Report Filters -->
    <Card class="bg-white dark:bg-slate-800">
      <template #content>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
          <div>
            <label class="block text-sm font-medium mb-1">Từ ngày</label>
            <DatePicker
              v-model="filters.date_from"
              dateFormat="dd/mm/yy"
              class="w-full"
            />
          </div>

          <div>
            <label class="block text-sm font-medium mb-1">Đến ngày</label>
            <DatePicker
              v-model="filters.date_to"
              dateFormat="dd/mm/yy"
              class="w-full"
            />
          </div>

          <div v-if="filters.report_type === 'trends'" class="trends-filter">
            <label class="block text-sm font-medium mb-1">Nhóm theo</label>
            <Select
              v-model="filters.group_by"
              :options="reportOptions.groupOptions"
              optionLabel="label"
              optionValue="value"
              placeholder="Chọn cách nhóm dữ liệu"
              class="w-full"
            />
          </div>

          <div class="flex items-end">
            <Button
              label="Tạo báo cáo"
              icon="pi pi-chart-bar"
              @click="generateReport"
              :loading="isLoading"
              class="w-full"
            />
          </div>
        </div>
      </template>
    </Card>

    <!-- Report Content -->
    <div v-if="currentReportData && Object.keys(currentReportData).length > 0">
      <Tabs :value="filters.report_type || 'summary'" @update:value="handleTabChange">
        <TabList>
          <Tab value="summary" @click="() => handleTabChange('summary')">
            <i class="pi pi-chart-pie mr-2"></i>
            Tổng quan
          </Tab>
          <Tab value="trends" @click="() => handleTabChange('trends')">
            <i class="pi pi-chart-line mr-2"></i>
            Xu hướng
          </Tab>
          <Tab value="performance" @click="() => handleTabChange('performance')">
            <i class="pi pi-users mr-2"></i>
            Hiệu suất
          </Tab>
          <Tab value="detailed" @click="() => handleTabChange('detailed')">
            <i class="pi pi-table mr-2"></i>
            Chi tiết
          </Tab>
        </TabList>

        <TabPanels>
          <!-- Summary Tab -->
          <TabPanel value="summary">
          <div class="space-y-6">
            <!-- Key Metrics -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
              <Card class="bg-gradient-to-br from-blue-500 to-blue-600 text-white">
                <template #content>
                  <div class="flex items-center justify-between">
                    <div>
                      <div class="text-2xl font-bold">{{ formatNumber(summaryData.total_transfers) }}</div>
                      <div class="text-blue-100">Tổng chuyển lớp</div>
                    </div>
                    <i class="pi pi-refresh text-3xl opacity-80"></i>
                  </div>
                </template>
              </Card>

              <Card class="bg-gradient-to-br from-green-500 to-green-600 text-white">
                <template #content>
                  <div class="flex items-center justify-between">
                    <div>
                      <div class="text-2xl font-bold">{{ formatNumber(summaryData.active_transfers) }}</div>
                      <div class="text-green-100">Đang hoạt động</div>
                    </div>
                    <i class="pi pi-check-circle text-3xl opacity-80"></i>
                  </div>
                </template>
              </Card>

              <Card class="bg-gradient-to-br from-orange-500 to-orange-600 text-white">
                <template #content>
                  <div class="flex items-center justify-between">
                    <div>
                      <div class="text-2xl font-bold">{{ formatNumber(summaryData.reverted_transfers) }}</div>
                      <div class="text-orange-100">Đã hoàn tác</div>
                    </div>
                    <i class="pi pi-undo text-3xl opacity-80"></i>
                  </div>
                </template>
              </Card>

              <Card class="bg-gradient-to-br from-purple-500 to-purple-600 text-white">
                <template #content>
                  <div class="flex items-center justify-between">
                    <div>
                      <div class="text-2xl font-bold">{{ formatCurrency(summaryData.total_fees) }}</div>
                      <div class="text-purple-100">Tổng phí thu</div>
                    </div>
                    <i class="pi pi-wallet text-3xl opacity-80"></i>
                  </div>
                </template>
              </Card>
            </div>

            <!-- Charts Row -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
              <!-- By Source -->
              <Card class="bg-white dark:bg-slate-800">
                <template #title>Phân bố theo nguồn</template>
                <template #content>
                  <div class="h-80">
                    <Chart
                      type="doughnut"
                      :data="{
                        labels: currentReportData.by_source?.map(s => s.source_system) || [],
                        datasets: [{
                          data: currentReportData.by_source?.map(s => s.count) || [],
                          backgroundColor: ['#3B82F6', '#10B981', '#F59E0B', '#EF4444']
                        }]
                      }"
                      :options="doughnutOptions"
                      class="h-full"
                    />
                  </div>
                </template>
              </Card>

              <!-- Top Reasons -->
              <Card class="bg-white dark:bg-slate-800">
                <template #title>Lý do chuyển lớp phổ biến</template>
                <template #content>
                  <DataTable
                    :value="currentReportData.top_reasons || []"
                    :paginator="false"
                    size="small"
                  >
                    <Column field="reason" header="Lý do">
                      <template #body="{ data }">
                        <div class="max-w-xs truncate" :title="data.reason">
                          {{ data.reason }}
                        </div>
                      </template>
                    </Column>
                    <Column field="count" header="Số lượng">
                      <template #body="{ data }">
                        <div class="flex items-center gap-3">
                          <div class="flex-1 min-w-0">
                            <ProgressBar
                              :value="summaryData.total_transfers > 0 ? ((data.count / summaryData.total_transfers) * 100) : 0"
                              class="w-full h-3"
                              :showValue="false"
                            />
                          </div>
                          <div class="flex items-center gap-2 flex-shrink-0">
                            <span class="font-medium text-sm">{{ data.count }}</span>
                            <span class="text-xs text-blue-600 font-medium min-w-[40px] text-right">
                              {{ summaryData.total_transfers > 0 ? ((data.count / summaryData.total_transfers) * 100).toFixed(1) : '0.0' }}%
                            </span>
                          </div>
                        </div>
                      </template>
                    </Column>
                  </DataTable>
                </template>
              </Card>
            </div>
          </div>
        </TabPanel>

        <!-- Trends Tab -->
        <TabPanel value="trends">
          <div class="space-y-6">
            <!-- Trend Chart -->
            <Card class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700">
              <template #title>
                <div class="flex items-center gap-2">
                  <i class="pi pi-chart-line text-blue-600"></i>
                  <span>Xu hướng chuyển lớp theo {{ getGroupByLabel(filters.group_by) }}</span>
                </div>
              </template>
              <template #content>
                <div class="h-96 p-4">
                  <Chart
                    type="line"
                    :data="chartData"
                    :options="chartOptions"
                    class="h-full w-full"
                  />
                </div>
              </template>
            </Card>

            <!-- Trends Table -->
            <Card class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700">
              <template #title>
                <div class="flex items-center gap-2">
                  <i class="pi pi-table text-purple-600"></i>
                  <span>Chi tiết xu hướng</span>
                </div>
              </template>
              <template #content>
                <DataTable
                  :value="trendsData"
                  :paginator="false"
                  stripedRows
                  size="small"
                  class="rounded-lg overflow-hidden"
                >
                  <Column field="period" header="Kỳ" :sortable="true">
                    <template #body="{ data }">
                      <span class="font-medium text-slate-900 dark:text-slate-100">{{ data.period }}</span>
                    </template>
                  </Column>
                  <Column field="total" header="Tổng" :sortable="true">
                    <template #body="{ data }">
                      <span class="font-bold text-blue-600 dark:text-blue-400">{{ data.total }}</span>
                    </template>
                  </Column>
                  <Column field="active" header="Hoạt động" :sortable="true">
                    <template #body="{ data }">
                      <span class="font-bold text-green-600 dark:text-green-400">{{ data.active }}</span>
                    </template>
                  </Column>
                  <Column field="reverted" header="Hoàn tác" :sortable="true">
                    <template #body="{ data }">
                      <span class="font-bold text-orange-600 dark:text-orange-400">{{ data.reverted }}</span>
                    </template>
                  </Column>
                  <Column field="priority" header="Ưu tiên" :sortable="true">
                    <template #body="{ data }">
                      <span class="font-bold text-purple-600 dark:text-purple-400">{{ data.priority }}</span>
                    </template>
                  </Column>
                  <Column field="total_fees" header="Tổng phí" :sortable="true">
                    <template #body="{ data }">
                      <span class="font-medium text-slate-700 dark:text-slate-300">
                        {{ formatCurrency(data.total_fees) }}
                      </span>
                    </template>
                  </Column>
                </DataTable>
              </template>
            </Card>
          </div>
        </TabPanel>

        <!-- Performance Tab -->
        <TabPanel value="performance">
          <div class="space-y-6">
            <!-- Success Rate -->
            <Card class="bg-white dark:bg-slate-800">
              <template #title>Tỷ lệ thành công</template>
              <template #content>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                  <div class="text-center">
                    <div class="text-4xl font-bold text-green-600">
                      {{ formatPercentage(currentReportData.success_rate?.rate) }}
                    </div>
                    <div class="text-slate-600">Tỷ lệ thành công</div>
                    <div class="text-sm text-slate-500 mt-1">
                      {{ currentReportData.success_rate?.successful }} / {{ currentReportData.success_rate?.total }} transfers
                    </div>
                  </div>

                  <div class="text-center">
                    <div class="text-4xl font-bold text-blue-600">
                      {{ formatNumber(currentReportData.processing_time?.average_hours) }}h
                    </div>
                    <div class="text-slate-600">Thời gian xử lý TB</div>
                    <div class="text-sm text-slate-500 mt-1">
                      {{ currentReportData.processing_time?.min_hours }}h - {{ currentReportData.processing_time?.max_hours }}h
                    </div>
                  </div>

                  <div class="text-center">
                    <div class="text-4xl font-bold text-purple-600">
                      {{ formatNumber(currentReportData.user_performance?.length) }}
                    </div>
                    <div class="text-slate-600">Người xử lý</div>
                    <div class="text-sm text-slate-500 mt-1">
                      Đang hoạt động
                    </div>
                  </div>
                </div>
              </template>
            </Card>

            <!-- User Performance Chart -->
            <Card class="bg-white dark:bg-slate-800">
              <template #title>Hiệu suất theo người dùng</template>
              <template #content>
                <div class="h-80">
                  <Chart
                    type="bar"
                    :data="performanceChartData"
                    :options="chartOptions"
                    class="h-full"
                  />
                </div>
              </template>
            </Card>

            <!-- User Performance Table -->
            <Card class="bg-white dark:bg-slate-800">
              <template #title>Chi tiết hiệu suất</template>
              <template #content>
                <DataTable
                  :value="currentReportData.user_performance || []"
                  :paginator="false"
                  stripedRows
                  size="small"
                >
                  <Column field="name" header="Người dùng" :sortable="true" />
                  <Column field="total_transfers" header="Tổng transfers" :sortable="true">
                    <template #body="{ data }">
                      <span class="font-medium">{{ data.total_transfers }}</span>
                    </template>
                  </Column>
                  <Column field="successful" header="Thành công" :sortable="true">
                    <template #body="{ data }">
                      <span class="text-green-600 font-medium">{{ data.successful }}</span>
                    </template>
                  </Column>
                  <Column field="success_rate" header="Tỷ lệ %" :sortable="true">
                    <template #body="{ data }">
                      <div class="flex items-center gap-3">
                        <div class="flex-1 min-w-0">
                          <ProgressBar
                            :value="Math.min(data.success_rate || 0, 100)"
                            class="w-full h-3"
                            :showValue="false"
                          />
                        </div>
                        <div class="flex-shrink-0">
                          <span class="font-medium text-sm text-blue-600 min-w-[50px] text-right">
                            {{ (data.success_rate || 0).toFixed(1) }}%
                          </span>
                        </div>
                      </div>
                    </template>
                  </Column>
                </DataTable>
              </template>
            </Card>
          </div>
        </TabPanel>

        <!-- Detailed Tab -->
        <TabPanel value="detailed">
          <Card class="bg-white dark:bg-slate-800">
            <template #title>Transfers chi tiết</template>
            <template #content>
              <DataTable
                :value="currentReportData.transfers || []"
                :paginator="true"
                :rows="20"
                stripedRows
                size="small"
                class="p-datatable-sm"
                paginatorTemplate="RowsPerPageDropdown FirstPageLink PrevPageLink PageLinks NextPageLink LastPageLink CurrentPageReport"
                currentPageReportTemplate="Hiển thị {first} - {last} trên tổng số {totalRecords} bản ghi"
                :rowsPerPageOptions="[10, 15, 25]"
              >
                <Column field="id" header="ID" style="width: 60px" />
                <Column field="student.code" header="Mã HV" />
                <Column field="student.name" header="Học viên" />
                <Column field="from_class.code" header="Từ lớp" />
                <Column field="to_class.code" header="Đến lớp" />
                <Column field="status" header="Trạng thái" />
                <Column field="transfer_fee" header="Phí">
                  <template #body="{ data }">
                    {{ formatCurrency(data.transfer_fee) }}
                  </template>
                </Column>
                <Column field="created_at" header="Ngày tạo">
                  <template #body="{ data }">
                    {{ new Date(data.created_at).toLocaleDateString('vi-VN') }}
                  </template>
                </Column>
              </DataTable>
            </template>
          </Card>
        </TabPanel>
        </TabPanels>
      </Tabs>
    </div>

    <!-- Empty State -->
    <Card v-else class="bg-white dark:bg-slate-800">
      <template #content>
        <div class="text-center py-12 text-slate-500">
          <i class="pi pi-chart-bar text-6xl mb-4"></i>
          <div class="text-xl font-medium">Chọn tab báo cáo và điều chỉnh tham số</div>
          <div class="text-sm mt-2">Nhấn vào các tab ở trên để xem các loại báo cáo khác nhau</div>
        </div>
      </template>
    </Card>
  </div>
</template>
