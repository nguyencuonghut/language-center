<template>
  <Head title="Báo cáo Lớp & Học viên" />
  <AppLayout>
    <div class="p-3 md:p-5 space-y-6">
      <!-- Page header -->
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
          <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
            Báo cáo Lớp & Học viên
          </h1>
          <p class="text-gray-600 dark:text-gray-400">
            Thống kê học viên và tình hình các lớp học
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
          label="Lớp đang mở"
          :value="kpi.classes_open.total"
          icon="pi pi-home"
          color="green"
        />

        <KPICard
          label="Lớp đã đóng"
          :value="kpi.classes_closed.total"
          icon="pi pi-lock"
          color="red"
        />

        <KPICard
          label="Học viên mới"
          :value="kpi.new_enrollments.total"
          icon="pi pi-user-plus"
          color="blue"
        />

        <KPICard
          label="Tỷ lệ hoàn thành"
          :value="kpi.completion_rate.rate"
          icon="pi pi-check-circle"
          color="green"
          format="percentage"
        />
      </div>

      <!-- Secondary KPI row -->
      <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <KPICard
          label="Tỷ lệ bỏ học"
          :value="kpi.dropout_rate.rate"
          icon="pi pi-times-circle"
          color="red"
          format="percentage"
        />

        <KPICard
          label="Tỷ lệ chuyển lớp"
          :value="kpi.transfer_rate.rate"
          icon="pi pi-refresh"
          color="yellow"
          format="percentage"
        />

        <div class="flex items-center justify-center">
          <Card class="w-full">
            <template #content>
              <div class="text-center">
                <div class="text-lg font-semibold text-gray-700 dark:text-gray-300">
                  Xu hướng tổng thể
                </div>
                <div class="flex justify-center mt-2">
                  <div
                    class="px-3 py-1 rounded-full text-sm font-medium"
                    :class="overallTrendClass"
                  >
                    {{ overallTrend }}
                  </div>
                </div>
              </div>
            </template>
          </Card>
        </div>
      </div>

      <!-- Charts section -->
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Enrollment by course chart -->
        <Card>
          <template #title>Ghi danh theo khóa học</template>
          <template #content>
            <div class="h-80">
              <Chart
                type="bar"
                :data="enrollmentByCourseData"
                :options="stackedBarChartOptions"
                class="h-full"
              />
            </div>
          </template>
        </Card>

        <!-- Top lớp theo số học viên -->
        <Card>
          <template #title>Top 10 lớp đông học viên</template>
          <template #content>
            <div class="h-80">
              <Chart
                type="bar"
                :data="topClassesData"
                :options="horizontalBarChartOptions"
                class="h-full"
              />
            </div>
          </template>
        </Card>

        <!-- Enrollment status distribution -->
        <Card>
          <template #title>Phân bố trạng thái học viên</template>
          <template #content>
            <div class="h-80">
              <Chart
                type="doughnut"
                :data="enrollmentStatusData"
                :options="pieChartOptions"
                class="h-full"
              />
            </div>
          </template>
        </Card>

        <!-- Statistics summary -->
        <Card>
          <template #title>Thống kê chi tiết</template>
          <template #content>
            <div class="space-y-4">
              <div class="grid grid-cols-2 gap-4">
                <div class="text-center p-4 bg-blue-50 dark:bg-blue-900/20 rounded-lg">
                  <div class="text-2xl font-bold text-blue-600 dark:text-blue-400">
                    {{ (kpi.classes_open.total + kpi.classes_closed.total) }}
                  </div>
                  <div class="text-sm text-blue-600 dark:text-blue-400">Tổng số lớp</div>
                </div>
                <div class="text-center p-4 bg-green-50 dark:bg-green-900/20 rounded-lg">
                  <div class="text-2xl font-bold text-green-600 dark:text-green-400">
                    {{ Math.round((kpi.classes_open.total / (kpi.classes_open.total + kpi.classes_closed.total)) * 100) }}%
                  </div>
                  <div class="text-sm text-green-600 dark:text-green-400">Lớp đang hoạt động</div>
                </div>
              </div>

              <Divider />

              <div class="space-y-3">
                <div class="flex justify-between items-center">
                  <span class="text-gray-600 dark:text-gray-400">Học viên hoàn thành</span>
                  <div class="flex items-center gap-2">
                    <div class="w-16 bg-gray-200 dark:bg-gray-700 rounded-full h-2">
                      <div
                        class="bg-green-500 h-2 rounded-full"
                        :style="{ width: kpi.completion_rate.rate + '%' }"
                      ></div>
                    </div>
                    <span class="text-sm font-medium">{{ kpi.completion_rate.rate }}%</span>
                  </div>
                </div>

                <div class="flex justify-between items-center">
                  <span class="text-gray-600 dark:text-gray-400">Học viên chuyển lớp</span>
                  <div class="flex items-center gap-2">
                    <div class="w-16 bg-gray-200 dark:bg-gray-700 rounded-full h-2">
                      <div
                        class="bg-yellow-500 h-2 rounded-full"
                        :style="{ width: kpi.transfer_rate.rate + '%' }"
                      ></div>
                    </div>
                    <span class="text-sm font-medium">{{ kpi.transfer_rate.rate }}%</span>
                  </div>
                </div>

                <div class="flex justify-between items-center">
                  <span class="text-gray-600 dark:text-gray-400">Học viên bỏ học</span>
                  <div class="flex items-center gap-2">
                    <div class="w-16 bg-gray-200 dark:bg-gray-700 rounded-full h-2">
                      <div
                        class="bg-red-500 h-2 rounded-full"
                        :style="{ width: kpi.dropout_rate.rate + '%' }"
                      ></div>
                    </div>
                    <span class="text-sm font-medium">{{ kpi.dropout_rate.rate }}%</span>
                  </div>
                </div>
              </div>
            </div>
          </template>
        </Card>
      </div>

      <!-- Data tables -->
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Bảng tổng hợp lớp học -->
        <Card>
          <template #title>
            <div class="flex items-center justify-between">
              <span>Tổng hợp lớp học</span>
              <div class="flex items-center gap-2">
                <IconField iconPosition="left">
                  <InputIcon class="pi pi-search" />
                  <InputText
                    v-model="classSearchQuery"
                    placeholder="Tìm lớp..."
                    size="small"
                  />
                </IconField>
              </div>
            </div>
          </template>
          <template #content>
            <DataTable
              :value="filteredClassesData"
              :paginator="true"
              :rows="10"
              paginator-template="FirstPageLink PrevPageLink PageLinks NextPageLink LastPageLink"
              class="p-datatable-sm"
              :loading="loading"
            >
              <Column field="code" header="Mã lớp" sortable />
              <Column field="name" header="Tên lớp" sortable />
              <Column field="course" header="Khóa học" sortable />
              <Column field="student_count" header="Sĩ số" sortable />
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
                  <i class="pi pi-inbox text-4xl text-gray-400 mb-4"></i>
                  <p class="text-gray-500">Không tìm thấy lớp học</p>
                </div>
              </template>
            </DataTable>
          </template>
        </Card>

        <!-- New enrollments table -->
        <Card>
          <template #title>
            <div class="flex items-center justify-between">
              <span>Học viên mới ghi danh</span>
              <div class="flex items-center gap-2">
                <IconField iconPosition="left">
                  <InputIcon class="pi pi-search" />
                  <InputText
                    v-model="enrollmentSearchQuery"
                    placeholder="Tìm học viên..."
                    size="small"
                  />
                </IconField>
              </div>
            </div>
          </template>
          <template #content>
            <DataTable
              :value="filteredEnrollmentsData"
              :paginator="true"
              :rows="10"
              paginator-template="FirstPageLink PrevPageLink PageLinks NextPageLink LastPageLink"
              class="p-datatable-sm"
              :loading="loading"
            >
              <Column field="student_code" header="Mã HV" sortable />
              <Column field="student_name" header="Tên học viên" sortable />
              <Column field="class" header="Lớp" sortable />
              <Column field="branch" header="Chi nhánh" sortable />
              <Column field="enrolled_at" header="Ngày GD" sortable />

              <template #empty>
                <div class="text-center py-8">
                  <i class="pi pi-user-plus text-4xl text-gray-400 mb-4"></i>
                  <p class="text-gray-500">Chưa có học viên mới</p>
                </div>
              </template>
            </DataTable>
          </template>
        </Card>
      </div>
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
import Divider from 'primevue/divider'

const props = defineProps({
  filters: Object,
  filterOptions: Object,
  kpi: Object,
  charts: Object,
  tables: Object
})

const loading = ref(false)
const classSearchQuery = ref('')
const enrollmentSearchQuery = ref('')

// Overall trend calculation
const overallTrend = computed(() => {
  const completion = props.kpi?.completion_rate?.rate || 0
  const dropout = props.kpi?.dropout_rate?.rate || 0

  if (completion > 80) return 'Tích cực'
  if (dropout > 20) return 'Cần cải thiện'
  return 'Ổn định'
})

const overallTrendClass = computed(() => {
  const trend = overallTrend.value
  if (trend === 'Tích cực') return 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400'
  if (trend === 'Cần cải thiện') return 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400'
  return 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400'
})

// Chart data
const enrollmentByCourseData = computed(() => {
  const data = props.charts?.enrollments_by_course || []
  if (data.length === 0) return { labels: [], datasets: [] }

  const labels = data.map(item => item.period)
  const courses = new Set()
  data.forEach(item => {
    Object.keys(item).forEach(key => {
      if (key !== 'period') courses.add(key)
    })
  })

  const colors = ['#10b981', '#3b82f6', '#f59e0b', '#ef4444', '#8b5cf6']
  const datasets = Array.from(courses).map((course, index) => ({
    label: course,
    data: data.map(item => item[course] || 0),
    backgroundColor: colors[index % colors.length]
  }))

  return { labels, datasets }
})

const topClassesData = computed(() => {
  const data = props.charts?.top_classes_by_students || []
  return {
    labels: data.map(item => item.name),
    datasets: [{
      label: 'Số học viên',
      data: data.map(item => item.value),
      backgroundColor: '#10b981'
    }]
  }
})

const enrollmentStatusData = computed(() => {
  const data = props.charts?.enrollment_status_distribution || []
  return {
    labels: data.map(item => item.name),
    datasets: [{
      data: data.map(item => item.value),
      backgroundColor: ['#10b981', '#f59e0b', '#ef4444', '#3b82f6']
    }]
  }
})

// Chart options
const stackedBarChartOptions = ref({
  responsive: true,
  maintainAspectRatio: false,
  plugins: {
    legend: {
      position: 'bottom'
    }
  },
  scales: {
    x: { stacked: true },
    y: { stacked: true, beginAtZero: true }
  }
})

const horizontalBarChartOptions = ref({
  responsive: true,
  maintainAspectRatio: false,
  indexAxis: 'y',
  plugins: {
    legend: { display: false }
  },
  scales: {
    x: { beginAtZero: true }
  }
})

const pieChartOptions = ref({
  responsive: true,
  maintainAspectRatio: false,
  plugins: {
    legend: { position: 'bottom' }
  }
})

// Table data
const filteredClassesData = computed(() => {
  const data = props.tables?.classes_summary || []
  if (!classSearchQuery.value) return data

  const query = classSearchQuery.value.toLowerCase()
  return data.filter(item =>
    item.code?.toLowerCase().includes(query) ||
    item.name?.toLowerCase().includes(query) ||
    item.course?.toLowerCase().includes(query)
  )
})

const filteredEnrollmentsData = computed(() => {
  const data = props.tables?.new_enrollments || []
  if (!enrollmentSearchQuery.value) return data

  const query = enrollmentSearchQuery.value.toLowerCase()
  return data.filter(item =>
    item.student_code?.toLowerCase().includes(query) ||
    item.student_name?.toLowerCase().includes(query) ||
    item.class?.toLowerCase().includes(query)
  )
})

// Methods
const getStatusSeverity = (status) => {
  const statusMap = {
    'open': 'success',
    'active': 'success',
    'closed': 'danger',
    'completed': 'info'
  }
  return statusMap[status] || 'secondary'
}

const applyFilters = (filterData) => {
  loading.value = true
  router.get(route('admin.reports.students-classes'), filterData, {
    preserveState: true,
    preserveScroll: true,
    onFinish: () => loading.value = false
  })
}

const resetFilters = () => {
  loading.value = true
  router.get(route('admin.reports.students-classes'), {}, {
    preserveState: true,
    preserveScroll: true,
    onFinish: () => loading.value = false
  })
}

const exportCSV = () => {
  console.log('Export CSV functionality to be implemented')
}
</script>
