<template>
    <Head title="Báo cáo lớp học" />
    <AppLayout title="Báo cáo Lớp học">
        <div class="p-3 md:p-5 space-y-6">
            <!-- Header -->
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Báo cáo Lớp học</h1>
                    <p class="text-gray-600 dark:text-gray-400">Phân tích quản lý lớp học cho các chi nhánh của bạn</p>
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
                    title="Tổng lớp học"
                    :value="kpi.total_classes?.total || 0"
                    :growth="kpi.total_classes?.growth"
                    icon="pi pi-book"
                    color="blue"
                />
                <KPICard
                    title="Lớp đang hoạt động"
                    :value="kpi.active_classes?.total || 0"
                    :growth="kpi.active_classes?.growth"
                    icon="pi pi-play"
                    color="green"
                />
                <KPICard
                    title="Tổng học viên"
                    :value="kpi.total_students?.total || 0"
                    :growth="kpi.total_students?.growth"
                    icon="pi pi-users"
                    color="purple"
                />
                <KPICard
                    title="Sĩ số trung bình"
                    :value="kpi.avg_class_size?.total || 0"
                    :growth="kpi.avg_class_size?.growth"
                    icon="pi pi-chart-bar"
                    color="orange"
                />
            </div>

            <!-- Charts Grid -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- Lớp theo Trạng thái -->
                <Card class="h-96">
                    <template #header>
                        <div class="p-4 border-b dark:border-gray-700">
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Lớp theo Trạng thái</h3>
                        </div>
                    </template>
                    <template #content>
                        <div class="h-80">
                            <Chart
                                v-if="chartData.classesByStatus"
                                type="doughnut"
                                :data="chartData.classesByStatus"
                                :options="chartOptions.doughnut"
                            />
                        </div>
                    </template>
                </Card>

                <!-- Lớp mở mới theo tháng -->
                <Card class="h-96">
                    <template #header>
                        <div class="p-4 border-b dark:border-gray-700">
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Lớp mở mới theo tháng</h3>
                        </div>
                    </template>
                    <template #content>
                        <div class="h-80">
                            <Chart
                                v-if="chartData.monthlyClassCreation"
                                type="bar"
                                :data="chartData.monthlyClassCreation"
                                :options="chartOptions.bar"
                            />
                        </div>
                    </template>
                </Card>

                <!-- Lớp theo Khóa học -->
                <Card class="h-96">
                    <template #header>
                        <div class="p-4 border-b dark:border-gray-700">
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Lớp theo Khóa học</h3>
                        </div>
                    </template>
                    <template #content>
                        <div class="h-80">
                            <Chart
                                v-if="chartData.classesByCourse"
                                type="bar"
                                :data="chartData.classesByCourse"
                                :options="chartOptions.bar"
                            />
                        </div>
                    </template>
                </Card>

                <!-- Lớp học Gần đây -->
                <Card class="h-96">
                    <template #header>
                        <div class="p-4 border-b dark:border-gray-700">
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Lớp học Gần đây</h3>
                        </div>
                    </template>
                    <template #content>
                        <div class="h-80 overflow-auto">
                            <div v-if="recent.classes?.length" class="space-y-3">
                                <div
                                    v-for="classItem in recent.classes"
                                    :key="classItem.id"
                                    class="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-800 rounded-lg"
                                >
                                    <div>
                                        <div class="font-medium text-gray-900 dark:text-white">
                                            {{ classItem.name }}
                                        </div>
                                        <div class="text-sm text-gray-600 dark:text-gray-400">
                                            {{ classItem.course_name }} • {{ classItem.branch_name }}
                                        </div>
                                        <div class="text-xs text-gray-500">
                                            {{ classItem.students_count }} students • Teacher: {{ classItem.teacher_name || 'Not assigned' }}
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        <div class="inline-flex items-center px-2 py-1 text-xs font-medium rounded-full"
                                             :class="getStatusClass(classItem.status)">
                                            {{ classItem.status }}
                                        </div>
                                        <div class="text-xs text-gray-500 mt-1">
                                            {{ formatDate(classItem.start_date) }}
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div v-else class="flex items-center justify-center h-full text-gray-500">
                                Không có lớp học gần đây
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

// Reactive data
const filters = ref({
    startDate: props.appliedFilters?.start_date || null,
    endDate: props.appliedFilters?.end_date || null,
    branchIds: props.appliedFilters?.branch_ids || [],
    courseIds: props.appliedFilters?.course_ids || []
})

// Chart data
const chartData = computed(() => {
    const data = {}

    // Classes by Status
    if (props.charts?.classes_by_status?.length) {
        data.classesByStatus = {
            labels: props.charts.classes_by_status.map(item => item.name),
            datasets: [{
                data: props.charts.classes_by_status.map(item => item.value),
                backgroundColor: [
                    'rgba(16, 185, 129, 0.8)',   // Active - green
                    'rgba(251, 191, 36, 0.8)',   // Planned - yellow
                    'rgba(239, 68, 68, 0.8)',    // Closed - red
                    'rgba(156, 163, 175, 0.8)'   // Other - gray
                ],
                borderWidth: 2
            }]
        }
    }

    // Monthly Class Creation
    if (props.charts?.monthly_class_creation?.length) {
        data.monthlyClassCreation = {
            labels: props.charts.monthly_class_creation.map(item => item.month),
            datasets: [{
                label: 'Lớp mới',
                data: props.charts.monthly_class_creation.map(item => item.value),
                backgroundColor: 'rgba(59, 130, 246, 0.8)',
                borderColor: 'rgba(59, 130, 246, 1)',
                borderWidth: 1
            }]
        }
    }

    // Classes by Course
    if (props.charts?.classes_by_course?.length) {
        data.classesByCourse = {
            labels: props.charts.classes_by_course.map(item => item.name),
            datasets: [{
                label: 'Lớp học',
                data: props.charts.classes_by_course.map(item => item.value),
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

    router.get(route('manager.reports.classes'), params, {
        preserveState: true,
        preserveScroll: true
    })
}

const resetFilters = () => {
    router.get(route('manager.reports.classes'))
}

const getStatusClass = (status) => {
    const classes = {
        'active': 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300',
        'planned': 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-300',
        'closed': 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300',
        'suspended': 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-300'
    }
    return classes[status] || classes['planned']
}

const formatDate = (dateString) => {
    if (!dateString) return ''
    return new Date(dateString).toLocaleDateString('vi-VN')
}
</script>
