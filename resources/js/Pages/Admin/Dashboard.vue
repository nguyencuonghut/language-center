<script setup>
import { Head, Link } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import { computed } from 'vue'

// PrimeVue
import Card from 'primevue/card'
import Tag from 'primevue/tag'
import Button from 'primevue/button'
import Chart from 'primevue/chart'
import Badge from 'primevue/badge'
import Panel from 'primevue/panel'

defineOptions({ layout: AppLayout })

const props = defineProps({
  kpi: Object,       // KPI data with growth
  charts: Object,    // Charts data
  recent: Object,    // Recent activities
  alerts: Object,    // Alert counts
  meta: Object       // Metadata
})

// Formatters
function fmtCurrency(v) {
  try {
    return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(v || 0)
  } catch {
    return v
  }
}

function fmtNumber(v) {
  try {
    return new Intl.NumberFormat('vi-VN').format(v || 0)
  } catch {
    return v
  }
}

function fmtDate(d) {
  if (!d) return '—'
  const dt = new Date(d)
  return dt.toLocaleDateString('vi-VN')
}

function fmtGrowth(growth) {
  if (!growth) return ''
  const sign = growth > 0 ? '+' : ''
  return `${sign}${growth}%`
}

function getGrowthClass(growth) {
  if (growth > 0) return 'text-green-600'
  if (growth < 0) return 'text-red-600'
  return 'text-gray-500'
}

function getGrowthIcon(growth) {
  if (growth > 0) return 'pi pi-arrow-up'
  if (growth < 0) return 'pi pi-arrow-down'
  return 'pi pi-minus'
}

const statusSeverity = (s) => {
  switch (s) {
    case 'paid': return 'success'
    case 'partial': return 'warn'
    case 'refunded': return 'info'
    default: return 'danger' // unpaid
  }
}

// Chart configurations
const revenueChartData = computed(() => ({
  labels: props.charts?.revenue_monthly?.map(item => item.month) || [],
  datasets: [{
    label: 'Doanh thu',
    data: props.charts?.revenue_monthly?.map(item => item.value) || [],
    borderColor: '#10b981',
    backgroundColor: 'rgba(16, 185, 129, 0.1)',
    tension: 0.4,
    fill: true
  }]
}))

const revenueChartOptions = {
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
            style: 'currency',
            currency: 'VND',
            notation: 'compact'
          }).format(value)
        }
      }
    }
  }
}

const branchChartData = computed(() => ({
  labels: props.charts?.students_by_branch?.map(item => item.name) || [],
  datasets: [{
    data: props.charts?.students_by_branch?.map(item => item.value) || [],
    backgroundColor: [
      '#10b981',
      '#f59e0b',
      '#ef4444',
      '#8b5cf6',
      '#06b6d4',
      '#f97316'
    ]
  }]
}))

// Computed để tính tổng số học viên từ chart data
const totalStudentsFromChart = computed(() => {
  return props.charts?.students_by_branch?.reduce((sum, item) => sum + (item.value || 0), 0) || 0
})

// Computed để tính tổng lượt đăng ký
const totalEnrollmentsFromChart = computed(() => {
  return props.charts?.enrollments_by_branch?.reduce((sum, item) => sum + (item.value || 0), 0) || 0
})

const branchChartOptions = {
  responsive: true,
  maintainAspectRatio: false,
  plugins: {
    legend: {
      position: 'bottom'
    }
  }
}

const enrollmentChartData = computed(() => ({
  labels: props.charts?.enrollment_trend?.map(item => item.month) || [],
  datasets: [{
    label: 'Đăng ký mới',
    data: props.charts?.enrollment_trend?.map(item => item.value) || [],
    backgroundColor: '#10b981',
    borderColor: '#10b981',
    borderWidth: 1
  }]
}))

const enrollmentChartOptions = {
  responsive: true,
  maintainAspectRatio: false,
  plugins: {
    legend: {
      display: false
    }
  },
  scales: {
    y: {
      beginAtZero: true
    }
  }
}
</script>

<template>
  <Head title="Dashboard" />

  <div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
      <div>
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Dashboard Admin</h1>
        <p class="text-gray-600 dark:text-gray-400">Tổng quan hoạt động trung tâm</p>
      </div>

      <!-- Quick Actions -->
      <div class="flex flex-wrap gap-2">
        <Link :href="route('admin.invoices.create')">
          <Button label="Tạo HĐ" icon="pi pi-plus" size="small" />
        </Link>
        <Link :href="route('manager.students.create')">
          <Button label="Học viên" icon="pi pi-user-plus" size="small" severity="secondary" />
        </Link>
        <Link :href="route('manager.classrooms.create')">
          <Button label="Lớp học" icon="pi pi-users" size="small" severity="secondary" />
        </Link>
      </div>
    </div>

    <!-- Alerts -->
    <div v-if="alerts?.overdue_invoices > 0 || alerts?.full_classes > 0" class="grid grid-cols-1 md:grid-cols-3 gap-4">
      <Card v-if="alerts?.overdue_invoices > 0" class="bg-red-50 dark:bg-red-900/20 border-red-200 dark:border-red-800">
        <template #content>
          <div class="flex items-center gap-3">
            <i class="pi pi-exclamation-triangle text-red-500 text-xl"></i>
            <div>
              <div class="font-semibold text-red-700 dark:text-red-300">{{ alerts.overdue_invoices }} hóa đơn quá hạn</div>
              <div class="text-sm text-red-600 dark:text-red-400">Cần xử lý ngay</div>
            </div>
          </div>
        </template>
      </Card>

      <Card v-if="alerts?.full_classes > 0" class="bg-orange-50 dark:bg-orange-900/20 border-orange-200 dark:border-orange-800">
        <template #content>
          <div class="flex items-center gap-3">
            <i class="pi pi-users text-orange-500 text-xl"></i>
            <div>
              <div class="font-semibold text-orange-700 dark:text-orange-300">{{ alerts.full_classes }} lớp sắp đầy</div>
              <div class="text-sm text-orange-600 dark:text-orange-400">Cân nhắc mở lớp mới</div>
            </div>
          </div>
        </template>
      </Card>

      <Card v-if="alerts?.new_reports > 0" class="bg-blue-50 dark:bg-blue-900/20 border-blue-200 dark:border-blue-800">
        <template #content>
          <div class="flex items-center gap-3">
            <i class="pi pi-chart-bar text-blue-500 text-xl"></i>
            <div>
              <div class="font-semibold text-blue-700 dark:text-blue-300">Báo cáo mới</div>
              <div class="text-sm text-blue-600 dark:text-blue-400">Báo cáo tháng đã sẵn sàng</div>
            </div>
          </div>
        </template>
      </Card>
    </div>

    <!-- KPI Cards - Main -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
      <Card>
        <template #content>
          <div class="flex items-center justify-between">
            <div>
              <div class="text-2xl font-bold text-gray-900 dark:text-white">{{ fmtNumber(kpi?.students?.total) }}</div>
              <div class="text-sm font-medium text-gray-600 dark:text-gray-400">Học viên đang học</div>
              <div v-if="kpi?.students?.growth" :class="['text-xs flex items-center gap-1', getGrowthClass(kpi.students.growth)]">
                <i :class="getGrowthIcon(kpi.students.growth)"></i>
                {{ fmtGrowth(kpi.students.growth) }}
              </div>
            </div>
            <div class="w-12 h-12 bg-blue-100 dark:bg-blue-900/30 rounded-lg flex items-center justify-center">
              <i class="pi pi-users text-blue-500 text-xl"></i>
            </div>
          </div>
        </template>
      </Card>

      <Card>
        <template #content>
          <div class="flex items-center justify-between">
            <div>
              <div class="text-2xl font-bold text-gray-900 dark:text-white">{{ fmtNumber(kpi?.classes?.total) }}</div>
              <div class="text-sm font-medium text-gray-600 dark:text-gray-400">Lớp đang mở</div>
              <div v-if="kpi?.classes?.growth" :class="['text-xs flex items-center gap-1', getGrowthClass(kpi.classes.growth)]">
                <i :class="getGrowthIcon(kpi.classes.growth)"></i>
                {{ fmtGrowth(kpi.classes.growth) }}
              </div>
            </div>
            <div class="w-12 h-12 bg-green-100 dark:bg-green-900/30 rounded-lg flex items-center justify-center">
              <i class="pi pi-graduation-cap text-green-500 text-xl"></i>
            </div>
          </div>
        </template>
      </Card>

      <Card>
        <template #content>
          <div class="flex items-center justify-between">
            <div>
              <div class="text-2xl font-bold text-gray-900 dark:text-white">{{ fmtNumber(kpi?.teachers?.total) }}</div>
              <div class="text-sm font-medium text-gray-600 dark:text-gray-400">Giáo viên</div>
              <div v-if="kpi?.teachers?.growth" :class="['text-xs flex items-center gap-1', getGrowthClass(kpi.teachers.growth)]">
                <i :class="getGrowthIcon(kpi.teachers.growth)"></i>
                {{ fmtGrowth(kpi.teachers.growth) }}
              </div>
            </div>
            <div class="w-12 h-12 bg-purple-100 dark:bg-purple-900/30 rounded-lg flex items-center justify-center">
              <i class="pi pi-id-card text-purple-500 text-xl"></i>
            </div>
          </div>
        </template>
      </Card>

      <Card>
        <template #content>
          <div class="flex items-center justify-between">
            <div>
              <div class="text-2xl font-bold text-gray-900 dark:text-white">{{ fmtNumber(kpi?.branches?.total) }}</div>
              <div class="text-sm font-medium text-gray-600 dark:text-gray-400">Chi nhánh</div>
            </div>
            <div class="w-12 h-12 bg-orange-100 dark:bg-orange-900/30 rounded-lg flex items-center justify-center">
              <i class="pi pi-building text-orange-500 text-xl"></i>
            </div>
          </div>
        </template>
      </Card>
    </div>

    <!-- KPI Cards - Financial -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
      <Card>
        <template #content>
          <div class="flex items-center justify-between">
            <div>
              <div class="text-xl font-bold text-gray-900 dark:text-white">{{ fmtCurrency(kpi?.revenue_month?.total) }}</div>
              <div class="text-sm font-medium text-gray-600 dark:text-gray-400">Doanh thu tháng</div>
              <div v-if="kpi?.revenue_month?.growth" :class="['text-xs flex items-center gap-1', getGrowthClass(kpi.revenue_month.growth)]">
                <i :class="getGrowthIcon(kpi.revenue_month.growth)"></i>
                {{ fmtGrowth(kpi.revenue_month.growth) }}
              </div>
            </div>
            <div class="w-12 h-12 bg-green-100 dark:bg-green-900/30 rounded-lg flex items-center justify-center">
              <i class="pi pi-chart-line text-green-500 text-xl"></i>
            </div>
          </div>
        </template>
      </Card>

      <Card>
        <template #content>
          <div class="flex items-center justify-between">
            <div>
              <div class="text-xl font-bold text-gray-900 dark:text-white">{{ fmtCurrency(kpi?.revenue_total?.total) }}</div>
              <div class="text-sm font-medium text-gray-600 dark:text-gray-400">Tổng doanh thu</div>
              <div v-if="kpi?.revenue_total?.growth" :class="['text-xs flex items-center gap-1', getGrowthClass(kpi.revenue_total.growth)]">
                <i :class="getGrowthIcon(kpi.revenue_total.growth)"></i>
                {{ fmtGrowth(kpi.revenue_total.growth) }}
              </div>
            </div>
            <div class="w-12 h-12 bg-blue-100 dark:bg-blue-900/30 rounded-lg flex items-center justify-center">
              <i class="pi pi-wallet text-blue-500 text-xl"></i>
            </div>
          </div>
        </template>
      </Card>

      <Card>
        <template #content>
          <div class="flex items-center justify-between">
            <div>
              <div class="text-xl font-bold text-gray-900 dark:text-white">{{ fmtCurrency(kpi?.outstanding?.total) }}</div>
              <div class="text-sm font-medium text-gray-600 dark:text-gray-400">Công nợ</div>
              <div v-if="kpi?.outstanding?.growth" :class="['text-xs flex items-center gap-1', getGrowthClass(kpi.outstanding.growth)]">
                <i :class="getGrowthIcon(kpi.outstanding.growth)"></i>
                {{ fmtGrowth(kpi.outstanding.growth) }}
              </div>
            </div>
            <div class="w-12 h-12 bg-red-100 dark:bg-red-900/30 rounded-lg flex items-center justify-center">
              <i class="pi pi-exclamation-circle text-red-500 text-xl"></i>
            </div>
          </div>
        </template>
      </Card>

      <Card>
        <template #content>
          <div class="flex items-center justify-between">
            <div>
              <div class="text-xl font-bold text-gray-900 dark:text-white">{{ kpi?.collection_rate?.rate }}%</div>
              <div class="text-sm font-medium text-gray-600 dark:text-gray-400">Tỷ lệ thu hồi</div>
              <div v-if="kpi?.collection_rate?.growth" :class="['text-xs flex items-center gap-1', getGrowthClass(kpi.collection_rate.growth)]">
                <i :class="getGrowthIcon(kpi.collection_rate.growth)"></i>
                {{ fmtGrowth(kpi.collection_rate.growth) }}
              </div>
            </div>
            <div class="w-12 h-12 bg-teal-100 dark:bg-teal-900/30 rounded-lg flex items-center justify-center">
              <i class="pi pi-percentage text-teal-500 text-xl"></i>
            </div>
          </div>
        </template>
      </Card>
    </div>

    <!-- Charts -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
      <!-- Revenue Chart -->
      <div class="lg:col-span-2">
        <Card>
          <template #title>Doanh thu 12 tháng gần đây</template>
          <template #content>
            <div class="h-80">
              <Chart type="line" :data="revenueChartData" :options="revenueChartOptions" class="w-full h-full" />
            </div>
          </template>
        </Card>
      </div>

      <!-- Students by Branch -->
      <Card>
        <template #title>
          <div class="flex flex-col">
            <span>Học viên theo chi nhánh</span>
            <span class="text-sm text-gray-500 font-normal">
              {{ fmtNumber(totalStudentsFromChart) }} học viên duy nhất
              ({{ fmtNumber(totalEnrollmentsFromChart) }} lượt đăng ký)
            </span>
          </div>
        </template>
        <template #content>
          <div class="h-80">
            <Chart type="doughnut" :data="branchChartData" :options="branchChartOptions" class="w-full h-full" />
          </div>
        </template>
      </Card>
    </div>

    <!-- Enrollment Trend -->
    <Card>
      <template #title>Xu hướng đăng ký 6 tháng gần đây</template>
      <template #content>
        <div class="h-60">
          <Chart type="bar" :data="enrollmentChartData" :options="enrollmentChartOptions" class="w-full h-full" />
        </div>
      </template>
    </Card>

    <!-- Recent Activities -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
      <!-- Recent Invoices -->
      <Card>
        <template #title>
          <div class="flex items-center justify-between">
            <span>Hóa đơn gần đây</span>
            <Link :href="route('admin.invoices.index')">
              <Button label="Xem tất cả" link size="small" />
            </Link>
          </div>
        </template>
        <template #content>
          <div class="space-y-3">
            <div v-for="invoice in recent?.invoices" :key="invoice.id" class="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-800 rounded-lg">
              <div class="flex-1 min-w-0">
                <div class="text-sm font-medium text-gray-900 dark:text-white truncate">
                  {{ invoice.student?.name }}
                </div>
                <div class="text-xs text-gray-500 dark:text-gray-400">
                  {{ invoice.code || ('#' + invoice.id) }}
                </div>
              </div>
              <div class="flex flex-col items-end gap-1">
                <div class="text-sm font-semibold">{{ fmtCurrency(invoice.total) }}</div>
                <Tag :value="invoice.status" :severity="statusSeverity(invoice.status)" />
              </div>
            </div>
            <div v-if="!recent?.invoices?.length" class="text-center text-gray-500 dark:text-gray-400 py-4">
              Chưa có hóa đơn nào
            </div>
          </div>
        </template>
      </Card>

      <!-- Recent Transfers -->
      <Card>
        <template #title>
          <div class="flex items-center justify-between">
            <span>Chuyển lớp gần đây</span>
            <Link :href="route('manager.transfers.index')">
              <Button label="Xem tất cả" link size="small" />
            </Link>
          </div>
        </template>
        <template #content>
          <div class="space-y-3">
            <div v-for="transfer in recent?.transfers" :key="transfer.id" class="p-3 bg-gray-50 dark:bg-gray-800 rounded-lg">
              <div class="text-sm font-medium text-gray-900 dark:text-white mb-1">
                {{ transfer.student?.name }}
              </div>
              <div class="text-xs text-gray-500 dark:text-gray-400">
                {{ transfer.from_class?.name }} → {{ transfer.to_class?.name }}
              </div>
              <div class="text-xs text-gray-400 dark:text-gray-500 mt-1">
                {{ fmtDate(transfer.created_at) }}
              </div>
            </div>
            <div v-if="!recent?.transfers?.length" class="text-center text-gray-500 dark:text-gray-400 py-4">
              Chưa có chuyển lớp nào
            </div>
          </div>
        </template>
      </Card>

      <!-- Upcoming Classes -->
      <Card>
        <template #title>
          <div class="flex items-center justify-between">
            <span>Lớp sắp bắt đầu</span>
            <Link :href="route('manager.classrooms.index')">
              <Button label="Xem tất cả" link size="small" />
            </Link>
          </div>
        </template>
        <template #content>
          <div class="space-y-3">
            <div v-for="classroom in recent?.upcoming_classes" :key="classroom.id" class="p-3 bg-gray-50 dark:bg-gray-800 rounded-lg">
              <div class="text-sm font-medium text-gray-900 dark:text-white mb-1">
                {{ classroom.name }}
              </div>
              <div class="text-xs text-gray-500 dark:text-gray-400 mb-1">
                {{ classroom.course?.name }}
              </div>
              <div class="flex items-center justify-between">
                <div class="text-xs text-gray-400 dark:text-gray-500">
                  {{ fmtDate(classroom.start_date) }}
                </div>
                <Badge :value="classroom.sessions_total + ' buổi'" severity="info" />
              </div>
            </div>
            <div v-if="!recent?.upcoming_classes?.length" class="text-center text-gray-500 dark:text-gray-400 py-4">
              Không có lớp nào sắp bắt đầu
            </div>
          </div>
        </template>
      </Card>
    </div>
  </div>
</template>
