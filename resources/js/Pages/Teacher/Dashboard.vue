<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { Head, router } from '@inertiajs/vue3'
import { ref, computed } from 'vue'
import Card from 'primevue/card'
import Tag from 'primevue/tag'
import Button from 'primevue/button'

defineOptions({ layout: AppLayout })

const props = defineProps({
    kpi: Object,
    todaySchedule: Array,
    weekSchedule: Array,
    alerts: Object,
    recentTimesheets: Array,
    studentsAttention: Array,
    meta: Object,
})

// Computed properties for alerts
const hasAlerts = computed(() => {
    return props.alerts.sessions_no_attendance > 0 ||
           props.alerts.pending_timesheets > 0 ||
           props.alerts.overdue_sessions > 0
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

const formatTime = (time) => {
    return time
}

const formatDate = (dateString) => {
    return new Intl.DateTimeFormat('vi-VN', {
        day: '2-digit',
        month: '2-digit',
        hour: '2-digit',
        minute: '2-digit'
    }).format(new Date(dateString))
}

const getTimesheetStatusText = (status) => {
    const statusMap = {
        'draft': 'Bản nháp',
        'approved': 'Đã duyệt',
        'locked': 'Đã khóa'
    }
    return statusMap[status] || status
}

const getTimesheetStatusSeverity = (status) => {
    const severityMap = {
        'draft': 'warning',
        'approved': 'success',
        'locked': 'info'
    }
    return severityMap[status] || 'secondary'
}

const getSessionStatusText = (status) => {
    const statusMap = {
        'planned': 'Theo kế hoạch',
        'canceled': 'Đã hủy',
        'moved': 'Đã chuyển'
    }
    return statusMap[status] || status
}

const getSessionStatusSeverity = (status) => {
    const severityMap = {
        'planned': 'info',
        'canceled': 'danger',
        'moved': 'warning'
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

const getDayOfWeek = (date) => {
    const days = ['Chủ nhật', 'Thứ 2', 'Thứ 3', 'Thứ 4', 'Thứ 5', 'Thứ 6', 'Thứ 7']
    return days[new Date(date).getDay()]
}

const isToday = (date) => {
    return date === props.meta.today
}

const getWeekDates = () => {
    const startDate = new Date(props.meta.week_range[0])
    const dates = []
    for (let i = 0; i < 7; i++) {
        const date = new Date(startDate)
        date.setDate(startDate.getDate() + i)
        dates.push(date.toISOString().split('T')[0])
    }
    return dates
}

// Action handlers
const goToAttendance = (sessionId) => {
    router.visit(`/teacher/attendance/sessions/${sessionId}`)
}

const viewClassDetail = (classId) => {
    // Placeholder - tùy route bạn đã setup
    console.log('View class detail:', classId)
}
</script>

<template>
    <Head title="Teacher Dashboard" />

    <!-- Header -->
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-gray-900 dark:text-white">
            Teacher Dashboard
        </h1>
        <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
            Xin chào, {{ meta.teacher_name }}! Chúc bạn một ngày làm việc hiệu quả.
        </p>
    </div>

    <!-- Alerts Row -->
    <div v-if="hasAlerts" class="mb-6 grid grid-cols-1 md:grid-cols-3 gap-4">
        <!-- Sessions without attendance -->
        <div v-show="alerts.sessions_no_attendance > 0" class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg p-4">
            <div class="flex items-center">
                <i class="pi pi-exclamation-triangle text-red-600 dark:text-red-400 mr-3"></i>
                <div>
                    <p class="text-sm font-medium text-red-800 dark:text-red-200">
                        {{ alerts.sessions_no_attendance }} buổi học chưa điểm danh
                    </p>
                    <p class="text-xs text-red-600 dark:text-red-300">
                        Cần hoàn thành điểm danh
                    </p>
                </div>
            </div>
        </div>

        <!-- Pending timesheets -->
        <div v-show="alerts.pending_timesheets > 0" class="bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 rounded-lg p-4">
            <div class="flex items-center">
                <i class="pi pi-clock text-yellow-600 dark:text-yellow-400 mr-3"></i>
                <div>
                    <p class="text-sm font-medium text-yellow-800 dark:text-yellow-200">
                        {{ alerts.pending_timesheets }} timesheet chờ duyệt
                    </p>
                    <p class="text-xs text-yellow-600 dark:text-yellow-300">
                        Nhớ nộp timesheet đúng hạn
                    </p>
                </div>
            </div>
        </div>

        <!-- Overdue sessions -->
        <div v-show="alerts.overdue_sessions > 0" class="bg-orange-50 dark:bg-orange-900/20 border border-orange-200 dark:border-orange-800 rounded-lg p-4">
            <div class="flex items-center">
                <i class="pi pi-calendar-times text-orange-600 dark:text-orange-400 mr-3"></i>
                <div>
                    <p class="text-sm font-medium text-orange-800 dark:text-orange-200">
                        {{ alerts.overdue_sessions }} buổi học quá hạn
                    </p>
                    <p class="text-xs text-orange-600 dark:text-orange-300">
                        Quá 3 ngày chưa điểm danh
                    </p>
                </div>
            </div>
        </div>
    </div>

    <!-- KPI Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
        <!-- Classes Teaching -->
        <Card>
            <template #content>
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-600 dark:text-gray-400">
                            Lớp đang dạy
                        </p>
                        <p class="text-3xl font-bold text-gray-900 dark:text-white">
                            {{ formatNumber(kpi.classes_teaching.total) }}
                        </p>
                    </div>
                    <div class="bg-blue-100 dark:bg-blue-900/20 p-3 rounded-full">
                        <i class="pi pi-bookmark text-blue-600 dark:text-blue-400 text-xl"></i>
                    </div>
                </div>
            </template>
        </Card>

        <!-- Sessions Today -->
        <Card>
            <template #content>
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-600 dark:text-gray-400">
                            Buổi hôm nay
                        </p>
                        <p class="text-3xl font-bold text-gray-900 dark:text-white">
                            {{ formatNumber(kpi.sessions_today.total) }}
                        </p>
                    </div>
                    <div class="bg-green-100 dark:bg-green-900/20 p-3 rounded-full">
                        <i class="pi pi-calendar text-green-600 dark:text-green-400 text-xl"></i>
                    </div>
                </div>
            </template>
        </Card>

        <!-- Sessions This Week -->
        <Card>
            <template #content>
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-600 dark:text-gray-400">
                            Buổi tuần này
                        </p>
                        <p class="text-3xl font-bold text-gray-900 dark:text-white">
                            {{ formatNumber(kpi.sessions_this_week.total) }}
                        </p>
                    </div>
                    <div class="bg-purple-100 dark:bg-purple-900/20 p-3 rounded-full">
                        <i class="pi pi-calendar-plus text-purple-600 dark:text-purple-400 text-xl"></i>
                    </div>
                </div>
            </template>
        </Card>

        <!-- Need Attendance -->
        <Card>
            <template #content>
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-600 dark:text-gray-400">
                            Cần điểm danh
                        </p>
                        <p class="text-3xl font-bold text-gray-900 dark:text-white">
                            {{ formatNumber(kpi.need_attendance.total) }}
                        </p>
                    </div>
                    <div class="bg-orange-100 dark:bg-orange-900/20 p-3 rounded-full">
                        <i class="pi pi-check-square text-orange-600 dark:text-orange-400 text-xl"></i>
                    </div>
                </div>
            </template>
        </Card>
    </div>

    <!-- Today's Schedule & Students Attention -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
        <!-- Today's Schedule -->
        <Card>
            <template #content>
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                        Lịch dạy hôm nay
                    </h3>
                    <i class="pi pi-calendar-plus text-blue-600 dark:text-blue-400"></i>
                </div>
                <div class="space-y-3">
                    <div v-if="todaySchedule.length === 0" class="text-center py-8">
                        <i class="pi pi-calendar text-gray-400 text-3xl mb-2"></i>
                        <p class="text-gray-500 dark:text-gray-400">Không có buổi học nào hôm nay</p>
                    </div>
                    <div v-else v-for="session in todaySchedule" :key="session.id"
                         class="p-4 bg-gray-50 dark:bg-gray-700 rounded-lg">
                        <div class="flex justify-between items-start">
                            <div class="flex-1">
                                <div class="flex items-center gap-2 mb-1">
                                    <h4 class="text-sm font-medium text-gray-900 dark:text-white">
                                        {{ session.class_name }}
                                    </h4>
                                    <span class="text-xs text-gray-500 dark:text-gray-400">
                                        ({{ session.class_code }})
                                    </span>
                                    <!-- Session Status -->
                                    <Tag
                                        v-if="session.status !== 'planned'"
                                        :value="getSessionStatusText(session.status)"
                                        :severity="getSessionStatusSeverity(session.status)"
                                        class="text-xs"
                                    />
                                </div>
                                <p class="text-xs text-gray-500 dark:text-gray-400">
                                    <i class="pi pi-clock mr-1"></i>
                                    {{ formatTime(session.start_time) }} - {{ formatTime(session.end_time) }}
                                </p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">
                                    <i class="pi pi-building mr-1"></i>
                                    {{ session.room }} •
                                    <i class="pi pi-users ml-2 mr-1"></i>
                                    {{ session.students_count }} học viên
                                </p>
                                <div v-if="session.attendance_taken" class="mt-2">
                                    <span class="text-xs" :class="getAttendanceColor(session.present_count, session.total_attendances)">
                                        <i class="pi pi-check-circle mr-1"></i>
                                        Điểm danh: {{ session.present_count }}/{{ session.total_attendances }}
                                    </span>
                                </div>
                            </div>
                            <div class="flex flex-col items-end space-y-2">
                                <Tag
                                    :value="session.attendance_taken ? 'Đã điểm danh' : 'Chưa điểm danh'"
                                    :severity="session.attendance_taken ? 'success' : 'warning'"
                                    class="text-xs"
                                />
                                <div class="flex gap-1">
                                    <Button
                                        v-if="!session.attendance_taken && session.status === 'planned'"
                                        size="small"
                                        severity="secondary"
                                        text
                                        class="text-xs"
                                        @click="goToAttendance(session.id)"
                                    >
                                        <i class="pi pi-check-square mr-1"></i>
                                        Điểm danh
                                    </Button>
                                    <Button
                                        size="small"
                                        severity="secondary"
                                        text
                                        outlined
                                        class="text-xs"
                                        @click="viewClassDetail(session.class_id)"
                                    >
                                        <i class="pi pi-eye mr-1"></i>
                                        Xem lớp
                                    </Button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </template>
        </Card>

        <!-- Students Needing Attention -->
        <Card>
            <template #content>
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                        Học viên cần chú ý
                    </h3>
                    <i class="pi pi-exclamation-triangle text-yellow-600 dark:text-yellow-400"></i>
                </div>
                <div class="space-y-3">
                    <div v-if="studentsAttention.length === 0" class="text-center py-8">
                        <i class="pi pi-check-circle text-gray-400 text-3xl mb-2"></i>
                        <p class="text-gray-500 dark:text-gray-400">Tất cả học viên đều ổn</p>
                    </div>
                    <div v-else v-for="student in studentsAttention" :key="student.student_name + student.class_name"
                         class="p-3 bg-yellow-50 dark:bg-yellow-900/20 rounded-lg border border-yellow-200 dark:border-yellow-800">
                        <div class="flex justify-between items-start">
                            <div class="flex-1">
                                <div class="flex items-center gap-2 mb-1">
                                    <p class="text-sm font-medium text-gray-900 dark:text-white">
                                        {{ student.student_name }}
                                    </p>
                                    <span v-if="student.student_code" class="text-xs text-gray-500 dark:text-gray-400">
                                        ({{ student.student_code }})
                                    </span>
                                </div>
                                <p class="text-xs text-gray-500 dark:text-gray-400">
                                    <i class="pi pi-bookmark mr-1"></i>
                                    {{ student.class_name }} ({{ student.class_code }})
                                </p>
                                <p class="text-xs text-yellow-600 dark:text-yellow-400">
                                    <i class="pi pi-exclamation-triangle mr-1"></i>
                                    {{ student.issue }}
                                </p>
                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                    Tham dự: {{ student.sessions_attended }}/{{ student.total_sessions }} buổi
                                </p>
                            </div>
                            <div class="text-right">
                                <div class="text-sm font-medium text-red-600 dark:text-red-400">
                                    {{ student.attendance_rate }}%
                                </div>
                                <div class="text-xs text-gray-500">
                                    Tỷ lệ tham dự
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </template>
        </Card>
    </div>

    <!-- Weekly Schedule -->
    <div class="mb-8">
        <Card>
            <template #content>
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                        Lịch dạy tuần này
                    </h3>
                    <i class="pi pi-calendar text-blue-600 dark:text-blue-400"></i>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-7 gap-3">
                    <div v-for="(date, index) in getWeekDates()" :key="date" class="space-y-2">
                        <div class="text-center py-2 bg-gray-100 dark:bg-gray-700 rounded">
                            <p class="text-xs font-medium text-gray-600 dark:text-gray-300">
                                {{ getDayOfWeek(date) }}
                            </p>
                            <p class="text-xs text-gray-500">
                                {{ new Date(date).getDate() }}
                            </p>
                        </div>
                        <div class="space-y-1">
                            <div v-for="session in weekSchedule.filter(s => s.date === date)"
                                 :key="session.id"
                                 :class="[
                                     'p-2 rounded text-xs',
                                     isToday(session.date)
                                        ? 'bg-blue-100 dark:bg-blue-900/30 text-blue-800 dark:text-blue-200 border border-blue-300 dark:border-blue-700'
                                        : 'bg-gray-50 dark:bg-gray-700 text-gray-700 dark:text-gray-300'
                                 ]">
                                <p class="font-medium">{{ session.class_name }}</p>
                                <p>{{ formatTime(session.start_time) }} - {{ formatTime(session.end_time) }}</p>
                                <p class="text-gray-500">{{ session.room }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </template>
        </Card>
    </div>

    <!-- Recent Timesheets -->
    <div>
        <Card>
            <template #content>
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                        Bảng công gần đây
                    </h3>
                    <i class="pi pi-file-edit text-green-600 dark:text-green-400"></i>
                </div>
                <div class="space-y-3">
                    <div v-if="recentTimesheets.length === 0" class="text-center py-8">
                        <i class="pi pi-inbox text-gray-400 text-3xl mb-2"></i>
                        <p class="text-gray-500 dark:text-gray-400">Chưa có bảng công nào</p>
                    </div>
                    <div v-else v-for="timesheet in recentTimesheets" :key="timesheet.id"
                         class="flex items-center justify-between p-4 bg-gray-50 dark:bg-gray-700 rounded-lg">
                        <div class="flex-1">
                            <div class="flex items-center gap-2 mb-1">
                                <p class="text-sm font-medium text-gray-900 dark:text-white">
                                    {{ timesheet.class_name }}
                                </p>
                                <span class="text-xs text-gray-500 dark:text-gray-400">
                                    ({{ timesheet.class_code }})
                                </span>
                            </div>
                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                <i class="pi pi-calendar mr-1"></i>
                                Buổi: {{ formatDate(timesheet.session_date) }}
                            </p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                <i class="pi pi-clock mr-1"></i>
                                Tạo: {{ formatDate(timesheet.created_at) }}
                            </p>
                        </div>
                        <div class="flex items-center space-x-3">
                            <div class="text-right">
                                <p class="text-sm font-medium text-gray-900 dark:text-white">
                                    {{ formatCurrency(timesheet.amount) }}
                                </p>
                            </div>
                            <Tag
                                :value="getTimesheetStatusText(timesheet.status)"
                                :severity="getTimesheetStatusSeverity(timesheet.status)"
                                class="text-xs"
                            />
                        </div>
                    </div>
                </div>
            </template>
        </Card>
    </div>
</template>
