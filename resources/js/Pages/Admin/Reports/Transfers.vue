<template>
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
                    title="Đã hoàn thành"
                    :value="kpi.completed_transfers?.total || 0"
                    icon="pi pi-check-circle"
                    color="green"
                />
                <KPICard
                    title="Đang chờ"
                    :value="kpi.pending_transfers?.total || 0"
                    icon="pi pi-clock"
                    color="orange"
                />
                <KPICard
                    title="Bị từ chối"
                    :value="kpi.rejected_transfers?.total || 0"
                    icon="pi pi-times-circle"
                    color="red"
                />
            </div>

            <!-- Charts Grid -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- Monthly Transfer Trend -->
                <Card class="h-96">
                    <template #header>
                        <div class="p-4 border-b dark:border-gray-700">
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Monthly Transfer Trend</h3>
                        </div>
                    </template>
                    <template #content>
                        <div class="h-80">
                            <Chart
                                v-if="chartData.monthlyTransferTrend"
                                type="line"
                                :data="chartData.monthlyTransferTrend"
                                :options="chartOptions.line"
                            />
                        </div>
                    </template>
                </Card>

                <!-- Transfer Status Distribution -->
                <Card class="h-96">
                    <template #header>
                        <div class="p-4 border-b dark:border-gray-700">
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Status Distribution</h3>
                        </div>
                    </template>
                    <template #content>
                        <div class="h-80">
                            <Chart
                                v-if="chartData.transferStatusDistribution"
                                type="doughnut"
                                :data="chartData.transferStatusDistribution"
                                :options="chartOptions.doughnut"
                            />
                        </div>
                    </template>
                </Card>

                <!-- Chuyển lớp theo Chi nhánh -->
                <Card class="h-96">
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
                            />
                        </div>
                    </template>
                </Card>

                <!-- Chuyển lớp gần đây -->
                <Card class="h-96">
                    <template #header>
                        <div class="p-4 border-b dark:border-gray-700">
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Chuyển lớp gần đây</h3>
                        </div>
                    </template>
                    <template #content>
                        <div class="h-80 overflow-auto">
                            <div v-if="recent.transfers?.length" class="space-y-3">
                                <div
                                    v-for="transfer in recent.transfers"
                                    :key="transfer.id"
                                    class="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-800 rounded-lg"
                                >
                                    <div>
                                        <div class="font-medium text-gray-900 dark:text-white">
                                            {{ transfer.student_name }}
                                        </div>
                                        <div class="text-sm text-gray-600 dark:text-gray-400">
                                            {{ transfer.from_class }} → {{ transfer.to_class }}
                                        </div>
                                        <div class="text-xs text-gray-500">
                                            {{ transfer.reason }}
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        <div class="inline-flex items-center px-2 py-1 text-xs font-medium rounded-full"
                                             :class="getStatusClass(transfer.status)">
                                            {{ transfer.status }}
                                        </div>
                                        <div class="text-xs text-gray-500 mt-1">
                                            {{ formatDate(transfer.created_at) }}
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div v-else class="flex items-center justify-center h-full text-gray-500">
                                Không có chuyển lớp gần đây
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

    // Transfer Status Distribution
    if (props.charts?.transfer_status_distribution?.length) {
        data.transferStatusDistribution = {
            labels: props.charts.transfer_status_distribution.map(item => item.name),
            datasets: [{
                data: props.charts.transfer_status_distribution.map(item => item.value),
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
    if (props.charts?.transfers_by_branch?.length) {
        data.transfersByBranch = {
            labels: props.charts.transfers_by_branch.map(item => item.name),
            datasets: [{
                label: 'Chuyển lớp',
                data: props.charts.transfers_by_branch.map(item => item.value),
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
