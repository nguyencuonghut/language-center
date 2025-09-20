<template>
    <Head title="Báo cáo Giáo viên & Bảng chấm công" />
    <AppLayout title="Báo cáo Giáo viên & Bảng chấm công">
        <div class="p-3 md:p-5 space-y-6">
            <!-- Header -->
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Báo cáo Giáo viên & Bảng chấm công</h1>
                    <p class="text-gray-600 dark:text-gray-400">Phân tích hiệu suất giáo viên và bảng lương</p>
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
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4">
                <KPICard
                    title="Tổng buổi học"
                    :value="kpi.total_sessions?.total || 0"
                    icon="pi pi-calendar"
                    color="blue"
                />
                <KPICard
                    title="Chi phí lương"
                    :value="kpi.total_payroll_cost?.total || 0"
                    icon="pi pi-money-bill"
                    color="green"
                    format="currency"
                />
                <KPICard
                    title="Chấm công nháp"
                    :value="kpi.timesheet_draft?.total || 0"
                    icon="pi pi-file-edit"
                    color="orange"
                />
                <KPICard
                    title="Đã duyệt"
                    :value="kpi.timesheet_approved?.total || 0"
                    icon="pi pi-check"
                    color="green"
                />
                <KPICard
                    title="Đã khóa"
                    :value="kpi.timesheet_locked?.total || 0"
                    icon="pi pi-lock"
                    color="red"
                />
            </div>

            <!-- Charts Grid -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- Buổi học theo Giáo viên -->
                <Card>
                    <template #header>
                        <div class="p-4 border-b dark:border-gray-700">
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Buổi học theo Giáo viên</h3>
                        </div>
                    </template>
                    <template #content>
                        <div class="relative" style="height:340px;">
                            <PrimeChart
                                v-if="barChartData"
                                type="bar"
                                :data="barChartData"
                                :options="barChartOptions"
                                class="!w-full !h-full"
                            />
                        </div>
                    </template>
                </Card>

                <!-- Chi phí Lương theo tháng -->
                <Card>
                    <template #header>
                        <div class="p-4 border-b dark:border-gray-700">
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Chi phí Lương theo tháng</h3>
                        </div>
                    </template>
                    <template #content>
                        <div class="relative" style="height:340px;">
                            <PrimeChart
                                v-if="lineChartData"
                                type="line"
                                :data="lineChartData"
                                :options="lineChartOptions"
                                class="!w-full !h-full"
                            />
                        </div>
                    </template>
                </Card>

                <!-- Timesheet Status Funnel -->
                <Card>
                    <template #header>
                        <div class="p-4 border-b dark:border-gray-700">
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Trạng thái chấm công</h3>
                        </div>
                    </template>
                    <template #content>
                        <div class="relative" style="height:340px;">
                            <PrimeChart
                                v-if="doughnutChartData"
                                type="doughnut"
                                :data="doughnutChartData"
                                :options="doughnutChartOptions"
                                class="!w-full !h-full"
                            />
                        </div>
                    </template>
                </Card>

                <!-- Teacher Summary -->
                <Card>
                    <template #header>
                        <div class="p-4 border-b dark:border-gray-700">
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Tổng kết giáo viên</h3>
                        </div>
                    </template>
                    <template #content>
                        <div class="h-80 overflow-auto">
                            <div v-if="recent.teacher_summary?.length" class="space-y-3">
                                <div
                                    v-for="teacher in recent.teacher_summary"
                                    :key="teacher.teacher_id"
                                    class="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-800 rounded-lg"
                                >
                                    <div>
                                        <div class="font-medium text-gray-900 dark:text-white">
                                            {{ teacher.teacher }}
                                        </div>
                                        <div class="text-sm text-gray-600 dark:text-gray-400">
                                            {{ teacher.sessions_count }} buổi • Trạng thái: {{ statusVi(teacher.status)  }}
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        <div class="font-medium text-green-600 dark:text-green-400">
                                            {{ formatCurrency(teacher.total_amount) }}
                                        </div>
                                        <div class="text-xs text-gray-500">
                                            {{ formatDate(teacher.last_updated) }}
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div v-else class="flex items-center justify-center h-full text-gray-500">
                                No teacher data available
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
import Card from 'primevue/card'
import PrimeChart from 'primevue/chart'

// Props
const props = defineProps({
    kpi: Object,
    charts: Object,
    recent: Object,
    availableFilters: Object,
    appliedFilters: Object
})

// Filters
const filters = ref({
    startDate: props.appliedFilters?.start_date || null,
    endDate: props.appliedFilters?.end_date || null,
    branchIds: props.appliedFilters?.branch_ids || [],
    courseIds: props.appliedFilters?.course_ids || [],
    teacherIds: props.appliedFilters?.teacher_ids || []
})

// Chart data & options
const STATUS_META = {
    draft:    { label: 'Nháp',      color: 'rgba(251, 191, 36, 0.8)' },
    approved: { label: 'Đã duyệt',  color: 'rgba(16, 185, 129, 0.8)' },
    locked:   { label: 'Đã khóa',   color: 'rgba(239, 68, 68, 0.8)' },
}

const barChartData = computed(() => {
    if (!props.charts?.sessions_by_teacher?.length) return null
    return {
        labels: props.charts.sessions_by_teacher.map(item => item.name),
        datasets: [{
            label: 'Buổi học',
            data: props.charts.sessions_by_teacher.map(item => item.value),
            backgroundColor: 'rgba(16, 185, 129, 0.8)',
            borderColor: 'rgba(16, 185, 129, 1)',
            borderWidth: 1
        }]
    }
})

const barChartOptions = {
    plugins: {
        legend: { display: false }
    },
    responsive: true,
    maintainAspectRatio: false,
    scales: {
        y: {
            beginAtZero: true,
            ticks: { precision: 0 }
        }
    }
}

const lineChartData = computed(() => {
    if (!props.charts?.monthly_payroll_cost?.length) return null
    return {
        labels: props.charts.monthly_payroll_cost.map(item => item.month),
        datasets: [{
            label: 'Chi phí lương',
            data: props.charts.monthly_payroll_cost.map(item => item.value),
            borderColor: 'rgba(59, 130, 246, 1)',
            backgroundColor: 'rgba(59, 130, 246, 0.1)',
            tension: 0.4,
            fill: true
        }]
    }
})

const lineChartOptions = {
    plugins: {
        legend: { display: false }
    },
    responsive: true,
    maintainAspectRatio: false,
    scales: {
        y: {
            beginAtZero: true,
            ticks: {
                callback: value => formatCurrency(value)
            }
        }
    }
}

const doughnutChartData = computed(() => {
    if (!props.charts?.timesheet_status_funnel?.length) return null
    const items = props.charts.timesheet_status_funnel.map(i => {
        const key = String(i.name).toLowerCase()
        return { ...i, key, ...(STATUS_META[key] || {label: i.name, color: '#999'}) }
    })
    return {
        labels: items.map(i => i.label),
        datasets: [{
            data: items.map(i => i.value),
            backgroundColor: items.map(i => i.color),
            borderWidth: 2
        }]
    }
})

const doughnutChartOptions = {
  responsive: true,
  maintainAspectRatio: false,
  plugins: {
    legend: {
      position: 'bottom'
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

    router.get(route('admin.reports.teachers-timesheet'), params, {
        preserveState: true,
        preserveScroll: true
    })
}

const resetFilters = () => {
    router.get(route('admin.reports.teachers-timesheet'))
}
const formatCurrency = (value) => {
  const numValue = Number(value) || 0
  return new Intl.NumberFormat('vi-VN', {
    style: 'currency',
    currency: 'VND',
    minimumFractionDigits: 0
  }).format(numValue)
}

const formatDate = (dateString) => {
    if (!dateString) return ''
    // Xử lý format dd/MM/yyyy HH:mm hoặc dd/MM/yyyy
    const [datePart, timePart] = dateString.split(' ')
    const [day, month, year] = datePart.split('/')
    if (!day || !month || !year) return dateString
    // Nếu có time, trả về dạng dd/MM/yyyy HH:mm, nếu không chỉ trả về dd/MM/yyyy
    if (timePart) {
        return `${day}/${month}/${year} ${timePart}`
    }
    return `${day}/${month}/${year}`
}

// Trạng thái tiếng Việt cho card Tổng kết giáo viên
const statusVi = (status) => {
    if (!status) return ''
    const normalized = String(status).toLowerCase().trim()
    const map = {
        draft: 'Nháp',
        approved: 'Đã duyệt',
        locked: 'Đã khóa',
    }
    return map[normalized] || status
}
</script>
