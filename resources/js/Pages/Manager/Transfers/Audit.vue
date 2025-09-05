<template>
    <Head title="Audit Log - Transfer Details" />

    <div class="p-6">
        <!-- Header -->
        <div class="mb-6">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900 mb-2">
                        Audit Log - Chuyển lớp #{{ transfer.id }}
                    </h1>
                    <p class="text-gray-600">
                        Lịch sử chi tiết tất cả thao tác với chuyển lớp này
                    </p>
                </div>
                <div class="flex gap-2">
                    <Button
                            label="Export CSV"
                            icon="pi pi-file-excel"
                            severity="success"
                            @click="exportAudit('csv')"
                        />
                        <Button
                            label="Export JSON"
                            icon="pi pi-file"
                            @click="exportAudit('json')"
                        />
                        <Button
                            label="Quay lại"
                            icon="pi pi-arrow-left"
                            severity="secondary"
                            @click="$router.back()"
                        />
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Transfer Overview -->
                <div class="lg:col-span-1">
                    <Card>
                        <template #title>
                            <div class="flex items-center gap-2">
                                <i class="pi pi-info-circle text-blue-500"></i>
                                Thông tin chuyển lớp
                            </div>
                        </template>
                        <template #content>
                            <div class="space-y-4">
                                <!-- Status -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">
                                        Trạng thái hiện tại
                                    </label>
                                    <Tag
                                        :value="getStatusLabel(transfer.status)"
                                        :severity="getStatusSeverity(transfer.status)"
                                        class="text-sm"
                                    />
                                </div>

                                <!-- Student -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">
                                        Học viên
                                    </label>
                                    <div class="bg-gray-50 p-3 rounded">
                                        <div class="font-medium">{{ transfer.student?.name }}</div>
                                        <div class="text-sm text-gray-500">{{ transfer.student?.code }}</div>
                                    </div>
                                </div>

                                <!-- Classes -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">
                                        Lớp học
                                    </label>
                                    <div class="space-y-2">
                                        <div class="bg-red-50 p-3 rounded border-l-4 border-red-500">
                                            <div class="text-sm text-gray-600">Từ lớp:</div>
                                            <div class="font-medium">{{ transfer.from_class?.code }} - {{ transfer.from_class?.name }}</div>
                                        </div>
                                        <div class="bg-green-50 p-3 rounded border-l-4 border-green-500">
                                            <div class="text-sm text-gray-600">Đến lớp:</div>
                                            <div class="font-medium">{{ transfer.to_class?.code }} - {{ transfer.to_class?.name }}</div>
                                        </div>
                                        <div v-if="transfer.retargeted_to_class" class="bg-blue-50 p-3 rounded border-l-4 border-blue-500">
                                            <div class="text-sm text-gray-600">Đổi hướng đến:</div>
                                            <div class="font-medium">{{ transfer.retargeted_to_class?.code }} - {{ transfer.retargeted_to_class?.name }}</div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Transfer Fee -->
                                <div v-if="transfer.transfer_fee">
                                    <label class="block text-sm font-medium text-gray-700 mb-1">
                                        Phí chuyển lớp
                                    </label>
                                    <div class="bg-gray-50 p-3 rounded">
                                        <div class="font-medium text-green-600">
                                            {{ formatCurrency(transfer.transfer_fee) }}
                                        </div>
                                    </div>
                                </div>

                                <!-- Reason -->
                                <div v-if="transfer.reason">
                                    <label class="block text-sm font-medium text-gray-700 mb-1">
                                        Lý do chuyển lớp
                                    </label>
                                    <div class="bg-gray-50 p-3 rounded text-sm">
                                        {{ transfer.reason }}
                                    </div>
                                </div>

                                <!-- Notes -->
                                <div v-if="transfer.notes">
                                    <label class="block text-sm font-medium text-gray-700 mb-1">
                                        Ghi chú
                                    </label>
                                    <div class="bg-gray-50 p-3 rounded text-sm">
                                        {{ transfer.notes }}
                                    </div>
                                </div>
                            </div>
                        </template>
                    </Card>
                </div>

                <!-- Audit Trail -->
                <div class="lg:col-span-2">
                    <Card>
                        <template #title>
                            <div class="flex items-center gap-2">
                                <i class="pi pi-clock text-blue-500"></i>
                                Lịch sử thao tác ({{ audit_trail.length }} sự kiện)
                            </div>
                        </template>
                        <template #content>
                            <div class="space-y-4">
                                <div
                                    v-for="(entry, index) in audit_trail"
                                    :key="entry.id"
                                    class="relative"
                                >
                                    <!-- Timeline connector -->
                                    <div
                                        v-if="index < audit_trail.length - 1"
                                        class="absolute left-6 top-12 w-0.5 h-full bg-gray-200"
                                    ></div>

                                    <!-- Entry card -->
                                    <div class="flex gap-4">
                                        <!-- Icon -->
                                        <div
                                            class="flex-shrink-0 w-12 h-12 rounded-full flex items-center justify-center text-white"
                                            :class="getEntryIconClass(entry.type)"
                                        >
                                            <i :class="getEntryIcon(entry.action)"></i>
                                        </div>

                                        <!-- Content -->
                                        <div class="flex-1 bg-white border rounded-lg p-4 shadow-sm">
                                            <div class="flex justify-between items-start mb-2">
                                                <div>
                                                    <h4 class="font-medium text-gray-900">
                                                        {{ entry.description }}
                                                    </h4>
                                                    <div class="text-sm text-gray-500 mt-1">
                                                        {{ entry.user }} • {{ formatDateTime(entry.timestamp) }}
                                                    </div>
                                                </div>
                                                <Tag
                                                    :value="getActionLabel(entry.action)"
                                                    :severity="entry.type === 'danger' ? 'danger' : entry.type === 'warning' ? 'warning' : entry.type === 'success' ? 'success' : 'info'"
                                                    class="text-xs"
                                                />
                                            </div>

                                            <!-- Details -->
                                            <div v-if="entry.details && Object.keys(entry.details).length" class="mt-3">
                                                <div class="bg-gray-50 rounded p-3">
                                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 text-sm">
                                                        <div
                                                            v-for="(value, key) in entry.details"
                                                            :key="key"
                                                            class="flex justify-between"
                                                        >
                                                            <span class="text-gray-600 capitalize">{{ formatFieldName(key) }}:</span>
                                                            <span class="font-medium text-right">{{ formatFieldValue(key, value) }}</span>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Empty state -->
                                <div v-if="!audit_trail.length" class="text-center py-8">
                                    <i class="pi pi-clock text-4xl text-gray-300 mb-4"></i>
                                    <p class="text-gray-500">Chưa có lịch sử thao tác nào</p>
                                </div>
                            </div>
                        </template>
                    </Card>
                </div>
            </div>
        </div>
</template>

<script setup>
import { ref } from 'vue'
import { Head } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import Card from 'primevue/card'
import Button from 'primevue/button'
import Tag from 'primevue/tag'

defineOptions({ layout: AppLayout })

const props = defineProps({
    transfer: Object,
    audit_trail: Array
})

// Methods
const exportAudit = (format) => {
    window.open(`/manager/transfers/${props.transfer.id}/audit/export?format=${format}`, '_blank')
}

const getStatusLabel = (status) => {
    const labels = {
        pending: 'Chờ duyệt',
        approved: 'Đã duyệt',
        completed: 'Hoàn thành',
        reverted: 'Đã hoàn tác'
    }
    return labels[status] || status
}

const getStatusSeverity = (status) => {
    const severities = {
        pending: 'warning',
        approved: 'info',
        completed: 'success',
        reverted: 'danger'
    }
    return severities[status] || 'secondary'
}

const getActionLabel = (action) => {
    const labels = {
        created: 'Tạo',
        status_change: 'Đổi trạng thái',
        field_change: 'Sửa đổi',
        reverted: 'Hoàn tác',
        retargeted: 'Đổi hướng'
    }
    return labels[action] || action
}

const getEntryIcon = (action) => {
    const icons = {
        created: 'pi pi-plus',
        status_change: 'pi pi-refresh',
        field_change: 'pi pi-pencil',
        reverted: 'pi pi-undo',
        retargeted: 'pi pi-arrow-right'
    }
    return icons[action] || 'pi pi-info'
}

const getEntryIconClass = (type) => {
    const classes = {
        info: 'bg-blue-500',
        success: 'bg-green-500',
        warning: 'bg-yellow-500',
        danger: 'bg-red-500'
    }
    return classes[type] || 'bg-gray-500'
}

const formatFieldName = (key) => {
    const names = {
        student: 'Học viên',
        from_class: 'Từ lớp',
        to_class: 'Đến lớp',
        new_target: 'Lớp đích mới',
        reason: 'Lý do',
        transfer_fee: 'Phí chuyển',
        from_status: 'Trạng thái cũ',
        to_status: 'Trạng thái mới',
        field: 'Trường',
        old_value: 'Giá trị cũ',
        new_value: 'Giá trị mới'
    }
    return names[key] || key.replace(/_/g, ' ')
}

const formatFieldValue = (key, value) => {
    if (!value && value !== 0) return 'N/A'

    if (key === 'transfer_fee') {
        return formatCurrency(value)
    }

    if (typeof value === 'object') {
        return JSON.stringify(value, null, 2)
    }

    return value.toString()
}

const formatCurrency = (amount) => {
    if (!amount && amount !== 0) return 'N/A'
    return new Intl.NumberFormat('vi-VN', {
        style: 'currency',
        currency: 'VND'
    }).format(amount)
}

const formatDateTime = (dateString) => {
    if (!dateString) return 'N/A'
    return new Date(dateString).toLocaleString('vi-VN', {
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit'
    })
}
</script>
