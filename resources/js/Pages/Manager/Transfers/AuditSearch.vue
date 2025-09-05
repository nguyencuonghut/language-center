<template>
    <Head title="Audit Search - Transfer History" />

    <div class="max-w-7xl mx-auto space-y-6">
        <!-- Page Header -->
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-slate-900 dark:text-slate-100">
                    Audit Log - Tìm kiếm lịch sử chuyển lớp
                </h1>
                <p class="text-slate-600 dark:text-slate-400">
                    Tra cứu và theo dõi chi tiết lịch sử thao tác chuyển lớp của tất cả người dùng
                </p>
            </div>
        </div>

        <!-- Search Filters -->
        <Card class="bg-white dark:bg-slate-800">
            <template #content>
                <form @submit.prevent="search" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4">
                    <!-- Text Search -->
                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">
                            Tìm kiếm
                        </label>
                        <InputText
                            v-model="form.q"
                            placeholder="Tìm trong lý do, ghi chú, mô tả..."
                            class="w-full"
                        />
                    </div>

                    <!-- User Filter -->
                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">
                            Người thực hiện
                        </label>
                        <Select
                            v-model="form.user_id"
                            :options="users"
                            optionLabel="name"
                            optionValue="id"
                            placeholder="Chọn người dùng"
                            class="w-full"
                            showClear
                        />
                    </div>

                    <!-- Action Filter -->
                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">
                            Hành động
                        </label>
                        <Select
                            v-model="form.action"
                            :options="actionOptions"
                            optionLabel="label"
                            optionValue="value"
                            placeholder="Chọn hành động"
                            class="w-full"
                            showClear
                        />
                    </div>

                    <!-- Date Range -->
                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">
                            Từ ngày
                        </label>
                        <DatePicker
                            v-model="form.start_date"
                            dateFormat="dd/mm/yy"
                            placeholder="dd/mm/yyyy"
                            class="w-full"
                            showIcon
                        />
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">
                            Đến ngày
                        </label>
                        <DatePicker
                            v-model="form.end_date"
                            dateFormat="dd/mm/yy"
                            placeholder="dd/mm/yyyy"
                            class="w-full"
                            showIcon
                        />
                    </div>

                    <!-- Search Button -->
                    <div class="lg:col-span-5 flex gap-2">
                        <Button
                            type="submit"
                            label="Tìm kiếm"
                            icon="pi pi-search"
                            :loading="searching"
                        />
                        <Button
                            label="Xóa bộ lọc"
                            severity="secondary"
                            @click="clearFilters"
                        />
                    </div>
                </form>
            </template>
        </Card>

        <!-- Results -->
        <Card class="bg-white dark:bg-slate-800">
            <template #title>
                <div class="flex justify-between items-center">
                    <span class="text-slate-900 dark:text-slate-100">Kết quả tìm kiếm ({{ transfers.total || 0 }} bản ghi)</span>
                    <div class="flex gap-2">
                        <Button
                            label="Export CSV"
                            icon="pi pi-file-excel"
                            size="small"
                            severity="success"
                            @click="exportResults('csv')"
                            :disabled="!transfers.data?.length"
                        />
                        <Button
                            label="Export JSON"
                            icon="pi pi-file"
                            size="small"
                            @click="exportResults('json')"
                            :disabled="!transfers.data?.length"
                        />
                    </div>
                </div>
            </template>

            <template #content>
                <DataTable
                    :value="transfers.data || []"
                    paginator
                    :rows="20"
                    :total-records="transfers.total"
                    lazy
                    @page="onPageChange"
                    class="p-datatable-sm"
                    responsive-layout="scroll"
                >
                    <Column field="id" header="ID" sortable>
                        <template #body="{ data }">
                            <router-link
                                :to="`/manager/transfers/${data.id}/audit`"
                                class="text-blue-600 hover:text-blue-800 font-medium"
                            >
                                #{{ data.id }}
                            </router-link>
                        </template>
                    </Column>

                    <Column field="student" header="Học viên">
                        <template #body="{ data }">
                            <div>
                                <div class="font-medium text-slate-900 dark:text-slate-100">{{ data.student?.name }}</div>
                                <div class="text-sm text-slate-500 dark:text-slate-400">{{ data.student?.code }}</div>
                            </div>
                        </template>
                    </Column>

                    <Column field="classes" header="Lớp học">
                        <template #body="{ data }">
                            <div class="space-y-1">
                                <div class="flex items-center text-sm">
                                    <span class="text-slate-500 dark:text-slate-400 mr-2">Từ:</span>
                                    <span class="font-medium text-slate-900 dark:text-slate-100">{{ data.from_class?.code }}</span>
                                </div>
                                <div class="flex items-center text-sm">
                                    <span class="text-slate-500 dark:text-slate-400 mr-2">Đến:</span>
                                    <span class="font-medium text-green-600 dark:text-green-400">{{ data.to_class?.code }}</span>
                                </div>
                            </div>
                        </template>
                    </Column>

                    <Column field="status" header="Trạng thái">
                        <template #body="{ data }">
                            <Tag
                                :value="getStatusLabel(data.status)"
                                :severity="getStatusSeverity(data.status)"
                            />
                        </template>
                    </Column>

                    <Column field="created_by" header="Người tạo">
                        <template #body="{ data }">
                            <div>
                                <div class="font-medium text-slate-900 dark:text-slate-100">{{ data.created_by?.name }}</div>
                                <div class="text-sm text-slate-500 dark:text-slate-400">
                                    {{ formatDateTime(data.created_at) }}
                                </div>
                            </div>
                        </template>
                    </Column>

                    <Column field="last_action" header="Hành động cuối">
                        <template #body="{ data }">
                            <div class="space-y-1">
                                <div v-if="data.reverted_at" class="text-sm text-red-600 dark:text-red-400">
                                    <i class="pi pi-undo mr-1"></i>
                                    Đã hoàn tác
                                </div>
                                <div v-if="data.retargeted_at" class="text-sm text-blue-600 dark:text-blue-400">
                                    <i class="pi pi-arrow-right mr-1"></i>
                                    Đã đổi hướng
                                </div>
                                <div v-if="!data.reverted_at && !data.retargeted_at" class="text-sm text-slate-500 dark:text-slate-400">
                                    Không có
                                </div>
                            </div>
                        </template>
                    </Column>

                    <Column header="Thao tác" style="width: 120px">
                        <template #body="{ data }">
                            <div class="flex gap-1">
                                <Button
                                    icon="pi pi-eye"
                                    size="small"
                                    severity="info"
                                    @click="viewAudit(data.id)"
                                    v-tooltip="'Xem audit log'"
                                />
                                <Button
                                    icon="pi pi-download"
                                    size="small"
                                    severity="success"
                                    @click="exportSingle(data.id)"
                                    v-tooltip="'Export audit log'"
                                />
                            </div>
                        </template>
                    </Column>

                    <template #empty>
                        <div class="text-center py-8">
                            <i class="pi pi-search text-4xl text-slate-300 dark:text-slate-600 mb-4"></i>
                            <p class="text-slate-500 dark:text-slate-400">
                                {{ hasSearched ? 'Không tìm thấy kết quả nào' : 'Nhập điều kiện tìm kiếm và nhấn "Tìm kiếm"' }}
                            </p>
                        </div>
                    </template>
                </DataTable>
            </template>
        </Card>
    </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import Card from 'primevue/card'
import Button from 'primevue/button'
import InputText from 'primevue/inputtext'
import Select from 'primevue/select'
import DatePicker from 'primevue/datepicker'
import Tag from 'primevue/tag'

defineOptions({ layout: AppLayout })

const props = defineProps({
    transfers: Object,
    users: Array,
    filters: Object
})

// Form state
const form = reactive({
    q: props.filters?.q || '',
    user_id: props.filters?.user_id || null,
    action: props.filters?.action || null,
    start_date: props.filters?.start_date ? new Date(props.filters.start_date) : null,
    end_date: props.filters?.end_date ? new Date(props.filters.end_date) : null,
})

const searching = ref(false)
const hasSearched = ref(Object.keys(props.filters || {}).length > 0)

// Options
const actionOptions = [
    { label: 'Tạo chuyển lớp', value: 'created' },
    { label: 'Hoàn tác', value: 'reverted' },
    { label: 'Đổi hướng', value: 'retargeted' }
]

// Methods
const search = () => {
    searching.value = true
    hasSearched.value = true

    const params = {}
    if (form.q) params.q = form.q
    if (form.user_id) params.user_id = form.user_id
    if (form.action) params.action = form.action
    if (form.start_date) params.start_date = formatDate(form.start_date)
    if (form.end_date) params.end_date = formatDate(form.end_date)

    router.get(route('manager.transfers.audit.search'), params, {
        preserveState: true,
        onFinish: () => {
            searching.value = false
        }
    })
}

const clearFilters = () => {
    Object.keys(form).forEach(key => {
        form[key] = null
    })
    form.q = ''
    hasSearched.value = false

    router.get(route('manager.transfers.audit.search'), {}, {
        preserveState: true
    })
}

const onPageChange = (event) => {
    const params = { ...props.filters, page: event.page + 1 }
    router.get(route('manager.transfers.audit.search'), params, {
        preserveState: true
    })
}

const viewAudit = (transferId) => {
    router.visit(`/manager/transfers/${transferId}/audit`)
}

const exportSingle = (transferId) => {
    window.open(`/manager/transfers/${transferId}/audit/export?format=csv`, '_blank')
}

const exportResults = (format) => {
    const params = new URLSearchParams(props.filters || {})
    params.append('format', format)
    window.open(`/manager/transfers/audit/export-search?${params.toString()}`, '_blank')
}

// Utility functions
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

const formatDateTime = (dateString) => {
    if (!dateString) return ''
    return new Date(dateString).toLocaleString('vi-VN')
}

const formatDate = (date) => {
    if (!date) return ''
    return date.toISOString().split('T')[0]
}
</script>
