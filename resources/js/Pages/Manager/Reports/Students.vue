<template>
    <Head title="Báo cáo Học viên" />
    <AppLayout title="Báo cáo Học viên">
        <div class="p-3 md:p-5 space-y-6">
            <!-- Header -->
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Báo cáo Học viên</h1>
                    <p class="text-gray-600 dark:text-gray-400">Phân tích học viên cho các chi nhánh của bạn</p>
                </div>
            </div>

            <!-- Filters -->
            <FilterBar
                :filters="appliedFilters"
                :filter-options="availableFilters"
                @apply="applyFilters"
                @reset="resetFilters"
            />

            <!-- KPI Cards -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                <KPICard
                    title="Tổng học viên"
                    :value="kpi.total_students?.total || 0"
                    :growth="kpi.total_students?.growth"
                    icon="pi pi-users"
                    color="blue"
                />
                <KPICard
                    title="Học viên đang học"
                    :value="kpi.active_students?.total || 0"
                    :growth="kpi.active_students?.growth"
                    icon="pi pi-user-plus"
                    color="green"
                />
                <KPICard
                    title="Đăng ký mới"
                    :value="kpi.new_enrollments?.total || 0"
                    :growth="kpi.new_enrollments?.growth"
                    icon="pi pi-user-edit"
                    color="purple"
                />
                <KPICard
                    title="Điểm danh TB"
                    :value="(kpi.avg_attendance?.total || 0) + '%'"
                    :growth="kpi.avg_attendance?.growth"
                    icon="pi pi-calendar-plus"
                    color="orange"
                />
            </div>

            <!-- Charts Grid -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- Xu hướng Đăng ký -->
                <Card class="h-96">
                    <template #header>
                        <div class="p-4 border-b dark:border-gray-700">
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Xu hướng Đăng ký</h3>
                        </div>
                    </template>
                    <template #content>
                        <div class="h-80">
                            <Chart
                                v-if="chartData.enrollmentTrend"
                                type="line"
                                :data="chartData.enrollmentTrend"
                                :options="chartOptions.line"
                            />
                        </div>
                    </template>
                </Card>

                <!-- Học viên theo Khóa học -->
                <Card class="h-96">
                    <template #header>
                        <div class="p-4 border-b dark:border-gray-700">
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Học viên theo Khóa học</h3>
                        </div>
                    </template>
                    <template #content>
                        <div class="h-80">
                            <Chart
                                v-if="chartData.studentsByCourse"
                                type="doughnut"
                                :data="chartData.studentsByCourse"
                                :options="chartOptions.doughnut"
                            />
                        </div>
                    </template>
                </Card>

                <!-- Học viên theo Chi nhánh -->
                <Card class="h-96">
                    <template #header>
                        <div class="p-4 border-b dark:border-gray-700">
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Học viên theo Chi nhánh</h3>
                        </div>
                    </template>
                    <template #content>
                        <div class="h-80">
                            <Chart
                                v-if="chartData.studentsByBranch"
                                type="bar"
                                :data="chartData.studentsByBranch"
                                :options="chartOptions.bar"
                            />
                        </div>
                    </template>
                </Card>

                <!-- Đăng ký Gần đây -->
                <Card class="h-96">
                    <template #header>
                        <div class="p-4 border-b dark:border-gray-700">
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Đăng ký Gần đây</h3>
                        </div>
                    </template>
                    <template #content>
                        <div class="h-80 overflow-auto">
                            <div v-if="recent.enrollments?.length" class="space-y-3">
                                <div
                                    v-for="enrollment in recent.enrollments"
                                    :key="enrollment.id"
                                    class="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-800 rounded-lg"
                                >
                                    <div>
                                        <div class="font-medium text-gray-900 dark:text-white">
                                            {{ enrollment.student_name }}
                                        </div>
                                        <div class="text-sm text-gray-600 dark:text-gray-400">
                                            {{ enrollment.class_name }} • {{ enrollment.course_name }}
                                        </div>
                                        <div class="text-xs text-gray-500">
                                            {{ enrollment.branch_name }}
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        <div class="inline-flex items-center px-2 py-1 text-xs font-medium rounded-full"
                                             :class="getStatusClass(enrollment.status)">
                                            {{ enrollment.status }}
                                        </div>
                                        <div class="text-xs text-gray-500 mt-1">
                                            {{ formatDate(enrollment.enrolled_at) }}
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div v-else class="flex items-center justify-center h-full text-gray-500">
                                Không có đăng ký gần đây
                            </div>
                        </div>
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
import Chart from '@/Components/Reports/Chart.vue'
import Card from 'primevue/card'

// Props
const props = defineProps({
    kpi: Object,
    charts: Object,
    recent: Object,
    availableFilters: Object,
    appliedFilters: Object
})

// Chart data
const chartData = computed(() => {
    const data = {}

    // Enrollment Trend
    if (props.charts?.enrollment_trend?.length) {
        data.enrollmentTrend = {
            labels: props.charts.enrollment_trend.map(item => item.month),
            datasets: [{
                label: 'Đăng ký',
                data: props.charts.enrollment_trend.map(item => item.value),
                borderColor: 'rgba(59, 130, 246, 1)',
                backgroundColor: 'rgba(59, 130, 246, 0.1)',
                tension: 0.4,
                fill: true
            }]
        }
    }

    // Students by Course
    if (props.charts?.students_by_course?.length) {
        data.studentsByCourse = {
            labels: props.charts.students_by_course.map(item => item.name),
            datasets: [{
                data: props.charts.students_by_course.map(item => item.value),
                backgroundColor: [
                    'rgba(59, 130, 246, 0.8)',
                    'rgba(16, 185, 129, 0.8)',
                    'rgba(251, 191, 36, 0.8)',
                    'rgba(239, 68, 68, 0.8)',
                    'rgba(139, 92, 246, 0.8)'
                ],
                borderWidth: 2
            }]
        }
    }

    // Students by Branch
    if (props.charts?.students_by_branch?.length) {
        data.studentsByBranch = {
            labels: props.charts.students_by_branch.map(item => item.name),
            datasets: [{
                label: 'Học viên',
                data: props.charts.students_by_branch.map(item => item.value),
                backgroundColor: 'rgba(16, 185, 129, 0.8)',
                borderColor: 'rgba(16, 185, 129, 1)',
                borderWidth: 1
            }]
        }
    }

    return data
})

// Chart options
const chartOptions = {
    line: {
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
                    precision: 0
                }
            }
        }
    },
    doughnut: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'bottom'
            }
        }
    },
    bar: {
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
                    precision: 0
                }
            }
        }
    }
}

// Methods
const applyFilters = (filterData) => {
    const params = {}

    if (filterData.start_date) params.start_date = filterData.start_date
    if (filterData.end_date) params.end_date = filterData.end_date
    if (filterData.branches?.length) params.branches = filterData.branches
    if (filterData.courses?.length) params.courses = filterData.courses

    router.get(route('manager.reports.students'), params, {
        preserveState: true,
        preserveScroll: true
    })
}

const resetFilters = () => {
    router.get(route('manager.reports.students'))
}

const getStatusClass = (status) => {
    const classes = {
        'active': 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300',
        'pending': 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-300',
        'suspended': 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300',
        'graduated': 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-300'
    }
    return classes[status] || classes['active']
}

const formatDate = (dateString) => {
    if (!dateString) return ''
    return new Date(dateString).toLocaleDateString('vi-VN')
}
</script>
