<script setup>
import { computed } from 'vue'
import { Head, Link } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import { createTransferService } from '@/service/TransferService.js'

// PrimeVue
import Card from 'primevue/card'
import Button from 'primevue/button'
import Tag from 'primevue/tag'
import Divider from 'primevue/divider'

defineOptions({ layout: AppLayout })

const props = defineProps({
  transfer: Object,
})

// Initialize TransferService (no toast injection - handled by AppLayout)
const transferService = createTransferService()

// Use utility functions from service
const { getStatusSeverity, getStatusLabel, getEffectiveTargetClass, formatDate, formatDateTime } = transferService.utils

// Computed
const statusSeverity = computed(() => getStatusSeverity(props.transfer.status))
const statusLabel = computed(() => getStatusLabel(props.transfer.status))
const effectiveTargetClass = computed(() => getEffectiveTargetClass(props.transfer))

// Methods using TransferService
function handleRevert() {
  transferService.revert({
    student_id: props.transfer.student_id,
    from_class_id: props.transfer.from_class_id,
    to_class_id: props.transfer.to_class_id,
  })
}

function handleRetarget() {
  transferService.retarget(props.transfer.id)
}

function handlePrint() {
  window.print()
}

function formatCurrency(amount) {
  return transferService.utils.formatCurrency(amount)
}
</script>

<template>
  <Head :title="`Transfer #${transfer.id}`" />

  <div class="max-w-4xl mx-auto space-y-6 transfer-content">
    <!-- Page Header -->
    <div class="flex items-center justify-between page-header">
      <div>
        <h1 class="text-2xl font-bold text-slate-900 dark:text-slate-100 transfer-title">
          Transfer #{{ transfer.id }}
        </h1>
        <p class="text-slate-600 dark:text-slate-400">Chi tiết phiếu chuyển lớp</p>
      </div>

      <div class="flex items-center gap-2 print-hide">
        <Tag
          :value="statusLabel"
          :severity="statusSeverity"
          class="text-sm"
        />

        <Button
          v-if="transfer.status === 'active'"
          label="Hoàn tác"
          severity="warn"
          icon="pi pi-undo"
          @click="handleRevert"
        />

        <Button
          v-if="transfer.status === 'active'"
          label="Đổi hướng"
          severity="info"
          icon="pi pi-refresh"
          @click="handleRetarget"
          class="ml-2"
        />

        <Button
          label="In phiếu"
          severity="secondary"
          icon="pi pi-print"
          @click="handlePrint"
          class="ml-2"
        />
      </div>
    </div>

    <!-- Transfer Info -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
      <!-- Student & Classes Info -->
      <Card class="bg-white dark:bg-slate-800 lg:col-span-2">
        <template #title>
          <div class="flex items-center gap-2">
            <i class="pi pi-user text-blue-600"></i>
            Thông tin chuyển lớp
          </div>
        </template>
        <template #content>
          <div class="space-y-4">
            <!-- Student -->
            <div>
              <label class="block text-sm font-medium text-slate-600 dark:text-slate-400 mb-1">
                Học viên
              </label>
              <div class="text-lg font-semibold">
                <Link
                  :href="route('manager.students.show', transfer.student.id)"
                  class="text-blue-600 hover:text-blue-800"
                >
                  {{ transfer.student.code }} - {{ transfer.student.name }}
                </Link>
              </div>
              <div v-if="transfer.student.email" class="text-sm text-slate-500">
                {{ transfer.student.email }}
              </div>
            </div>

            <Divider />

            <!-- From Class -->
            <div>
              <label class="block text-sm font-medium text-slate-600 dark:text-slate-400 mb-1">
                Từ lớp
              </label>
              <div class="font-medium">{{ transfer.from_class.code }}</div>
              <div class="text-sm text-slate-500">{{ transfer.from_class.name }}</div>
            </div>

            <!-- To Class -->
            <div>
              <label class="block text-sm font-medium text-slate-600 dark:text-slate-400 mb-1">
                Đến lớp
              </label>
              <div class="font-medium">{{ effectiveTargetClass.code }}</div>
              <div class="text-sm text-slate-500">{{ effectiveTargetClass.name }}</div>

              <!-- Show original target if retargeted -->
              <div v-if="transfer.status === 'retargeted'" class="mt-2 p-2 bg-orange-50 dark:bg-orange-900/20 rounded">
                <div class="text-xs text-orange-600 dark:text-orange-400 font-medium">Lớp đích ban đầu:</div>
                <div class="text-sm">{{ transfer.to_class.code }} - {{ transfer.to_class.name }}</div>
              </div>
            </div>

            <!-- Details -->
            <Divider />

            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-slate-600 dark:text-slate-400 mb-1">
                  Ngày hiệu lực
                </label>
                <div>{{ formatDate(transfer.effective_date) }}</div>
              </div>

              <div>
                <label class="block text-sm font-medium text-slate-600 dark:text-slate-400 mb-1">
                  Buổi bắt đầu
                </label>
                <div>Buổi {{ transfer.start_session_no }}</div>
              </div>
            </div>

            <!-- Transfer Fee -->
            <div v-if="transfer.transfer_fee > 0">
              <label class="block text-sm font-medium text-slate-600 dark:text-slate-400 mb-1">
                Phí chuyển lớp
              </label>
              <div class="text-lg font-semibold text-green-600">
                {{ formatCurrency(transfer.transfer_fee) }}
              </div>
            </div>
          </div>
        </template>
      </Card>

      <!-- Quick Stats -->
      <Card class="bg-white dark:bg-slate-800">
        <template #title>
          <div class="flex items-center gap-2">
            <i class="pi pi-chart-bar text-indigo-600"></i>
            Thống kê nhanh
          </div>
        </template>
        <template #content>
          <div class="space-y-4">
            <div class="text-center p-3 bg-blue-50 dark:bg-blue-900/20 rounded">
              <div class="text-2xl font-bold text-blue-600">{{ transfer.id }}</div>
              <div class="text-xs text-blue-500">Transfer ID</div>
            </div>

            <div class="text-center p-3 bg-green-50 dark:bg-green-900/20 rounded">
              <div class="text-lg font-bold text-green-600">
                {{ Math.abs(new Date(transfer.effective_date) - new Date()) < 86400000 ? 'Hôm nay' : formatDate(transfer.effective_date) }}
              </div>
              <div class="text-xs text-green-500">Ngày hiệu lực</div>
            </div>

            <div class="text-center p-3 bg-purple-50 dark:bg-purple-900/20 rounded">
              <div class="text-lg font-bold text-purple-600">
                {{ Math.ceil((new Date() - new Date(transfer.created_at)) / (1000 * 60 * 60 * 24)) }}
              </div>
              <div class="text-xs text-purple-500">Ngày đã tạo</div>
            </div>
          </div>
        </template>
      </Card>
    </div>

    <!-- Audit Trail -->
    <div class="grid grid-cols-1 lg:grid-cols-1 gap-6">
      <Card class="bg-white dark:bg-slate-800">
        <template #title>
          <div class="flex items-center gap-2">
            <i class="pi pi-history text-purple-600"></i>
            Lịch sử thao tác
          </div>
        </template>
        <template #content>
          <div class="space-y-4">
            <!-- Created -->
            <div class="flex items-start gap-3">
              <div class="w-2 h-2 bg-green-500 rounded-full mt-2"></div>
              <div class="flex-1">
                <div class="font-medium">Tạo chuyển lớp</div>
                <div class="text-sm text-slate-500">
                  {{ transfer.created_by?.name }} • {{ formatDateTime(transfer.created_at) }}
                </div>
              </div>
            </div>

            <!-- Processed -->
            <div v-if="transfer.processed_at" class="flex items-start gap-3">
              <div class="w-2 h-2 bg-blue-500 rounded-full mt-2"></div>
              <div class="flex-1">
                <div class="font-medium">Xử lý chuyển lớp</div>
                <div class="text-sm text-slate-500">
                  {{ formatDateTime(transfer.processed_at) }}
                </div>
              </div>
            </div>

            <!-- Retargeted -->
            <div v-if="transfer.retargeted_at" class="flex items-start gap-3">
              <div class="w-2 h-2 bg-purple-500 rounded-full mt-2"></div>
              <div class="flex-1">
                <div class="font-medium">Đổi hướng chuyển lớp</div>
                <div class="text-sm text-slate-500">
                  {{ transfer.retargeted_by?.name }} • {{ formatDateTime(transfer.retargeted_at) }}
                </div>
              </div>
            </div>

            <!-- Reverted -->
            <div v-if="transfer.reverted_at" class="flex items-start gap-3">
              <div class="w-2 h-2 bg-orange-500 rounded-full mt-2"></div>
              <div class="flex-1">
                <div class="font-medium">Hoàn tác chuyển lớp</div>
                <div class="text-sm text-slate-500">
                  {{ transfer.reverted_by?.name }} • {{ formatDateTime(transfer.reverted_at) }}
                </div>
              </div>
            </div>
          </div>
        </template>
      </Card>
    </div>

    <!-- Reason & Notes -->
    <div v-if="transfer.reason || transfer.notes" class="grid grid-cols-1 lg:grid-cols-2 gap-6">
      <!-- Reason -->
      <Card v-if="transfer.reason" class="bg-white dark:bg-slate-800">
        <template #title>Lý do chuyển lớp</template>
        <template #content>
          <div class="text-slate-700 dark:text-slate-300 whitespace-pre-wrap">
            {{ transfer.reason }}
          </div>
        </template>
      </Card>

      <!-- Notes -->
      <Card v-if="transfer.notes" class="bg-white dark:bg-slate-800">
        <template #title>Ghi chú</template>
        <template #content>
          <div class="text-slate-700 dark:text-slate-300 whitespace-pre-wrap">
            {{ transfer.notes }}
          </div>
        </template>
      </Card>
    </div>

    <!-- Invoice Info -->
    <Card v-if="transfer.invoice" class="bg-white dark:bg-slate-800">
      <template #title>
        <div class="flex items-center gap-2">
          <i class="pi pi-wallet text-green-600"></i>
          Thông tin hóa đơn
        </div>
      </template>
      <template #content>
        <div class="flex items-center justify-between">
          <div>
            <div class="font-medium">{{ transfer.invoice.code }}</div>
            <div class="text-sm text-slate-500">
              Tổng tiền: {{ formatCurrency(transfer.invoice.total) }}
            </div>
          </div>
          <div class="flex items-center gap-2">
            <Tag
              :value="transfer.invoice.status"
              :severity="transfer.invoice.status === 'paid' ? 'success' : 'warn'"
            />
            <Link
              :href="route('manager.invoices.show', transfer.invoice.id)"
              class="text-blue-600 hover:text-blue-800"
            >
              <i class="pi pi-external-link"></i>
            </Link>
          </div>
        </div>
      </template>
    </Card>

    <!-- Back Button -->
    <div class="flex justify-start print-hide">
      <Link
        :href="route('manager.transfers.index')"
        class="inline-flex items-center px-4 py-2 bg-slate-600 text-white rounded-lg hover:bg-slate-700 transition-colors back-button"
      >
        <i class="pi pi-arrow-left mr-2"></i>
        Quay lại danh sách
      </Link>
    </div>
  </div>
</template>

<style>
@media print {
  /* Ẩn layout components */
  .app-layout-header,
  .app-layout-sidebar,
  .app-layout-nav,
  nav,
  header,
  aside,
  .header,
  .sidebar,
  .navigation,
  .navbar,
  .print-hide,
  .back-button {
    display: none !important;
  }

  /* Ẩn page header buttons */
  .page-header .print-hide {
    display: none !important;
  }

  /* Reset page margins - tối ưu cho A4 */
  @page {
    margin: 0.25in !important;
    size: A4 !important;
  }

  html, body {
    margin: 0 !important;
    padding: 0 !important;
    font-size: 11px !important;
    line-height: 1.3 !important;
    -webkit-print-color-adjust: exact !important;
    print-color-adjust: exact !important;
  }

  /* Content styling - giữ nguyên layout */
  .transfer-content {
    margin: 0 !important;
    padding: 0 !important;
    max-width: 100% !important;
    font-size: 11px !important;
  }

  /* Title styling - compact nhưng đẹp */
  .transfer-title {
    font-size: 18px !important;
    font-weight: bold !important;
    text-align: center !important;
    margin-bottom: 12px !important;
    color: #1e293b !important;
  }

  /* GIỮ NGUYÊN GRID LAYOUT */
  .grid {
    display: grid !important;
  }

  .grid-cols-1 {
    grid-template-columns: 1fr !important;
  }

  .lg\\:grid-cols-3 {
    grid-template-columns: 2fr 1fr !important;
  }

  .lg\\:col-span-2 {
    grid-column: span 1 !important;
  }

  .gap-6 {
    gap: 8px !important;
  }

  /* Card styling - GIỮ NGUYÊN MÀU VÀ STYLING */
  .p-card {
    border: 1px solid #e2e8f0 !important;
    border-radius: 6px !important;
    margin-bottom: 8px !important;
    page-break-inside: avoid !important;
    background: #ffffff !important;
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05) !important;
    padding: 6px !important;
  }

  .p-card-title {
    font-size: 13px !important;
    font-weight: 600 !important;
    color: #374151 !important;
    border-bottom: 1px solid #e5e7eb !important;
    padding-bottom: 4px !important;
    margin-bottom: 6px !important;
  }

  .p-card-content {
    font-size: 10px !important;
    line-height: 1.3 !important;
  }

  /* GIỮ NGUYÊN MÀU SẮC */
  .text-slate-900 {
    color: #0f172a !important;
  }

  .text-slate-600 {
    color: #475569 !important;
  }

  .text-slate-500 {
    color: #475569 !important;
  }

  /* Cải thiện màu chữ cho timeline và thông tin chi tiết */
  .space-y-4 .text-sm.text-slate-500 {
    color: #374151 !important;
  }

  .text-slate-400 {
    color: #94a3b8 !important;
  }

  /* Màu chữ rõ ràng hơn cho nội dung chính */
  .text-slate-700 {
    color: #334155 !important;
  }

  .dark\\:text-slate-300 {
    color: #334155 !important;
  }

  .text-blue-600 {
    color: #2563eb !important;
  }

  .text-green-600 {
    color: #16a34a !important;
  }

  .text-purple-600 {
    color: #9333ea !important;
  }

  .text-indigo-600 {
    color: #4f46e5 !important;
  }

  .text-orange-600 {
    color: #ea580c !important;
  }

  /* Background colors */
  .bg-slate-50 {
    background: #f8fafc !important;
  }

  .bg-blue-50 {
    background: #eff6ff !important;
  }

  .bg-green-50 {
    background: #f0fdf4 !important;
  }

  .bg-purple-50 {
    background: #faf5ff !important;
  }

  .bg-orange-50 {
    background: #fff7ed !important;
  }

  /* Dark mode backgrounds for print */
  .dark\\:bg-slate-800 {
    background: #f8fafc !important;
    border: 1px solid #e2e8f0 !important;
  }

  .dark\\:bg-slate-700 {
    background: #f1f5f9 !important;
    border: 1px solid #e2e8f0 !important;
  }

  /* Links với màu */
  a {
    color: #2563eb !important;
    text-decoration: underline !important;
  }

  a:hover {
    color: #1d4ed8 !important;
  }

  /* Tags với màu */
  .p-tag {
    font-size: 9px !important;
    padding: 2px 6px !important;
    border-radius: 3px !important;
    font-weight: 500 !important;
  }

  .p-tag.p-tag-success {
    background: #dcfce7 !important;
    color: #166534 !important;
    border: 1px solid #bbf7d0 !important;
  }

  .p-tag.p-tag-warning {
    background: #fef3c7 !important;
    color: #92400e !important;
    border: 1px solid #fde68a !important;
  }

  .p-tag.p-tag-danger {
    background: #fee2e2 !important;
    color: #991b1b !important;
    border: 1px solid #fecaca !important;
  }

  .p-tag.p-tag-info {
    background: #dbeafe !important;
    color: #1e40af !important;
    border: 1px solid #bfdbfe !important;
  }

  /* Icons với màu */
  .pi {
    color: inherit !important;
  }

  .text-blue-600 .pi {
    color: #2563eb !important;
  }

  .text-indigo-600 .pi {
    color: #4f46e5 !important;
  }

  .text-purple-600 .pi {
    color: #9333ea !important;
  }

  .text-green-600 .pi {
    color: #16a34a !important;
  }

  /* Spacing tối ưu */
  .space-y-6 > * + * {
    margin-top: 8px !important;
  }

  .space-y-4 > * + * {
    margin-top: 6px !important;
  }

  /* Compact padding */
  .p-3 {
    padding: 4px !important;
  }

  .p-2 {
    padding: 3px !important;
  }

  /* Text sizes tối ưu */
  .text-2xl {
    font-size: 16px !important;
  }

  .text-lg {
    font-size: 13px !important;
  }

  .text-sm {
    font-size: 9px !important;
  }

  .text-xs {
    font-size: 8px !important;
  }

  /* Margins tối ưu */
  .mb-2 {
    margin-bottom: 4px !important;
  }

  .mb-1 {
    margin-bottom: 2px !important;
  }

  .mt-2 {
    margin-top: 4px !important;
  }

  /* Timeline colors */
  .w-2.h-2.rounded-full {
    width: 4px !important;
    height: 4px !important;
  }

  .bg-green-500 {
    background: #22c55e !important;
  }

  .bg-blue-500 {
    background: #3b82f6 !important;
  }

  .bg-purple-500 {
    background: #a855f7 !important;
  }

  .bg-orange-500 {
    background: #f97316 !important;
  }

  /* Divider styling */
  .p-divider {
    margin: 4px 0 !important;
    border-color: #e5e7eb !important;
  }

  /* Ensure print colors */
  * {
    -webkit-print-color-adjust: exact !important;
    print-color-adjust: exact !important;
  }
}
</style>
