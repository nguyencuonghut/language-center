<template>
    <Dialog
        :visible="visible"
        @update:visible="emit('update:visible', $event)"
        modal
        header="Hoàn tác chuyển lớp - Kiểm tra an toàn"
        :style="{width: '700px'}"
        class="safety-revert-dialog"
        :closable="!isProcessing"
    >
        <!-- Transfer Info -->
        <div v-if="transfer" class="bg-blue-50 dark:bg-blue-900/20 p-4 rounded-lg mb-4">
            <h4 class="font-medium mb-2 flex items-center gap-2">
                <i class="pi pi-info-circle text-blue-500"></i>
                Thông tin chuyển lớp:
            </h4>
            <div class="text-sm space-y-1">
                <div><strong>ID:</strong> #{{ transfer.id }}</div>
                <div><strong>Học viên:</strong> {{ transfer.student?.code }} - {{ transfer.student?.name }}</div>
                <div><strong>Từ lớp:</strong> {{ transfer.from_class?.code }} - {{ transfer.from_class?.name }}</div>
                <div><strong>Đến lớp:</strong> {{ transfer.to_class?.code }} - {{ transfer.to_class?.name }}</div>
                <div><strong>Phí chuyển:</strong> {{ formatCurrency(transfer.transfer_fee || 0) }}</div>
            </div>
        </div>

        <!-- Safety Check Status -->
        <div class="mb-6">
            <div class="flex items-center gap-2 mb-3">
                <Button
                    label="Kiểm tra an toàn"
                    icon="pi pi-shield"
                    @click="checkSafety"
                    :loading="checkingSafety"
                    size="small"
                    :disabled="!transfer"
                />
                <div v-if="safetyChecked" class="flex items-center gap-2">
                    <i :class="[
                        'pi',
                        validation?.can_revert ? 'pi-check-circle text-green-500' : 'pi-times-circle text-red-500'
                    ]"></i>
                    <span :class="validation?.can_revert ? 'text-green-600' : 'text-red-600'" class="text-sm font-medium">
                        {{ validation?.can_revert ? 'An toàn để hoàn tác' : 'Có rủi ro khi hoàn tác' }}
                    </span>
                </div>
            </div>

            <!-- Risk Level Indicator -->
            <div v-if="validation?.risk_level" class="mb-3">
                <div class="flex items-center gap-2">
                    <span class="text-sm font-medium">Mức độ rủi ro:</span>
                    <Tag
                        :value="getRiskLevelLabel(validation.risk_level)"
                        :severity="getRiskLevelSeverity(validation.risk_level)"
                        class="text-xs"
                    />
                </div>
            </div>
        </div>

        <!-- Safety Issues -->
        <div v-if="validation?.issues?.length" class="mb-6">
            <h4 class="font-medium mb-3 flex items-center gap-2">
                <i class="pi pi-exclamation-triangle text-orange-500"></i>
                Các vấn đề cần lưu ý ({{ validation.issues.length }} vấn đề):
            </h4>

            <div class="space-y-3 max-h-60 overflow-y-auto">
                <div
                    v-for="(issue, index) in validation.issues"
                    :key="index"
                    class="border rounded-lg p-3"
                    :class="getIssueCardClass(issue.type)"
                >
                    <div class="flex items-start gap-3">
                        <i :class="getIssueIcon(issue.type)" class="text-lg mt-0.5"></i>
                        <div class="flex-1">
                            <div class="font-medium text-sm">{{ issue.message }}</div>
                            <div v-if="issue.action_required" class="text-xs text-gray-600 mt-1">
                                <strong>Hành động cần thiết:</strong> {{ issue.action_required }}
                            </div>

                            <!-- Issue Details -->
                            <div v-if="issue.details && Object.keys(issue.details).length" class="mt-2">
                                <details class="text-xs">
                                    <summary class="cursor-pointer font-medium text-gray-700">Chi tiết</summary>
                                    <div class="mt-2 bg-gray-50 p-2 rounded text-xs">
                                        <pre class="whitespace-pre-wrap">{{ JSON.stringify(issue.details, null, 2) }}</pre>
                                    </div>
                                </details>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Revert Plan -->
        <div v-if="revertPlan?.success" class="mb-6">
            <h4 class="font-medium mb-3 flex items-center gap-2">
                <i class="pi pi-list-check text-green-500"></i>
                Kế hoạch hoàn tác an toàn:
            </h4>

            <div class="bg-green-50 p-4 rounded-lg">
                <div class="grid grid-cols-2 gap-4 mb-3 text-sm">
                    <div><strong>Tổng bước:</strong> {{ revertPlan.plan.steps.length }}</div>
                    <div><strong>Thời gian ước tính:</strong> {{ revertPlan.plan.estimated_time }} phút</div>
                </div>

                <div class="space-y-2">
                    <div
                        v-for="step in revertPlan.plan.steps"
                        :key="step.order"
                        class="flex items-center gap-2 text-sm"
                    >
                        <span class="bg-blue-500 text-white rounded-full w-5 h-5 flex items-center justify-center text-xs">
                            {{ step.order }}
                        </span>
                        <span>{{ step.description }}</span>
                        <span class="text-gray-500">({{ step.estimated_minutes }}p)</span>
                    </div>
                </div>

                <div class="mt-3">
                    <label class="flex items-center gap-2">
                        <input
                            type="checkbox"
                            v-model="useSafePlan"
                            class="rounded"
                        />
                        <span class="text-sm">Sử dụng kế hoạch hoàn tác an toàn</span>
                    </label>
                </div>
            </div>
        </div>

        <!-- Revert Form -->
        <div class="space-y-4">
            <div>
                <label class="block text-sm font-medium mb-2">
                    Lý do hoàn tác <span class="text-red-500">*</span>
                </label>
                <Textarea
                    v-model="form.reason"
                    placeholder="Nhập lý do hoàn tác..."
                    rows="3"
                    class="w-full"
                    :class="{'border-red-500': !form.reason.trim()}"
                />
            </div>

            <div>
                <label class="block text-sm font-medium mb-2">
                    Ghi chú thêm
                </label>
                <Textarea
                    v-model="form.notes"
                    placeholder="Ghi chú bổ sung (tùy chọn)..."
                    rows="2"
                    class="w-full"
                />
            </div>

            <!-- Force Revert Option -->
            <div v-if="validation && !validation.can_revert" class="bg-yellow-50 p-3 rounded-lg">
                <label class="flex items-center gap-2">
                    <input
                        type="checkbox"
                        v-model="forceRevert"
                        class="rounded"
                    />
                    <span class="text-sm font-medium text-yellow-800">
                        Bắt buộc hoàn tác (bỏ qua cảnh báo an toàn)
                    </span>
                </label>
                <p class="text-xs text-yellow-700 mt-1">
                    ⚠️ Tùy chọn này có thể gây ra vấn đề với hóa đơn và thanh toán
                </p>
            </div>
        </div>

        <template #footer>
            <div class="flex justify-between items-center">
                <div class="text-xs text-gray-500">
                    <span v-if="validation?.can_revert" class="text-green-600">✓ An toàn để thực hiện</span>
                    <span v-else-if="validation" class="text-red-600">⚠️ Có rủi ro - cần xem xét</span>
                </div>

                <div class="flex gap-2">
                    <Button
                        label="Hủy"
                        severity="secondary"
                        @click="handleCancel"
                        :disabled="isProcessing"
                    />
                    <Button
                        label="Xác nhận hoàn tác"
                        severity="danger"
                        @click="confirmRevert"
                        :disabled="!canRevert || isProcessing"
                        :loading="isProcessing"
                    />
                </div>
            </div>
        </template>
    </Dialog>
</template>

<script setup>
import { ref, computed, watch } from 'vue'
import { router } from '@inertiajs/vue3'
import Dialog from 'primevue/dialog'
import Button from 'primevue/button'
import Textarea from 'primevue/textarea'
import Tag from 'primevue/tag'

const props = defineProps({
    visible: Boolean,
    transfer: Object
})

const emit = defineEmits(['cancel', 'success', 'update:visible'])

// Form state
const form = ref({
    reason: '',
    notes: ''
})

// Safety check state
const checkingSafety = ref(false)
const safetyChecked = ref(false)
const validation = ref(null)
const revertPlan = ref(null)
const useSafePlan = ref(false)
const forceRevert = ref(false)
const isProcessing = ref(false)

// Reset form when dialog opens/closes
watch(() => props.visible, (newVal) => {
    if (newVal) {
        form.value = { reason: '', notes: '' }
        safetyChecked.value = false
        validation.value = null
        revertPlan.value = null
        useSafePlan.value = false
        forceRevert.value = false
    }
})

// Computed
const canRevert = computed(() => {
    return form.value.reason.trim() &&
           (validation.value?.can_revert || forceRevert.value)
})

// Methods
const checkSafety = async () => {
    if (!props.transfer?.id) return

    checkingSafety.value = true

    try {
        const response = await fetch(route('manager.transfers.check-revert-safety'), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            },
            body: JSON.stringify({
                transfer_id: props.transfer.id
            })
        })

        const data = await response.json()
        validation.value = data.validation
        revertPlan.value = data.revert_plan
        safetyChecked.value = true

        if (data.validation?.can_revert) {
            useSafePlan.value = true
        }

    } catch (error) {
        console.error('Safety check failed:', error)
    } finally {
        checkingSafety.value = false
    }
}

const confirmRevert = () => {
    if (!canRevert.value) return

    isProcessing.value = true

    const formData = new FormData()
    formData.append('student_id', props.transfer.student_id)
    formData.append('to_class_id', props.transfer.to_class_id)
    formData.append('reason', form.value.reason)
    formData.append('notes', form.value.notes)

    if (useSafePlan.value) {
        formData.append('use_safe_plan', '1')
    }

    if (forceRevert.value) {
        formData.append('force_revert', '1')
    }

    router.post(route('manager.transfers.revert'), formData, {
        onSuccess: () => {
            emit('success')
            emit('update:visible', false)
            isProcessing.value = false
        },
        onError: () => {
            isProcessing.value = false
        }
    })
}

// Utility functions
const formatCurrency = (amount) => {
    if (!amount) return '0 VND'
    return new Intl.NumberFormat('vi-VN', {
        style: 'currency',
        currency: 'VND'
    }).format(amount)
}

const handleCancel = () => {
    emit('cancel')
    emit('update:visible', false)
}

const getRiskLevelLabel = (level) => {
    const labels = {
        minimal: 'Tối thiểu',
        low: 'Thấp',
        medium: 'Trung bình',
        high: 'Cao'
    }
    return labels[level] || level
}

const getRiskLevelSeverity = (level) => {
    const severities = {
        minimal: 'success',
        low: 'info',
        medium: 'warning',
        high: 'danger'
    }
    return severities[level] || 'secondary'
}

const getIssueCardClass = (type) => {
    const classes = {
        error: 'border-red-200 bg-red-50',
        warning: 'border-yellow-200 bg-yellow-50',
        info: 'border-blue-200 bg-blue-50'
    }
    return classes[type] || 'border-gray-200 bg-gray-50'
}

const getIssueIcon = (type) => {
    const icons = {
        error: 'pi pi-times-circle text-red-500',
        warning: 'pi pi-exclamation-triangle text-yellow-500',
        info: 'pi pi-info-circle text-blue-500'
    }
    return icons[type] || 'pi pi-info text-gray-500'
}
</script>

<style scoped>
.safety-revert-dialog {
    font-family: 'Inter', sans-serif;
}

.safety-revert-dialog :deep(.p-dialog-content) {
    padding: 1.5rem;
}

.safety-revert-dialog :deep(.p-dialog-footer) {
    padding: 1rem 1.5rem;
    border-top: 1px solid #e5e7eb;
}

details summary::-webkit-details-marker {
    display: none;
}

details summary::before {
    content: "▶";
    margin-right: 0.5rem;
    transition: transform 0.2s;
}

details[open] summary::before {
    transform: rotate(90deg);
}
</style>
