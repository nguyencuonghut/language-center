<template>
    <Head title="Báo cáo Chuyển lớp" />
    <AppLayout title="Báo cáo Chuyển lớp">
        <div class="p-3 md:p-5 space-y-6">
            <!-- Header -->
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Báo cáo Chuyển lớp</h1>
                    <p class="text-gray-600 dark:text-gray-400">Phân tích và xu hướng chuyển lớp học sinh</p>
                </div>
            </div>

            <!-- Filters -->
            <FilterBar
                :filters="appliedFilters"
                :filter-options="availableFilters"
                show-branch-filter
                @apply="applyFilters"
                @reset="resetFilters"
            />

            <!-- KPI Cards -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                <KPICard
                    title="Tổng chuyển lớp"
                    :value="kpi.total_transfers?.total || 0"
                    :growth="kpi.total_transfers?.growth"
                    icon="pi pi-arrow-right-arrow-left"
                    color="blue"
                />
                <KPICard
                    title="Chuyển lớp đang hoạt động"
                    :value="kpi.active_transfers?.total || 0"
                    icon="pi pi-check"
                    color="green"
                />
                <KPICard
                    title="Chuyển lớp đã hoàn tác"
                    :value="kpi.reverted_transfers?.total || 0"
                    icon="pi pi-times-circle"
                    color="red"
                />
                <KPICard
                    title="Tỷ lệ chuyển lớp"
                    :value="kpi.transfer_rate?.rate || 0"
                    icon="pi pi-check-circle"
                    color="green"
                />
            </div>

            <!-- Charts Grid -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- Monthly Transfer Trend -->
                <Card>
                    <template #header>
                        <div class="p-4 border-b dark:border-gray-700">
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Xu hướng chuyển lớp theo tháng</h3>
                        </div>
                    </template>
                    <template #content>
                        <div class="h-80">
                            <Chart
                                v-if="chartData.monthlyTransferTrend"
                                type="line"
                                :data="chartData.monthlyTransferTrend"
                                :options="chartOptions.line"
                                class="h-full w-full"
                            />
                        </div>
                    </template>
                </Card>

                <!-- Transfer Flow by Course -->
                <Card>
                    <template #header>
                        <div class="p-4 border-b dark:border-gray-700">
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Phân bố luồng chuyển lớp theo khóa học</h3>
                        </div>
                    </template>
                    <template #content>
                        <div class="h-80">
                            <Chart
                                v-if="chartData.transferFlowByCourse"
                                type="doughnut"
                                :data="chartData.transferFlowByCourse"
                                :options="chartOptions.doughnut"
                                class="h-full w-full"
                            />
                        </div>
                    </template>
                </Card>

                <!-- Chuyển lớp theo Chi nhánh -->
                <Card>
                    <template #header>
                        <div class="p-4 border-b dark:border-gray-700">
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Chuyển lớp theo Chi nhánh</h3>
                        </div>
                    </template>
                    <template #content>
                        <div class="h-80">
                            <Chart
                                v-if="chartData.transfersByBranch"
                                type="bar"
                                :data="chartData.transfersByBranch"
                                :options="chartOptions.bar"
                                class="h-full w-full"
                            />
                        </div>
                    </template>
                </Card>

                <!-- Chuyển lớp theo lý do -->
                <Card>
                    <template #header>
                        <div class="p-4 border-b dark:border-gray-700">
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Chuyển lớp theo lý do</h3>
                        </div>
                    </template>
                    <template #content>
                        <div class="h-80">
                            <Chart
                                v-if="chartData.transferReasons"
                                type="bar"
                                :data="chartData.transferReasons"
                                :options="chartOptions.bar"
                                class="h-full w-full"
                            />
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
import Chart from 'primevue/chart'
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

    // Monthly Transfer Trend
    if (props.charts?.monthly_transfer_trend?.length) {
        data.monthlyTransferTrend = {
            labels: props.charts.monthly_transfer_trend.map(item => item.month),
            datasets: [{
                label: 'Chuyển lớp',
                data: props.charts.monthly_transfer_trend.map(item => item.value),
                borderColor: 'rgba(59, 130, 246, 1)',
                backgroundColor: 'rgba(59, 130, 246, 0.1)',
                tension: 0.4,
                fill: true
            }]
        }
    }

    // Transfer Flow by Course
    if (props.charts?.transfer_flow_by_course?.length) {
        data.transferFlowByCourse = {
            labels: props.charts.transfer_flow_by_course.map(item => `${item.from} → ${item.to}`),
            datasets: [{
                data: props.charts.transfer_flow_by_course.map(item => item.value),
                backgroundColor: [
                    'rgba(16, 185, 129, 0.8)',   // Completed - green
                    'rgba(251, 191, 36, 0.8)',   // Pending - yellow
                    'rgba(239, 68, 68, 0.8)',    // Rejected - red
                    'rgba(156, 163, 175, 0.8)'   // Other - gray
                ],
                borderWidth: 2
            }]
        }
    }

    // Transfers by Branch
    if (props.charts?.transfer_flow_by_branch?.length) {
        data.transfersByBranch = {
            labels: props.charts.transfer_flow_by_branch.map(item => `${item.from} → ${item.to}`),
            datasets: [{
                label: 'Chuyển lớp',
                data: props.charts.transfer_flow_by_branch.map(item => item.value),
                backgroundColor: 'rgba(16, 185, 129, 0.8)',
                borderColor: 'rgba(16, 185, 129, 1)',
                borderWidth: 1
            }]
        }
    }

    // Transfer Reasons
    if (props.charts?.transfer_reasons?.length) {
    data.transferReasons = {
        labels: props.charts.transfer_reasons.map(item => item.name),
        datasets: [{
            label: 'Số lượng',
            data: props.charts.transfer_reasons.map(item => item.value),
            backgroundColor: 'rgba(59, 130, 246, 0.7)'
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

    router.get(route('admin.reports.transfers'), params, {
        preserveState: true,
        preserveScroll: true
    })
}

const resetFilters = () => {
    router.get(route('admin.reports.transfers'))
}

const getStatusClass = (status) => {
    const classes = {
        'completed': 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300',
        'pending': 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-300',
        'rejected': 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300',
        'cancelled': 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-300'
    }
    return classes[status] || classes['pending']
}

const formatDate = (dateString) => {
    if (!dateString) return ''
    return new Date(dateString).toLocaleDateString('vi-VN')
}
</script>
