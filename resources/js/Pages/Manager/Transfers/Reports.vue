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

const isLoading = ref(false)

// Chart configurations
const chartOptions = {
  responsive: true,
  maintainAspectRatio: false,
  plugins: {
    legend: {
      position: 'bottom'
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

const doughnutOptions = {
  responsive: true,
  maintainAspectRatio: false,
  plugins: {
    legend: {
      position: 'right'
    }
  }
}

// Computed
const summaryData = computed(() => props.reportData?.totals || {})
const trendsData = computed(() => props.reportData?.trends || [])
const chartData = computed(() => props.reportData?.chart_data || { labels: [], datasets: [] })

const performanceChartData = computed(() => {
  if (!props.reportData?.user_performance) return { labels: [], datasets: [] }

  const users = props.reportData.user_performance
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
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4">
          <div>
            <label class="block text-sm font-medium mb-1">Loại báo cáo</label>
            <Select
              v-model="filters.report_type"
              :options="reportOptions.reportTypes"
              optionLabel="label"
              optionValue="value"
              class="w-full"
            />
          </div>

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

          <div v-if="filters.report_type === 'trends'">
            <label class="block text-sm font-medium mb-1">Nhóm theo</label>
            <Select
              v-model="filters.group_by"
              :options="reportOptions.groupOptions"
              optionLabel="label"
              optionValue="value"
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
    <div v-if="reportData">
      <Tabs :value="filters.report_type || 'summary'">
        <TabList>
          <Tab value="summary">Tổng quan</Tab>
          <Tab value="trends">Xu hướng</Tab>
          <Tab value="performance">Hiệu suất</Tab>
          <Tab value="detailed">Chi tiết</Tab>
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
                        labels: reportData.by_source?.map(s => s.source_system) || [],
                        datasets: [{
                          data: reportData.by_source?.map(s => s.count) || [],
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
                    :value="reportData.top_reasons || []"
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
            <Card class="bg-white dark:bg-slate-800">
              <template #title>Xu hướng chuyển lớp theo {{ filters.group_by }}</template>
              <template #content>
                <div class="h-96">
                  <Chart
                    type="line"
                    :data="chartData"
                    :options="chartOptions"
                    class="h-full"
                  />
                </div>
              </template>
            </Card>

            <!-- Trends Table -->
            <Card class="bg-white dark:bg-slate-800">
              <template #title>Chi tiết xu hướng</template>
              <template #content>
                <DataTable
                  :value="trendsData"
                  :paginator="false"
                  stripedRows
                  size="small"
                >
                  <Column field="period" header="Kỳ" :sortable="true" />
                  <Column field="total" header="Tổng" :sortable="true">
                    <template #body="{ data }">
                      <span class="font-medium">{{ data.total }}</span>
                    </template>
                  </Column>
                  <Column field="active" header="Hoạt động" :sortable="true">
                    <template #body="{ data }">
                      <span class="text-green-600 font-medium">{{ data.active }}</span>
                    </template>
                  </Column>
                  <Column field="reverted" header="Hoàn tác" :sortable="true">
                    <template #body="{ data }">
                      <span class="text-orange-600 font-medium">{{ data.reverted }}</span>
                    </template>
                  </Column>
                  <Column field="priority" header="Ưu tiên" :sortable="true">
                    <template #body="{ data }">
                      <span class="text-purple-600 font-medium">{{ data.priority }}</span>
                    </template>
                  </Column>
                  <Column field="total_fees" header="Tổng phí" :sortable="true">
                    <template #body="{ data }">
                      {{ formatCurrency(data.total_fees) }}
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
                      {{ formatPercentage(reportData.success_rate?.rate) }}
                    </div>
                    <div class="text-slate-600">Tỷ lệ thành công</div>
                    <div class="text-sm text-slate-500 mt-1">
                      {{ reportData.success_rate?.successful }} / {{ reportData.success_rate?.total }} transfers
                    </div>
                  </div>

                  <div class="text-center">
                    <div class="text-4xl font-bold text-blue-600">
                      {{ formatNumber(reportData.processing_time?.average_hours) }}h
                    </div>
                    <div class="text-slate-600">Thời gian xử lý TB</div>
                    <div class="text-sm text-slate-500 mt-1">
                      {{ reportData.processing_time?.min_hours }}h - {{ reportData.processing_time?.max_hours }}h
                    </div>
                  </div>

                  <div class="text-center">
                    <div class="text-4xl font-bold text-purple-600">
                      {{ formatNumber(reportData.user_performance?.length) }}
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
                  :value="reportData.user_performance || []"
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
                :value="reportData.transfers || []"
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
          <div class="text-xl font-medium">Chọn tham số và tạo báo cáo</div>
          <div class="text-sm mt-2">Điều chỉnh các bộ lọc và nhấn "Tạo báo cáo" để xem dữ liệu</div>
        </div>
      </template>
    </Card>
  </div>
</template>
