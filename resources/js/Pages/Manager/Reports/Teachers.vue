<template>
    <Head title="Báo cáo Giáo viên" />
    <AppLayout title="Báo cáo Giáo viên">
        <div class="p-3 md:p-5 space-y-6">
            <!-- Header -->
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Báo cáo Giáo viên</h1>
                    <p class="text-gray-600 dark:text-gray-400">Phân tích hiệu suất giáo viên cho các chi nhánh của bạn</p>
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
                    title="Tổng giáo viên"
                    :value="kpi.total_teachers?.total || 0"
                    :growth="kpi.total_teachers?.growth"
                    icon="pi pi-users"
                    color="blue"
                />
                <KPICard
                    title="GV đang dạy"
                    :value="kpi.active_teachers?.total || 0"
                    :growth="kpi.active_teachers?.growth"
                    icon="pi pi-user-plus"
                    color="green"
                />
                <KPICard
                    title="Tổng lớp"
                    :value="kpi.total_classes?.total || 0"
                    :growth="kpi.total_classes?.growth"
                    icon="pi pi-book"
                    color="purple"
                />
                <KPICard
                    title="TB HV/GV"
                    :value="kpi.avg_students_per_teacher?.total || 0"
                    :growth="kpi.avg_students_per_teacher?.growth"
                    icon="pi pi-chart-bar"
                    color="orange"
                />
            </div>

            <!-- Charts Grid -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- Giáo viên theo Chi nhánh -->
                <Card class="h-96">
                    <template #header>
                        <div class="p-4 border-b dark:border-gray-700">
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Giáo viên theo Chi nhánh</h3>
                        </div>
                    </template>
                    <template #content>
                        <div class="h-80">
                            <Chart
                                v-if="chartData.teachersByBranch"
                                type="bar"
                                :data="chartData.teachersByBranch"
                                :options="chartOptions.bar"
                            />
                        </div>
                    </template>
                </Card>

                <!-- Khối lượng công việc -->
                <Card class="h-96">
                    <template #header>
                        <div class="p-4 border-b dark:border-gray-700">
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Khối lượng công việc</h3>
                        </div>
                    </template>
                    <template #content>
                        <div class="h-80">
                            <Chart
                                v-if="chartData.teacherWorkload"
                                type="bar"
                                :data="chartData.teacherWorkload"
                                :options="chartOptions.bar"
                            />
                        </div>
                    </template>
                </Card>

                <!-- Phân công theo tháng -->
                <Card class="h-96">
                    <template #header>
                        <div class="p-4 border-b dark:border-gray-700">
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Phân công theo tháng</h3>
                        </div>
                    </template>
                    <template #content>
                        <div class="h-80">
                            <Chart
                                v-if="chartData.assignmentTimeline"
                                type="line"
                                :data="chartData.assignmentTimeline"
                                :options="chartOptions.line"
                            />
                        </div>
                    </template>
                </Card>

                <!-- Hiệu suất Giáo viên -->
                <Card class="h-96">
                    <template #header>
                        <div class="p-4 border-b dark:border-gray-700">
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Hiệu suất Giáo viên</h3>
                        </div>
                    </template>
                    <template #content>
                        <div class="h-80 overflow-auto">
                            <div v-if="recent.teacher_performance?.length" class="space-y-3">
                                <div
                                    v-for="teacher in recent.teacher_performance"
                                    :key="teacher.teacher_id"
                                    class="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-800 rounded-lg"
                                >
                                    <div>
                                        <div class="font-medium text-gray-900 dark:text-white">
                                            {{ teacher.teacher_name }}
                                        </div>
                                        <div class="text-sm text-gray-600 dark:text-gray-400">
                                            {{ teacher.classes_count }} lớp • {{ teacher.students_count }} học viên
                                        </div>
                                        <div class="text-xs text-gray-500">
                                            {{ teacher.branch_name }}
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        <div class="font-medium text-blue-600 dark:text-blue-400">
                                            {{ teacher.avg_attendance }}% điểm danh
                                        </div>
                                        <div class="text-xs text-gray-500">
                                            Hoạt động từ {{ formatDate(teacher.effective_from) }}
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div v-else class="flex items-center justify-center h-full text-gray-500">
                                Không có dữ liệu hiệu suất giáo viên
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
    courseIds: props.appliedFilters?.course_ids || [],
    teacherIds: props.appliedFilters?.teacher_ids || []
})

// Chart data
const chartData = computed(() => {
    const data = {}

    // Teachers by Branch
    if (props.charts?.teachers_by_branch?.length) {
        data.teachersByBranch = {
            labels: props.charts.teachers_by_branch.map(item => item.name),
            datasets: [{
                label: 'Giáo viên',
                data: props.charts.teachers_by_branch.map(item => item.value),
                backgroundColor: 'rgba(16, 185, 129, 0.8)',
                borderColor: 'rgba(16, 185, 129, 1)',
                borderWidth: 1
            }]
        }
    }

    // Teacher Workload
    if (props.charts?.teacher_workload?.length) {
        data.teacherWorkload = {
            labels: props.charts.teacher_workload.map(item => item.name),
            datasets: [{
                label: 'Lớp học',
                data: props.charts.teacher_workload.map(item => item.value),
                backgroundColor: 'rgba(59, 130, 246, 0.8)',
                borderColor: 'rgba(59, 130, 246, 1)',
                borderWidth: 1
            }]
        }
    }

    // Assignment Timeline
    if (props.charts?.assignment_timeline?.length) {
        data.assignmentTimeline = {
            labels: props.charts.assignment_timeline.map(item => item.month),
            datasets: [{
                label: 'Phân công mới',
                data: props.charts.assignment_timeline.map(item => item.value),
                borderColor: 'rgba(139, 92, 246, 1)',
                backgroundColor: 'rgba(139, 92, 246, 0.1)',
                tension: 0.4,
                fill: true
            }]
        }
    }

    return data
})

// Chart options
const chartOptions = {
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
    },
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
    }
}

// Methods
const applyFilters = (filterData) => {
    const params = {}

    if (filterData.start_date) params.start_date = filterData.start_date
    if (filterData.end_date) params.end_date = filterData.end_date
    if (filterData.branches?.length) params.branches = filterData.branches
    if (filterData.courses?.length) params.courses = filterData.courses
    if (filterData.teachers?.length) params.teachers = filterData.teachers

    router.get(route('manager.reports.teachers'), params, {
        preserveState: true,
        preserveScroll: true
    })
}

const resetFilters = () => {
    router.get(route('manager.reports.teachers'))
}

const formatDate = (dateString) => {
    if (!dateString) return ''
    return new Date(dateString).toLocaleDateString('vi-VN')
}
</script>
