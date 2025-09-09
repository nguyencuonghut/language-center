<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { Head, router } from '@inertiajs/vue3'
import { ref, computed, onMounted, onUnmounted } from 'vue'
import Card from 'primevue/card'
import Tag from 'primevue/tag'
import Button from 'primevue/button'
import DatePicker from 'primevue/datepicker'
import Select from 'primevue/select'
import Dialog from 'primevue/dialog'
import InputText from 'primevue/inputtext'
import Menu from 'primevue/menu'
import ProgressBar from 'primevue/progressbar'
import Chip from 'primevue/chip'
import Checkbox from 'primevue/checkbox'
import { usePageToast } from '@/composables/usePageToast'

defineOptions({ layout: AppLayout })

const props = defineProps({
    kpi: Object,
    todaySchedule: Array,
    weekSchedule: Array,
    alerts: Object,
    recentTimesheets: Array,
    studentsAttention: Array,
    upcomingSubstitutions: Array,
    range: { type: Object, default: () => ({ from: null, to: null }) }, // {from,to} ngày bắt đầu/kết thúc của khoảng 14 ngày tới
    meta: Object,
    branches: Array, // Danh sách chi nhánh (nếu teacher dạy nhiều CN)
})

// State cho filters và UI
const selectedWeek = ref(new Date(props.meta.week_range[0]))
const selectedBranch = ref(null)
const showWeekScheduleModal = ref(false)
const selectedSessionDetail = ref(null)

// Phase 3: Advanced Features State
const searchQuery = ref('')
const showQuickActionsMenu = ref(false)
const isAutoRefreshEnabled = ref(true)
const refreshInterval = ref(null)
const notifications = ref([
    {
        id: 1,
        type: 'warning',
        title: 'Buổi học sắp bắt đầu',
        message: 'Lớp ENG101-A01 sẽ bắt đầu trong 15 phút tại phòng 201',
        timestamp: new Date(Date.now() - 5 * 60 * 1000), // 5 minutes ago
        read: false
    },
    {
        id: 2,
        type: 'error',
        title: 'Timesheet quá hạn',
        message: 'Bạn có 2 timesheet chưa nộp cho tuần trước',
        timestamp: new Date(Date.now() - 2 * 60 * 60 * 1000), // 2 hours ago
        read: false
    },
    {
        id: 3,
        type: 'success',
        title: 'Lương đã được duyệt',
        message: 'Lương tháng 8 đã được duyệt và sẽ chuyển vào tài khoản trong 2 ngày',
        timestamp: new Date(Date.now() - 24 * 60 * 60 * 1000), // 1 day ago
        read: true
    },
    {
        id: 4,
        type: 'info',
        title: 'Thông báo từ ban quản lý',
        message: 'Cuộc họp giáo viên sẽ diễn ra vào thứ 6 tuần này lúc 14:00',
        timestamp: new Date(Date.now() - 3 * 24 * 60 * 60 * 1000), // 3 days ago
        read: true
    }
])
const showNotifications = ref(false)
const quickActionsMenuRef = ref(null)
const quickActionsMenu = ref(null)
const quickActionsButton = ref(null)



// Toast notifications
const { showSuccess, showError, showInfo } = usePageToast()

// Computed properties for alerts
const hasAlerts = computed(() => {
    return props.alerts.sessions_no_attendance > 0 ||
           props.alerts.pending_timesheets > 0 ||
           props.alerts.overdue_sessions > 0 ||
           props.alerts.pending_substitutions > 0
})

// Phase 3: Advanced Computed Properties
const filteredTodaySchedule = computed(() => {
    if (!searchQuery.value) return props.todaySchedule

    const query = searchQuery.value.toLowerCase()
    return props.todaySchedule.filter(session =>
        session.class_name.toLowerCase().includes(query) ||
        session.class_code.toLowerCase().includes(query) ||
        session.room.toLowerCase().includes(query)
    )
})


const filteredTimesheets = computed(() => {
    if (!searchQuery.value) return props.recentTimesheets

    const query = searchQuery.value.toLowerCase()
    return props.recentTimesheets.filter(timesheet =>
        timesheet.class_name.toLowerCase().includes(query) ||
        timesheet.class_code.toLowerCase().includes(query)
    )
})

const urgentTasks = computed(() => {
    const tasks = []

    // Add urgent sessions needing attendance
    if (props.alerts.sessions_no_attendance > 0) {
        tasks.push({
            type: 'attendance',
            count: props.alerts.sessions_no_attendance,
            message: `${props.alerts.sessions_no_attendance} buổi học cần điểm danh`,
            urgency: 'high'
        })
    }

    // Add overdue sessions
    if (props.alerts.overdue_sessions > 0) {
        tasks.push({
            type: 'overdue',
            count: props.alerts.overdue_sessions,
            message: `${props.alerts.overdue_sessions} buổi học quá hạn`,
            urgency: 'critical'
        })
    }

    // Add pending timesheets
    if (props.alerts.pending_timesheets > 0) {
        tasks.push({
            type: 'timesheet',
            count: props.alerts.pending_timesheets,
            message: `${props.alerts.pending_timesheets} timesheet chờ duyệt`,
            urgency: 'medium'
        })
    }

    // Add pending substitutions
    if (props.alerts.pending_substitutions > 0) {
        tasks.push({
            type: 'substitution',
            count: props.alerts.pending_substitutions,
            message: `${props.alerts.pending_substitutions} buổi dạy thay chờ duyệt`,
            urgency: 'high'
        })
    }

    return tasks.sort((a, b) => {
        const urgencyOrder = { critical: 3, high: 2, medium: 1 }
        return urgencyOrder[b.urgency] - urgencyOrder[a.urgency]
    })
})

const quickActionsItems = computed(() => {
    return [
        {
            label: 'Điểm danh nhanh',
            icon: 'pi pi-check-square',
            command: () => quickAttendance()
        },
        {
            label: 'Tạo timesheet',
            icon: 'pi pi-file-edit',
            command: () => createTimesheet()
        },
        {
            label: 'Báo cáo tháng',
            icon: 'pi pi-chart-bar',
            command: () => viewMonthlyReport()
        },
        {
            separator: true
        },
    ]
})

// Helper functions
const formatCurrency = (amount) => {
    if (amount == null || isNaN(amount)) return ''
    return new Intl.NumberFormat('vi-VN', {
        style: 'currency',
        currency: 'VND',
        maximumFractionDigits: 0
    }).format(amount)
}
const formatTime = (time) => {
    if (!time) return ''
    // Nếu đã là dạng HH:mm thì trả về luôn
    if (/^\d{2}:\d{2}$/.test(time)) return time
    // Nếu là dạng HH:mm:ss hoặc có giây
    if (/^\d{2}:\d{2}:\d{2}$/.test(time)) return time.slice(0,5)
    // Nếu là số (giây hoặc phút), có thể xử lý thêm nếu cần
    return time
}
/** dd/mm/yyyy (hiển thị, không đổi timezone) */
function toDdMmYyyy(d) {
if (!d) return '—'
const dt = new Date(String(d).replace(' ', 'T'))
if (isNaN(dt.getTime())) {
    const [y,m,day] = String(d).split('-')
    if (y && m && day) return `${day.padStart(2,'0')}/${m.padStart(2,'0')}/${y}`
    return String(d)
}
const dd = String(dt.getDate()).padStart(2,'0')
const mm = String(dt.getMonth()+1).padStart(2,'0')
const yy = dt.getFullYear()
return `${dd}/${mm}/${yy}`
}

const formatNumber = (num) => {
    return new Intl.NumberFormat('vi-VN').format(num)
}

const formatDate = (dateString) => {
    if (!dateString) return ''
    try {
        // Handle multiple datetime formats:
        // 1. YYYY-MM-DD (date only)
        // 2. YYYY-MM-DD HH:MM:SS (datetime with space)
        // 3. 2025-09-05T22:08:03.000000Z (ISO 8601 UTC)
        let dateOnly
        if (dateString.includes('T')) {
            // ISO 8601 format: 2025-09-05T22:08:03.000000Z
            dateOnly = dateString.split('T')[0]
        } else if (dateString.includes(' ')) {
            // Standard datetime format: 2025-09-05 22:08:03
            dateOnly = dateString.split(' ')[0]
        } else {
            // Date only format: 2025-09-05
            dateOnly = dateString
        }
        const dateParts = String(dateOnly).split('-')
        if (dateParts.length !== 3) {
            console.warn('formatDate - Invalid date format:', dateString, 'dateOnly:', dateOnly)
            return dateString // Return original string if format is wrong
        }
        const [year, month, day] = dateParts.map(Number)
        // Validate date components
        if (isNaN(year) || isNaN(month) || isNaN(day) ||
            year < 1900 || year > 2100 ||
            month < 1 || month > 12 ||
            day < 1 || day > 31) {
            console.warn('formatDate - Invalid date values:', { year, month, day, originalInput: dateString })
            return dateString
        }
        const date = new Date(year, month - 1, day)
        // Check if the date is valid
        if (isNaN(date.getTime())) {
            console.warn('formatDate - Invalid date object:', dateString)
            return dateString
        }
        return new Intl.DateTimeFormat('vi-VN', {
            day: '2-digit',
            month: '2-digit',
            year: 'numeric',
            timeZone: 'Asia/Ho_Chi_Minh'
        }).format(date)
    } catch (error) {
        console.error('formatDate - Error formatting date:', dateString, error)
        return dateString // Return original string on error
    }
}

const formatDateTime = (dateTimeString) => {
    if (!dateTimeString) return ''

    try {
        // Handle different datetime formats and delegate to formatDate for the date part
        // formatDate now handles ISO 8601, standard datetime, and date-only formats
        return formatDate(dateTimeString)
    } catch (error) {
        console.error('formatDateTime - Error formatting datetime:', dateTimeString, error)
        return dateTimeString
    }
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

    if (!date) return ''

    try {
        // Support ISO 8601, datetime, and date-only strings
        let dateOnly = String(date)
        if (dateOnly.includes('T')) {
            dateOnly = dateOnly.split('T')[0]
        } else if (dateOnly.includes(' ')) {
            dateOnly = dateOnly.split(' ')[0]
        }

        const dateParts = dateOnly.split('-')
        if (dateParts.length !== 3) {
            console.warn('getDayOfWeek - Invalid date format:', date)
            return ''
        }

        const [year, month, day] = dateParts.map(Number)
        if (isNaN(year) || isNaN(month) || isNaN(day)) {
            console.warn('getDayOfWeek - Invalid date values:', { year, month, day, originalInput: date })
            return ''
        }

        const dateObj = new Date(year, month - 1, day)
        if (isNaN(dateObj.getTime())) {
            console.warn('getDayOfWeek - Invalid date object:', date)
            return ''
        }

        const dayIndex = dateObj.getDay()
        return days[dayIndex] || ''
    } catch (error) {
        console.error('getDayOfWeek - Error:', date, error)
        return ''
    }
}

const isToday = (date) => {
    return date === props.meta.today
}

const weekDates = computed(() => {
    if (!props.meta?.week_range?.[0]) {
        console.error('weekDates computed - No week_range[0] found, props.meta:', props.meta)
        return []
    }

    try {
        // Parse date từ props.meta.week_range[0] trong local timezone
        const startDateStr = props.meta.week_range[0]
        const dateParts = String(startDateStr).split('-')

        if (dateParts.length !== 3) {
            console.error('weekDates computed - Invalid date format:', startDateStr)
            return []
        }

        const [year, month, day] = dateParts.map(Number)

        if (isNaN(year) || isNaN(month) || isNaN(day)) {
            console.error('weekDates computed - Invalid date values:', { year, month, day, startDateStr })
            return []
        }

        const startDate = new Date(year, month - 1, day) // month - 1 vì JavaScript month là 0-indexed

        const dates = []
        for (let i = 0; i < 7; i++) {
            const date = new Date(startDate)
            date.setDate(startDate.getDate() + i)

            // Format thành string trong local timezone
            const dateYear = date.getFullYear()
            const dateMonth = String(date.getMonth() + 1).padStart(2, '0')
            const dateDay = String(date.getDate()).padStart(2, '0')
            const dateString = `${dateYear}-${dateMonth}-${dateDay}`
            dates.push(dateString)
        }

        return dates
    } catch (error) {
        console.error('weekDates computed - Error:', error)
        return []
    }
})

// Action handlers
const goToAttendance = (sessionId) => {
    router.visit(`/teacher/attendance/sessions/${sessionId}`)
}

const viewClassDetail = (classId) => {
    // Placeholder - tùy route bạn đã setup
    console.log('View class detail:', classId)
}

// Week picker handler
const onWeekChange = (newDate) => {
    // Đảm bảo chúng ta làm việc với local date chứ không phải UTC
    const selectedDate = new Date(newDate.getTime() - (newDate.getTimezoneOffset() * 60000))

    // Tính toán start of week (Monday) trong local timezone
    const startOfWeek = new Date(selectedDate)
    const day = startOfWeek.getDay()
    const diff = startOfWeek.getDate() - day + (day === 0 ? -6 : 1) // Monday as first day
    startOfWeek.setDate(diff)

    // Format date trong local timezone
    const year = startOfWeek.getFullYear()
    const month = String(startOfWeek.getMonth() + 1).padStart(2, '0')
    const date = String(startOfWeek.getDate()).padStart(2, '0')
    const localDateString = `${year}-${month}-${date}`

    router.visit(route('teacher.dashboard'), {
        data: {
            week: localDateString
        },
        preserveState: true
    })
}

// Branch filter handler
const onBranchChange = (branchId) => {
    router.visit(route('teacher.dashboard'), {
        data: {
            branch_id: branchId
        },
        preserveState: true
    })
}

// Modal handlers
const viewSessionDetail = (session) => {
    selectedSessionDetail.value = session
    showWeekScheduleModal.value = true
}

const closeModal = () => {
    showWeekScheduleModal.value = false
    selectedSessionDetail.value = null
}

// Shortcut action handlers
const viewFullSchedule = () => {
    // Placeholder - navigate to full schedule view
    console.log('Navigate to full schedule')
    // router.visit('/teacher/schedule')
}

const lockLastWeekTimesheet = () => {
    // Placeholder - lock last week timesheet
    console.log('Lock last week timesheet')
    // Implement API call to lock timesheets
}

const createChangeRequest = () => {
    // Placeholder - create change request
    console.log('Create change request')
    // router.visit('/teacher/change-requests/create')
}

// Phase 3: Lifecycle Hooks
onMounted(() => {
    // Start auto-refresh if enabled
    if (isAutoRefreshEnabled.value) {
        startAutoRefresh()
    }

    // Show welcome notification
    showInfo('Dashboard loaded', 'Welcome back! All data is up to date.')

    // Load initial notifications
    loadNotifications()
})

onUnmounted(() => {
    // Cleanup auto-refresh
    if (refreshInterval.value) {
        clearInterval(refreshInterval.value)
    }
})

// Phase 3: Advanced Action Handlers
const startAutoRefresh = () => {
    refreshInterval.value = setInterval(() => {
        router.reload({
            only: ['kpi', 'todaySchedule', 'alerts', 'recentTimesheets'],
            preserveState: true
        })
        addNotification('Auto-refresh', 'Dashboard data updated', 'info')
    }, 60000) // Refresh every minute
}

const stopAutoRefresh = () => {
    if (refreshInterval.value) {
        clearInterval(refreshInterval.value)
        refreshInterval.value = null
    }
}

const toggleAutoRefresh = () => {
    isAutoRefreshEnabled.value = !isAutoRefreshEnabled.value

    if (isAutoRefreshEnabled.value) {
        startAutoRefresh()
        showSuccess('Auto-refresh enabled', 'Dashboard will update every minute')
    } else {
        stopAutoRefresh()
        showInfo('Auto-refresh disabled', 'Dashboard will not auto-update')
    }
}

const addNotification = (title, message, type = 'info') => {
    const notification = {
        id: Date.now(),
        title,
        message,
        type,
        timestamp: new Date(),
        read: false
    }

    notifications.value.unshift(notification)

    // Keep only last 10 notifications
    if (notifications.value.length > 10) {
        notifications.value = notifications.value.slice(0, 10)
    }
}

const markNotificationAsRead = (notificationId) => {
    const notification = notifications.value.find(n => n.id === notificationId)
    if (notification) {
        notification.read = true
    }
}

const clearAllNotifications = () => {
    notifications.value = []
    showInfo('Notifications cleared', 'All notifications have been removed')
}

const loadNotifications = () => {
    // Simulate loading initial notifications
    if (props.alerts.sessions_no_attendance > 0) {
        addNotification(
            'Attendance Required',
            `${props.alerts.sessions_no_attendance} sessions need attendance`,
            'warning'
        )
    }

    if (props.alerts.overdue_sessions > 0) {
        addNotification(
            'Overdue Sessions',
            `${props.alerts.overdue_sessions} sessions are overdue`,
            'error'
        )
    }

    if (props.alerts.pending_substitutions > 0) {
        addNotification(
            'Pending Substitutions',
            `${props.alerts.pending_substitutions} substitution requests need approval`,
            'warning'
        )
    }
}

const quickAttendance = () => {
    // Find the next session that needs attendance
    const nextSession = props.todaySchedule.find(s => !s.attendance_taken && s.status === 'planned')

    if (nextSession) {
        goToAttendance(nextSession.id)
        showSuccess('Redirecting', `Going to attendance for ${nextSession.class_name}`)
    } else {
        showInfo('No sessions available', 'All sessions have been attended or no sessions today')
    }
}

const createTimesheet = () => {
    // Placeholder for timesheet creation
    showInfo('Feature coming soon', 'Timesheet creation will be available soon')
}

const viewMonthlyReport = () => {
    // Placeholder for monthly report
    showInfo('Feature coming soon', 'Monthly reports will be available soon')
}

const openSettings = () => {
    // Placeholder for settings
    showInfo('Feature coming soon', 'Settings panel will be available soon')
}

const exportData = (format) => {
    // Placeholder for data export
    showSuccess('Export started', `Exporting data in ${format} format...`)

    // Simulate export process
    setTimeout(() => {
        showSuccess('Export completed', 'Your data has been exported successfully')
    }, 2000)
}

const refreshDashboard = () => {
    router.reload({ preserveState: true })
    showSuccess('Dashboard refreshed', 'All data has been updated')
}

const toggleQuickActionsMenu = (event) => {
    quickActionsMenu.value.toggle(event)
}


</script>

<template>
    <Head title="Teacher Dashboard" />

    <!-- Header with Filters -->
    <div class="mb-8">
        <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4 mb-4">
            <div>
                <h1 class="text-3xl font-bold text-gray-900 dark:text-white">
                    Teacher Dashboard
                </h1>
                <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                    Xin chào, {{ meta.teacher_name }}! Chúc bạn một ngày làm việc hiệu quả.
                </p>
            </div>

            <!-- Advanced Actions Row -->
            <div class="flex flex-col sm:flex-row gap-3 items-end">
                <!-- Search Bar -->
                <div class="relative w-64">
                    <div class="flex items-center border border-gray-300 dark:border-gray-600 rounded-md bg-white dark:bg-gray-800 focus-within:ring-2 focus-within:ring-blue-500">
                        <i class="pi pi-search text-gray-400 ml-3 flex-shrink-0"></i>
                        <input
                            v-model="searchQuery"
                            type="text"
                            placeholder="Tìm kiếm lớp, phòng..."
                            class="flex-1 px-2 py-2 bg-transparent border-0 focus:outline-none focus:ring-0 text-gray-900 dark:text-white placeholder-gray-500 dark:placeholder-gray-400 text-sm min-w-0"
                        />
                        <Button
                            v-if="searchQuery"
                            icon="pi pi-times"
                            text
                            size="small"
                            class="!p-1 !w-6 !h-6 mr-1 text-gray-400 hover:text-gray-600 flex-shrink-0"
                            @click="searchQuery = ''"
                        />
                    </div>
                </div>

                <!-- Notifications Button -->
                <div class="relative">
                    <Button
                        icon="pi pi-bell"
                        size="small"
                        text
                        severity="secondary"
                        @click="showNotifications = !showNotifications"
                        :badge="notifications.filter(n => !n.read).length > 0 ? notifications.filter(n => !n.read).length.toString() : null"
                        badgeClass="p-badge-danger"
                        title="Thông báo"
                    />
                </div>

                <!-- Quick Actions Menu -->
                <div class="relative">
                    <Button
                        icon="pi pi-ellipsis-v"
                        size="small"
                        text
                        severity="secondary"
                        @click="toggleQuickActionsMenu"
                        title="Hành động nhanh"
                        ref="quickActionsButton"
                    />
                    <Menu
                        ref="quickActionsMenu"
                        :model="quickActionsItems"
                        :popup="true"
                    />
                </div>
            </div>
        </div>

        <!-- Filter Row -->
        <div class="flex flex-col sm:flex-row gap-3">
            <!-- Branch Filter (if teacher teaches in multiple branches) -->
            <div v-if="branches && branches.length > 1" class="flex items-center gap-2">
                <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Chi nhánh:</label>
                <Select
                    v-model="selectedBranch"
                    :options="branches"
                    optionLabel="name"
                    optionValue="id"
                    placeholder="Tất cả chi nhánh"
                    class="w-48"
                    @change="onBranchChange"
                />
            </div>

            <!-- Week Picker -->
            <div class="flex items-center gap-2">
                <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Tuần:</label>
                <DatePicker
                    v-model="selectedWeek"
                    selectionMode="single"
                    :inline="false"
                    :showWeek="true"
                    placeholder="Chọn tuần"
                    class="w-40"
                    @date-select="onWeekChange"
                />
            </div>

            <!-- Auto-refresh Toggle -->
            <div class="flex items-center gap-2">
                <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Tự động làm mới:</label>
                <Button
                    :icon="isAutoRefreshEnabled ? 'pi pi-pause' : 'pi pi-play'"
                    :label="isAutoRefreshEnabled ? 'Đang bật' : 'Đã tắt'"
                    size="small"
                    :severity="isAutoRefreshEnabled ? 'success' : 'secondary'"
                    text
                    @click="toggleAutoRefresh"
                />
            </div>

            <!-- Manual Refresh -->
            <Button
                icon="pi pi-refresh"
                size="small"
                text
                severity="secondary"
                @click="refreshDashboard"
                title="Làm mới"
            />
        </div>
    </div>

    <!-- Alerts Row -->
    <div v-if="hasAlerts" class="mb-6 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
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

        <!-- Pending substitutions -->
        <div v-show="alerts.pending_substitutions > 0" class="bg-purple-50 dark:bg-purple-900/20 border border-purple-200 dark:border-purple-800 rounded-lg p-4">
            <div class="flex items-center">
                <i class="pi pi-user-plus text-purple-600 dark:text-purple-400 mr-3"></i>
                <div>
                    <p class="text-sm font-medium text-purple-800 dark:text-purple-200">
                        {{ alerts.pending_substitutions }} buổi dạy thay chờ duyệt
                    </p>
                    <p class="text-xs text-purple-600 dark:text-purple-300">
                        Cần phê duyệt từ quản lý
                    </p>
                </div>
            </div>
        </div>
    </div>

    <!-- Notifications Panel -->
    <div v-if="showNotifications" class="mb-6">
        <Card>
            <template #content>
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                        <i class="pi pi-bell mr-2"></i>
                        Thông báo
                        <Chip
                            v-if="notifications.filter(n => !n.read).length > 0"
                            :label="notifications.filter(n => !n.read).length.toString()"
                            class="ml-2"
                            severity="danger"
                        />
                    </h3>
                    <div class="flex gap-2">
                        <Button
                            label="Đánh dấu tất cả đã đọc"
                            size="small"
                            text
                            @click="notifications.forEach(n => n.read = true)"
                        />
                        <Button
                            label="Xóa tất cả"
                            size="small"
                            text
                            severity="danger"
                            @click="clearAllNotifications"
                        />
                        <Button
                            icon="pi pi-times"
                            size="small"
                            text
                            @click="showNotifications = false"
                        />
                    </div>
                </div>

                <div class="space-y-2 max-h-64 overflow-y-auto">
                    <div v-if="notifications.length === 0" class="text-center py-4 text-gray-500 dark:text-gray-400">
                        <i class="pi pi-check-circle text-2xl mb-2"></i>
                        <p>Không có thông báo nào</p>
                    </div>

                    <div
                        v-for="notification in notifications"
                        :key="notification.id"
                        :class="[
                            'p-3 rounded-lg border transition-colors cursor-pointer',
                            notification.read
                                ? 'bg-gray-50 dark:bg-gray-800 border-gray-200 dark:border-gray-700'
                                : 'bg-blue-50 dark:bg-blue-900/20 border-blue-200 dark:border-blue-800'
                        ]"
                        @click="markNotificationAsRead(notification.id)"
                    >
                        <div class="flex items-start justify-between">
                            <div class="flex-1">
                                <div class="flex items-center gap-2">
                                    <i :class="[
                                        'pi text-sm',
                                        notification.type === 'error' ? 'pi-exclamation-triangle text-red-500' :
                                        notification.type === 'warning' ? 'pi-exclamation-triangle text-yellow-500' :
                                        notification.type === 'success' ? 'pi-check-circle text-green-500' :
                                        'pi-info-circle text-blue-500'
                                    ]"></i>
                                    <p class="text-sm font-medium text-gray-900 dark:text-white">
                                        {{ notification.title }}
                                    </p>
                                    <div v-if="!notification.read" class="w-2 h-2 bg-blue-500 rounded-full"></div>
                                </div>
                                <p class="text-xs text-gray-600 dark:text-gray-400 mt-1">
                                    {{ notification.message }}
                                </p>
                                <p class="text-xs text-gray-500 mt-1">
                                    {{ formatDateTime(notification.timestamp.toISOString()) }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </template>
        </Card>
    </div>

    <!-- KPI Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-6 mb-8">
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

        <!-- Upcoming Substitutions -->
        <Card>
            <template #content>
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-600 dark:text-gray-400">
                            Buổi dạy thay (14 ngày)
                        </p>
                        <p class="text-3xl font-bold text-gray-900 dark:text-white">
                            {{ formatNumber(kpi.upcoming_substitutions.total) }}
                        </p>
                    </div>
                    <div class="bg-purple-100 dark:bg-purple-900/20 p-3 rounded-full">
                        <i class="pi pi-user-plus text-purple-600 dark:text-purple-400 text-xl"></i>
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
                        <span v-if="searchQuery" class="text-sm font-normal text-blue-600 dark:text-blue-400 ml-2">
                            ({{ filteredTodaySchedule.length }} kết quả cho "{{ searchQuery }}")
                        </span>
                    </h3>
                    <i class="pi pi-calendar-plus text-blue-600 dark:text-blue-400"></i>
                </div>
                <div class="space-y-3">
                    <div v-if="filteredTodaySchedule.length === 0" class="text-center py-8">
                        <i class="pi pi-calendar text-gray-400 text-3xl mb-2"></i>
                        <p class="text-gray-500 dark:text-gray-400">
                            {{ searchQuery ? 'Không tìm thấy buổi học nào phù hợp' : 'Không có buổi học nào hôm nay' }}
                        </p>
                    </div>
                    <div v-else v-for="session in filteredTodaySchedule" :key="session.id"
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

    <!-- Upcoming Substitutions -->
    <div class="mb-8">
        <Card>
            <template #content>
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                        Buổi dạy thay sắp tới trong 14 ngày tới
                    </h3>
                    <i class="pi pi-user-plus text-purple-600 dark:text-purple-400"></i>
                </div>
                <div class="space-y-3">
                    <div v-if="upcomingSubstitutions.length === 0" class="text-center py-8">
                        <i class="pi pi-calendar-times text-gray-400 text-3xl mb-2"></i>
                        <p class="text-gray-500 dark:text-gray-400">
                            Không có buổi dạy thay nào trong 14 ngày tới
                        </p>
                    </div>
                    <div v-else v-for="substitution in upcomingSubstitutions" :key="substitution.id"
                         class="p-4 bg-purple-50 dark:bg-purple-900/20 rounded-lg border border-purple-200 dark:border-purple-800">
                        <div class="flex justify-between items-start">
                            <div class="flex-1">
                                <div class="flex items-center gap-2 mb-1">
                                    <h4 class="text-sm font-medium text-gray-900 dark:text-white">
                                        {{ substitution.class_name }}
                                    </h4>
                                    <span class="text-xs text-gray-500 dark:text-gray-400">
                                        ({{ substitution.class_code }})
                                    </span>
                                    <Tag
                                        v-if="substitution.approved_at"
                                        value="Đã duyệt"
                                        severity="success"
                                        class="text-xs"
                                    />
                                    <Tag
                                        v-else
                                        value="Chờ duyệt"
                                        severity="warning"
                                        class="text-xs"
                                    />
                                </div>
                                <p class="text-xs text-gray-500 dark:text-gray-400">
                                    <i class="pi pi-calendar mr-1"></i>
                                    {{ toDdMmYyyy(substitution.date) }} •
                                    <i class="pi pi-clock ml-2 mr-1"></i>
                                    {{ formatTime(substitution.start_time) }} - {{ formatTime(substitution.end_time) }}
                                </p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">
                                    <i class="pi pi-building mr-1"></i>
                                    {{ substitution.room }} •
                                    <i class="pi pi-hashtag ml-2 mr-1"></i>
                                    Buổi {{ substitution.session_no }}
                                </p>
                                <p v-if="substitution.reason" class="text-xs text-purple-600 dark:text-purple-400 mt-1">
                                    <i class="pi pi-info-circle mr-1"></i>
                                    Lý do: {{ substitution.reason }}
                                </p>
                                <p v-if="substitution.rate_override" class="text-xs text-green-600 dark:text-green-400 mt-1">
                                    <i class="pi pi-dollar mr-1"></i>
                                    Tiền buổi: {{ formatCurrency(substitution.rate_override) }}
                                </p>
                            </div>
                            <div class="flex flex-col items-end space-y-2">
                                <div class="text-sm font-medium text-gray-900 dark:text-white">
                                    {{ getDayOfWeek(substitution.date) }}
                                </div>
                                <div class="text-xs text-gray-500">
                                    {{ new Date(substitution.date).getDate() }}
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
                    <div v-for="(date, index) in weekDates" :key="date" class="space-y-2">
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
                                     'p-2 rounded text-xs cursor-pointer transition-colors hover:bg-opacity-80',
                                     isToday(session.date)
                                        ? 'bg-blue-100 dark:bg-blue-900/30 text-blue-800 dark:text-blue-200 border border-blue-300 dark:border-blue-700'
                                        : 'bg-gray-50 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-600'
                                 ]"
                                 @click="viewSessionDetail(session)">
                                <p class="font-medium">{{ session.class_name }}</p>
                                <p>{{ formatTime(session.start_time) }} - {{ formatTime(session.end_time) }}</p>
                                <p class="text-gray-500">{{ session.room }}</p>
                                <Tag
                                    v-if="session.status !== 'planned'"
                                    :value="getSessionStatusText(session.status)"
                                    :severity="getSessionStatusSeverity(session.status)"
                                    class="text-xs mt-1"
                                />
                            </div>
                        </div>
                    </div>
                </div>
            </template>
        </Card>
    </div>

    <!-- Recent Timesheets -->
    <div class="mb-8">
        <Card>
            <template #content>
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                        Bảng công gần đây
                        <span v-if="searchQuery" class="text-sm font-normal text-green-600 dark:text-green-400 ml-2">
                            ({{ filteredTimesheets.length }} kết quả cho "{{ searchQuery }}")
                        </span>
                    </h3>
                    <i class="pi pi-file-edit text-green-600 dark:text-green-400"></i>
                </div>
                <div class="space-y-3">
                    <div v-if="filteredTimesheets.length === 0" class="text-center py-8">
                        <i class="pi pi-inbox text-gray-400 text-3xl mb-2"></i>
                        <p class="text-gray-500 dark:text-gray-400">
                            {{ searchQuery ? 'Không tìm thấy timesheet nào phù hợp' : 'Chưa có bảng công nào' }}
                        </p>
                    </div>
                    <div v-else v-for="timesheet in filteredTimesheets" :key="timesheet.id"
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
                                Tạo: {{ formatDateTime(timesheet.created_at) }}
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

    <!-- Shortcut Actions -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <Card>
            <template #content>
                <div class="text-center p-4">
                    <i class="pi pi-calendar-plus text-blue-600 dark:text-blue-400 text-3xl mb-3"></i>
                    <h4 class="text-lg font-semibold text-gray-900 dark:text-white mb-2">
                        Xem lịch đầy đủ
                    </h4>
                    <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">
                        Xem lịch dạy theo tuần hoặc tháng
                    </p>
                    <Button
                        label="Xem lịch"
                        icon="pi pi-external-link"
                        size="small"
                        @click="viewFullSchedule"
                    />
                </div>
            </template>
        </Card>

        <Card>
            <template #content>
                <div class="text-center p-4">
                    <i class="pi pi-lock text-orange-600 dark:text-orange-400 text-3xl mb-3"></i>
                    <h4 class="text-lg font-semibold text-gray-900 dark:text-white mb-2">
                        Khóa timesheet
                    </h4>
                    <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">
                        Khóa timesheet tuần trước
                    </p>
                    <Button
                        label="Khóa timesheet"
                        icon="pi pi-lock"
                        size="small"
                        severity="warning"
                        @click="lockLastWeekTimesheet"
                    />
                </div>
            </template>
        </Card>

        <Card>
            <template #content>
                <div class="text-center p-4">
                    <i class="pi pi-send text-green-600 dark:text-green-400 text-3xl mb-3"></i>
                    <h4 class="text-lg font-semibold text-gray-900 dark:text-white mb-2">
                        Yêu cầu đổi buổi
                    </h4>
                    <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">
                        Gửi yêu cầu đổi buổi/phòng
                    </p>
                    <Button
                        label="Tạo yêu cầu"
                        icon="pi pi-plus"
                        size="small"
                        severity="success"
                        @click="createChangeRequest"
                    />
                </div>
            </template>
        </Card>
    </div>

    <!-- Session Detail Modal -->
    <Dialog
        v-model:visible="showWeekScheduleModal"
        modal
        :style="{ width: '450px' }"
        header="Chi tiết buổi học">
        <div v-if="selectedSessionDetail" class="space-y-4">
            <div class="flex items-center justify-between">
                <h4 class="text-lg font-semibold text-gray-900 dark:text-white">
                    {{ selectedSessionDetail.class_name }}
                </h4>
                <Tag
                    :value="getSessionStatusText(selectedSessionDetail.status)"
                    :severity="getSessionStatusSeverity(selectedSessionDetail.status)"
                />
            </div>

            <div class="grid grid-cols-2 gap-4 text-sm">
                <div>
                    <p class="text-gray-500 dark:text-gray-400">Mã lớp:</p>
                    <p class="font-medium">{{ selectedSessionDetail.class_code }}</p>
                </div>
                <div>
                    <p class="text-gray-500 dark:text-gray-400">Ngày:</p>
                    <p class="font-medium">{{ formatDate(selectedSessionDetail.date) }}</p>
                </div>
                <div>
                    <p class="text-gray-500 dark:text-gray-400">Thời gian:</p>
                    <p class="font-medium">{{ formatTime(selectedSessionDetail.start_time) }} - {{ formatTime(selectedSessionDetail.end_time) }}</p>
                </div>
                <div>
                    <p class="text-gray-500 dark:text-gray-400">Phòng:</p>
                    <p class="font-medium">{{ selectedSessionDetail.room }}</p>
                </div>
            </div>

            <div class="pt-4 border-t border-gray-200 dark:border-gray-600">
                <div class="flex gap-2">
                    <Button
                        v-if="isToday(selectedSessionDetail.date)"
                        label="Điểm danh"
                        icon="pi pi-check-square"
                        size="small"
                        @click="goToAttendance(selectedSessionDetail.id); closeModal()"
                    />
                    <Button
                        label="Xem danh sách lớp"
                        icon="pi pi-users"
                        size="small"
                        severity="secondary"
                        @click="viewClassDetail(selectedSessionDetail.class_id); closeModal()"
                    />
                </div>
            </div>
        </div>
    </Dialog>

    <!-- Export Dialog -->
    <!-- Tính năng này đã được tạm thời gỡ bỏ -->
</template>
