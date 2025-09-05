<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { Head } from '@inertiajs/vue3'
import { ref, computed, onMounted, nextTick } from 'vue'
import Card from 'primevue/card'
import Chart from 'primevue/chart'
import Tag from 'primevue/tag'

// Import Chart.js for direct usage if needed
import { Chart as ChartJS } from 'chart.js'

defineOptions({ layout: AppLayout })

const props = defineProps({
    kpi: Object,
    charts: Object,
    recent: Object,
    alerts: Object,
    meta: Object,
})

// Computed properties for alerts
const hasAlerts = computed(() => {
    return props.alerts.low_attendance_classes > 0 ||
           props.alerts.pending_transfers > 0 ||
           props.alerts.overdue_timesheets > 0
})

// Chart configurations
const enrollmentChartData = computed(() => {
    if (!props.charts?.enrollment_trend || props.charts.enrollment_trend.length === 0) {
        return {
            labels: [],
            datasets: []
        }
    }

    // Convert proxy objects to plain objects for Chart.js compatibility
    const data = JSON.parse(JSON.stringify(props.charts.enrollment_trend))

    return {
        labels: data.map(item => item.month),
        datasets: [
            {
                label: 'Số lượng đăng ký',
                data: data.map(item => item.value),
                fill: false,
                borderColor: '#3B82F6',
                backgroundColor: '#3B82F6',
                tension: 0.4,
                pointRadius: 6,
                pointHoverRadius: 8,
            }
        ]
    }
})

const chartOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: {
            display: true,
            position: 'top',
            labels: {
                usePointStyle: true,
                padding: 20
            }
        },
        tooltip: {
            backgroundColor: 'rgba(255, 255, 255, 0.95)',
            titleColor: '#1F2937',
            bodyColor: '#374151',
            borderColor: '#E5E7EB',
            borderWidth: 1,
            cornerRadius: 8,
            displayColors: true
        }
    },
    scales: {
        y: {
            beginAtZero: true,
            grid: {
                color: 'rgba(0, 0, 0, 0.1)'
            }
        },
        x: {
            grid: {
                color: 'rgba(0, 0, 0, 0.1)'
            }
        }
    }
}

const pieChartOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: {
            display: true,
            position: 'bottom',
            labels: {
                usePointStyle: true,
                padding: 15,
                generateLabels: function(chart) {
                    const data = chart.data;
                    if (data.labels.length && data.datasets.length) {
                        return data.labels.map((label, i) => {
                            return {
                                text: label,
                                fillStyle: data.datasets[0].backgroundColor[i],
                                hidden: false,
                                index: i
                            };
                        });
                    }
                    return [];
                }
            }
        },
        tooltip: {
            backgroundColor: 'rgba(255, 255, 255, 0.95)',
            titleColor: '#1F2937',
            bodyColor: '#374151',
            borderColor: '#E5E7EB',
            borderWidth: 1,
            cornerRadius: 8,
            callbacks: {
                label: function(context) {
                    const total = context.dataset.data.reduce((a, b) => a + b, 0);
                    const percentage = ((context.parsed / total) * 100).toFixed(1);
                    return `${context.label}: ${context.parsed} (${percentage}%)`;
                }
            }
        }
    }
}

const courseChartData = computed(() => {
    if (!props.charts?.students_by_course || props.charts.students_by_course.length === 0) {
        return {
            labels: [],
            datasets: []
        }
    }

    // Convert proxy objects to plain objects for Chart.js compatibility
    const data = JSON.parse(JSON.stringify(props.charts.students_by_course))

    return {
        labels: data.map(item => item.name),
        datasets: [
            {
                data: data.map(item => item.value),
                backgroundColor: [
                    '#3B82F6', '#10B981', '#F59E0B', '#EF4444', '#8B5CF6',
                    '#06B6D4', '#84CC16', '#F97316', '#EC4899', '#6B7280'
                ],
                hoverBackgroundColor: [
                    '#2563EB', '#059669', '#D97706', '#DC2626', '#7C3AED',
                    '#0891B2', '#65A30D', '#EA580C', '#DB2777', '#4B5563'
                ]
            }
        ]
    }
})

const courseChartOptions = ref({
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: {
            position: 'bottom',
            labels: {
                padding: 20,
                usePointStyle: true
            }
        }
    }
})

const attendanceChartData = computed(() => ({
    labels: props.charts.attendance_by_class.map(item => item.name),
    datasets: [
        {
            label: 'Tỷ lệ tham dự (%)',
            data: props.charts.attendance_by_class.map(item => item.rate),
            backgroundColor: props.charts.attendance_by_class.map(item =>
                item.rate >= 80 ? '#10B981' :
                item.rate >= 70 ? '#F59E0B' : '#EF4444'
            ),
            borderColor: '#E5E7EB',
            borderWidth: 1
        }
    ]
}))

const attendanceChartOptions = ref({
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
            max: 100,
            ticks: {
                callback: function(value) {
                    return value + '%'
                }
            }
        }
    }
})

// Helper functions
const formatNumber = (num) => {
    return new Intl.NumberFormat('vi-VN').format(num)
}

const formatCurrency = (amount) => {
    return new Intl.NumberFormat('vi-VN', {
        style: 'currency',
        currency: 'VND'
    }).format(amount)
}

const formatDate = (dateString) => {
    return new Intl.DateTimeFormat('vi-VN', {
        day: '2-digit',
        month: '2-digit',
        hour: '2-digit',
        minute: '2-digit'
    }).format(new Date(dateString))
}

const getTransferStatusText = (status) => {
    const statusMap = {
        'pending': 'Chờ duyệt',
        'approved': 'Đã duyệt',
        'rejected': 'Từ chối',
        'completed': 'Hoàn thành'
    }
    return statusMap[status] || status
}

const getTransferStatusSeverity = (status) => {
    const severityMap = {
        'pending': 'warning',
        'approved': 'info',
        'rejected': 'danger',
        'completed': 'success'
    }
    return severityMap[status] || 'secondary'
}

const getAttendanceColor = (present, total) => {
    if (total === 0) return 'text-gray-500'
    const rate = (present / total) * 100
    if (rate >= 80) return 'text-green-600 dark:text-green-400'
    if (rate >= 70) return 'text-yellow-600 dark:text-yellow-400'
    return 'text-red-600 dark:text-red-400'
}

const getAttendancePercentage = (present, total) => {
    return total > 0 ? Math.round((present / total) * 100) : 0
}

// Lifecycle
onMounted(() => {
    console.log('Manager Dashboard loaded successfully')
})
</script>

<template>
    <Head title="Manager Dashboard" />

    <div class="p-6">
        <!-- Header -->
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-gray-900 dark:text-white">
                Manager Dashboard
            </h1>
            <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                Quản lý chi nhánh: {{ meta.branch_names.join(', ') || 'Chưa được phân quyền' }}
            </p>
        </div>

        <!-- Alerts Row -->
        <div v-if="hasAlerts" class="mb-6 grid grid-cols-1 md:grid-cols-3 gap-4">
            <!-- Low Attendance Alert -->
            <div v-show="alerts.low_attendance_classes > 0" class="bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 rounded-lg p-4">
                <div class="flex items-center">
                    <i class="pi pi-exclamation-triangle text-yellow-600 dark:text-yellow-400 mr-3"></i>
                    <div>
                        <p class="text-sm font-medium text-yellow-800 dark:text-yellow-200">
                            {{ alerts.low_attendance_classes }} lớp có tỷ lệ tham dự thấp
                        </p>
                        <p class="text-xs text-yellow-600 dark:text-yellow-300">
                            Dưới 70% trong 7 ngày qua
                        </p>
                    </div>
                </div>
            </div>

            <!-- Pending Transfers Alert -->
            <div v-show="alerts.pending_transfers > 0" class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-4">
                <div class="flex items-center">
                    <i class="pi pi-clock text-blue-600 dark:text-blue-400 mr-3"></i>
                    <div>
                        <p class="text-sm font-medium text-blue-800 dark:text-blue-200">
                            {{ alerts.pending_transfers }} yêu cầu chuyển lớp
                        </p>
                        <p class="text-xs text-blue-600 dark:text-blue-300">
                            Cần xem xét và phê duyệt
                        </p>
                    </div>
                </div>
            </div>

            <!-- Overdue Timesheets Alert -->
            <div v-show="alerts.overdue_timesheets > 0" class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg p-4">
                <div class="flex items-center">
                    <i class="pi pi-calendar-times text-red-600 dark:text-red-400 mr-3"></i>
                    <div>
                        <p class="text-sm font-medium text-red-800 dark:text-red-200">
                            {{ alerts.overdue_timesheets }} bảng chấm công quá hạn
                        </p>
                        <p class="text-xs text-red-600 dark:text-red-300">
                            Chờ duyệt quá 3 ngày
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- KPI Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <!-- Students -->
            <Card>
                <template #content>
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-600 dark:text-gray-400">
                                Học viên đang học
                            </p>
                            <p class="text-3xl font-bold text-gray-900 dark:text-white">
                                {{ formatNumber(kpi.students.total) }}
                            </p>
                            <div v-if="kpi.students.growth !== 0" class="flex items-center mt-2">
                                <i :class="[
                                    'pi text-sm mr-1',
                                    kpi.students.growth > 0 ? 'pi-arrow-up text-green-600' : 'pi-arrow-down text-red-600'
                                ]"></i>
                                <span :class="[
                                    'text-sm',
                                    kpi.students.growth > 0 ? 'text-green-600' : 'text-red-600'
                                ]">
                                    {{ Math.abs(kpi.students.growth) }}%
                                </span>
                                <span class="text-xs text-gray-500 ml-1">so với tháng trước</span>
                            </div>
                        </div>
                        <div class="bg-blue-100 dark:bg-blue-900/20 p-3 rounded-full">
                            <i class="pi pi-users text-blue-600 dark:text-blue-400 text-xl"></i>
                        </div>
                    </div>
                </template>
            </Card>

            <!-- Classes -->
            <Card>
                <template #content>
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-600 dark:text-gray-400">
                                Lớp học đang mở
                            </p>
                            <p class="text-3xl font-bold text-gray-900 dark:text-white">
                                {{ formatNumber(kpi.classes.total) }}
                            </p>
                            <div v-if="kpi.classes.growth !== 0" class="flex items-center mt-2">
                                <i :class="[
                                    'pi text-sm mr-1',
                                    kpi.classes.growth > 0 ? 'pi-arrow-up text-green-600' : 'pi-arrow-down text-red-600'
                                ]"></i>
                                <span :class="[
                                    'text-sm',
                                    kpi.classes.growth > 0 ? 'text-green-600' : 'text-red-600'
                                ]">
                                    {{ Math.abs(kpi.classes.growth) }}%
                                </span>
                                <span class="text-xs text-gray-500 ml-1">so với tháng trước</span>
                            </div>
                        </div>
                        <div class="bg-green-100 dark:bg-green-900/20 p-3 rounded-full">
                            <i class="pi pi-bookmark text-green-600 dark:text-green-400 text-xl"></i>
                        </div>
                    </div>
                </template>
            </Card>

            <!-- Teachers -->
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-600 dark:text-gray-400">
                            Giáo viên đang dạy
                        </p>
                        <p class="text-3xl font-bold text-gray-900 dark:text-white">
                            {{ formatNumber(kpi.teachers.total) }}
                        </p>
                    </div>
                    <div class="bg-purple-100 dark:bg-purple-900/20 p-3 rounded-full">
                        <i class="pi pi-user text-purple-600 dark:text-purple-400 text-xl"></i>
                    </div>
                </div>
            </div>

            <!-- Sessions Today -->
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-600 dark:text-gray-400">
                            Buổi học hôm nay
                        </p>
                        <p class="text-3xl font-bold text-gray-900 dark:text-white">
                            {{ formatNumber(kpi.sessions_today.total) }}
                        </p>
                    </div>
                    <div class="bg-orange-100 dark:bg-orange-900/20 p-3 rounded-full">
                        <i class="pi pi-calendar text-orange-600 dark:text-orange-400 text-xl"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Charts Row -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
            <!-- Enrollment Trend Chart -->
            <Card class="p-6">
                <template #content>
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">
                        Xu hướng đăng ký
                    </h3>
                    <div class="h-64">
                        <Chart
                            key="enrollment-chart"
                            type="line"
                            :data="enrollmentChartData"
                            :options="chartOptions"
                            class="w-full h-full"
                        />
                    </div>
                </template>
            </Card>

            <!-- Students by Course Chart -->
            <Card class="p-6">
                <template #content>
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">
                        Học viên theo khóa học
                    </h3>
                    <div class="h-64">
                        <Chart
                            key="course-chart"
                            type="doughnut"
                            :data="courseChartData"
                            :options="pieChartOptions"
                            class="w-full h-full"
                        />
                    </div>
                </template>
            </Card>
        </div>

        <!-- Attendance Chart -->
        <div class="mb-8">
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">
                    Tỷ lệ tham dự theo lớp (30 ngày qua)
                </h3>
                <div class="h-80">
                    <Chart
                        type="bar"
                        :data="attendanceChartData"
                        :options="attendanceChartOptions"
                        class="w-full h-full"
                    />
                </div>
            </div>
        </div>

        <!-- Recent Activities -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Recent Transfers -->
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">
                    Chuyển lớp gần đây
                </h3>
                <div class="space-y-3">
                    <div v-if="recent.transfers.length === 0" class="text-center py-8">
                        <i class="pi pi-inbox text-gray-400 text-3xl mb-2"></i>
                        <p class="text-gray-500 dark:text-gray-400">Chưa có chuyển lớp nào</p>
                    </div>
                    <div v-else v-for="transfer in recent.transfers" :key="transfer.id"
                         class="flex items-start space-x-3 p-3 bg-gray-50 dark:bg-gray-800 rounded-lg">
                        <div class="flex-shrink-0">
                            <i class="pi pi-arrow-right text-blue-600 dark:text-blue-400"></i>
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium text-gray-900 dark:text-white">
                                {{ transfer.student.name }}
                            </p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                {{ transfer.from_class.name }} → {{ transfer.to_class.name }}
                            </p>
                            <div class="flex items-center mt-1">
                                <Tag
                                    :value="getTransferStatusText(transfer.status)"
                                    :severity="getTransferStatusSeverity(transfer.status)"
                                    class="text-xs"
                                />
                                <span class="text-xs text-gray-400 ml-2">
                                    {{ formatDate(transfer.created_at) }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Today's Attendance -->
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">
                    Điểm danh hôm nay
                </h3>
                <div class="space-y-3">
                    <div v-if="recent.attendance_today.length === 0" class="text-center py-8">
                        <i class="pi pi-calendar text-gray-400 text-3xl mb-2"></i>
                        <p class="text-gray-500 dark:text-gray-400">Chưa có buổi học nào hôm nay</p>
                    </div>
                    <div v-else v-for="session in recent.attendance_today" :key="session.class_name + session.start_time"
                         class="p-3 bg-gray-50 dark:bg-gray-800 rounded-lg">
                        <div class="flex justify-between items-start">
                            <div class="flex-1">
                                <p class="text-sm font-medium text-gray-900 dark:text-white">
                                    {{ session.class_name }}
                                </p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">
                                    {{ session.start_time }} - {{ session.end_time }}
                                </p>
                            </div>
                            <div class="text-right">
                                <div class="text-sm font-medium" :class="getAttendanceColor(session.present_count, session.total_attendances)">
                                    {{ session.present_count }}/{{ session.total_attendances }}
                                </div>
                                <div class="text-xs text-gray-500">
                                    {{ getAttendancePercentage(session.present_count, session.total_attendances) }}%
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Pending Timesheets -->
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">
                    Bảng chấm công chờ duyệt
                </h3>
                <div class="space-y-3">
                    <div v-if="recent.pending_timesheets.length === 0" class="text-center py-8">
                        <i class="pi pi-check-circle text-gray-400 text-3xl mb-2"></i>
                        <p class="text-gray-500 dark:text-gray-400">Không có bảng chấm công nào chờ duyệt</p>
                    </div>
                    <div v-else v-for="timesheet in recent.pending_timesheets" :key="timesheet.id"
                         class="p-3 bg-gray-50 dark:bg-gray-800 rounded-lg">
                        <div class="flex justify-between items-start">
                            <div class="flex-1">
                                <p class="text-sm font-medium text-gray-900 dark:text-white">
                                    {{ timesheet.teacher.name }}
                                </p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">
                                    {{ timesheet.session.classroom.name }}
                                </p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">
                                    {{ formatDate(timesheet.created_at) }}
                                </p>
                            </div>
                            <div class="text-right">
                                <div class="text-sm font-medium text-gray-900 dark:text-white">
                                    {{ formatCurrency(timesheet.amount) }}
                                </div>
                                <div class="text-xs text-gray-500 dark:text-gray-400">
                                    Phí giảng dạy
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
