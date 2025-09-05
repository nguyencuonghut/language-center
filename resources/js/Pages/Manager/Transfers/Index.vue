<script setup>
import { reactive, computed, ref, watch } from 'vue'
import { Head, Link } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import { createTransferService } from '@/service/TransferService.js'

// PrimeVue
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import Button from 'primevue/button'
import Tag from 'primevue/tag'
import Select from 'primevue/select'
import InputText from 'primevue/inputtext'
import DatePicker from 'primevue/datepicker'
import Card from 'primevue/card'
import Knob from 'primevue/knob'
import Dialog from 'primevue/dialog'
import Textarea from 'primevue/textarea'

defineOptions({ layout: AppLayout })

const props = defineProps({
  transfers: Object,
  stats: Object,
  filters: Object
})

// Initialize TransferService (no toast injection - handled by AppLayout)
const transferService = createTransferService()

// Local state
const filters = reactive({
  q: props.filters?.q ?? '',
  status: props.filters?.status ?? '',
  from_date: props.filters?.from_date ?? '',
  to_date: props.filters?.to_date ?? '',
})

// Revert dialog state
const showRevertDialog = ref(false)
const revertData = ref({
  reason: '',
  notes: '',
  transfer: null
})

// Methods
function search(page = 1) {
  const params = { ...filters }

  // Format date parameters if they exist
  if (params.from_date && typeof params.from_date === 'object') {
    // Convert to local date string without time zone conversion
    const year = params.from_date.getFullYear()
    const month = String(params.from_date.getMonth() + 1).padStart(2, '0')
    const day = String(params.from_date.getDate()).padStart(2, '0')
    params.from_date = `${year}-${month}-${day}`
  }
  if (params.to_date && typeof params.to_date === 'object') {
    // Convert to local date string without time zone conversion
    const year = params.to_date.getFullYear()
    const month = String(params.to_date.getMonth() + 1).padStart(2, '0')
    const day = String(params.to_date.getDate()).padStart(2, '0')
    params.to_date = `${year}-${month}-${day}`
  }

  // Remove empty parameters to avoid sending status='' to backend
  Object.keys(params).forEach(key => {
    if (params[key] === '' || params[key] === null || params[key] === undefined) {
      delete params[key]
    }
  })

  if (page > 1) {
    params.page = page
  }
  transferService.getList(params)
}

function onPage(event) {
  const page = event.page + 1 // PrimeVue uses 0-based indexing
  search(page)
}

function resetFilters() {
  Object.keys(filters).forEach(key => {
    filters[key] = ''
  })
  search()
}

function handleRevert(transfer) {
  // Show revert dialog with transfer data
  revertData.value = {
    reason: '',
    notes: '',
    transfer: transfer
  }
  showRevertDialog.value = true
}

// Confirm revert with reason and notes
function confirmRevert() {
  if (!revertData.value.reason.trim()) {
    return // Validation handled by dialog
  }

  if (!revertData.value.transfer) {
    return
  }

  transferService.revert({
    student_id: revertData.value.transfer.student_id,
    from_class_id: revertData.value.transfer.from_class_id,
    to_class_id: revertData.value.transfer.to_class_id,
    reason: revertData.value.reason,
    notes: revertData.value.notes,
  }, {
    onSuccess: () => {
      // Close dialog and reload data
      showRevertDialog.value = false
      revertData.value = { reason: '', notes: '', transfer: null }
      search() // Reload current page
    },
    onError: (errors) => {
      console.error('Revert transfer failed:', errors)
    }
  })
}

// Cancel revert dialog
function cancelRevert() {
  showRevertDialog.value = false
  revertData.value = { reason: '', notes: '', transfer: null }
}

// Use utility functions from service
const { statusOptions, getStatusSeverity, getStatusLabel, formatDate } = transferService.utils

// Stats computed
const successRate = computed(() => {
  return props.stats?.success_rate ?? 0
})
</script>

<template>
  <Head title="Quản lý chuyển lớp" />

  <div class="space-y-6">
    <!-- Page Header -->
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-bold text-slate-900 dark:text-slate-100">Quản lý chuyển lớp</h1>
        <p class="text-slate-600 dark:text-slate-400">Theo dõi và quản lý việc chuyển lớp của học viên</p>
      </div>
      <div class="flex items-center gap-2">
        <Link
          :href="route('manager.transfers.advanced.search')"
          class="inline-flex items-center px-3 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors text-sm"
        >
          <i class="pi pi-search mr-2"></i>
          Tìm kiếm nâng cao
        </Link>
        <Link
          :href="route('manager.transfers.advanced.history')"
          class="inline-flex items-center px-3 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition-colors text-sm"
        >
          <i class="pi pi-history mr-2"></i>
          Lịch sử
        </Link>
        <Link
          :href="route('manager.transfers.advanced.reports')"
          class="inline-flex items-center px-3 py-2 bg-orange-600 text-white rounded-lg hover:bg-orange-700 transition-colors text-sm"
        >
          <i class="pi pi-file-excel mr-2"></i>
          Báo cáo
        </Link>
        <Link
          :href="route('manager.transfers.analytics')"
          class="inline-flex items-center px-3 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors text-sm"
        >
          <i class="pi pi-chart-bar mr-2"></i>
          Phân tích
        </Link>
        <Link
          :href="route('manager.transfers.create')"
          class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors"
        >
          <i class="pi pi-plus mr-2"></i>
          Tạo chuyển lớp
        </Link>
      </div>
    </div>

    <!-- Stats Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4">
      <Card class="bg-white dark:bg-slate-800">
        <template #content>
          <div class="text-center">
            <div class="text-2xl font-bold text-blue-600">{{ stats?.total ?? 0 }}</div>
            <div class="text-sm text-slate-600 dark:text-slate-400">Tổng chuyển lớp</div>
          </div>
        </template>
      </Card>

      <Card class="bg-white dark:bg-slate-800">
        <template #content>
          <div class="text-center">
            <div class="text-2xl font-bold text-green-600">{{ stats?.active ?? 0 }}</div>
            <div class="text-sm text-slate-600 dark:text-slate-400">Đang hoạt động</div>
          </div>
        </template>
      </Card>

      <Card class="bg-white dark:bg-slate-800">
        <template #content>
          <div class="text-center">
            <div class="text-2xl font-bold text-orange-600">{{ stats?.reverted ?? 0 }}</div>
            <div class="text-sm text-slate-600 dark:text-slate-400">Đã hoàn tác</div>
          </div>
        </template>
      </Card>

      <Card class="bg-white dark:bg-slate-800">
        <template #content>
          <div class="text-center">
            <div class="text-2xl font-bold text-purple-600">{{ stats?.retargeted ?? 0 }}</div>
            <div class="text-sm text-slate-600 dark:text-slate-400">Đã đổi hướng</div>
          </div>
        </template>
      </Card>

      <Card class="bg-white dark:bg-slate-800">
        <template #content>
          <div class="text-center">
            <Knob
              v-model="successRate"
              :size="60"
              :thickness="8"
              :stroke-width="8"
              :show-value="true"
              :readonly="true"
              value-color="rgb(34, 197, 94)"
              range-color="rgb(226, 232, 240)"
            />
            <div class="text-sm text-slate-600 dark:text-slate-400 mt-1">Tỷ lệ thành công</div>
          </div>
        </template>
      </Card>
    </div>

    <!-- Filters -->
    <Card class="bg-white dark:bg-slate-800">
      <template #content>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4">
          <div>
            <label class="block text-sm font-medium mb-1">Tìm kiếm</label>
            <InputText
              v-model="filters.q"
              placeholder="Tên hoặc mã học viên..."
              class="w-full"
              @keyup.enter="search"
            />
          </div>

          <div>
            <label class="block text-sm font-medium mb-1">Trạng thái</label>
            <Select
              v-model="filters.status"
              :options="statusOptions"
              optionLabel="label"
              optionValue="value"
              placeholder="Chọn trạng thái"
              class="w-full"
              @change="search"
            />
          </div>

          <div>
            <label class="block text-sm font-medium mb-1">Từ ngày</label>
            <DatePicker
              v-model="filters.from_date"
              date-format="yy-mm-dd"
              placeholder="yyyy-mm-dd"
              class="w-full"
              show-clear
              @date-select="search"
              @clear="search"
            />
          </div>

          <div>
            <label class="block text-sm font-medium mb-1">Đến ngày</label>
            <DatePicker
              v-model="filters.to_date"
              date-format="yy-mm-dd"
              placeholder="yyyy-mm-dd"
              class="w-full"
              show-clear
              @date-select="search"
              @clear="search"
            />
          </div>

          <div class="flex items-end gap-2">
            <Button
              label="Tìm kiếm"
              icon="pi pi-search"
              @click="search"
              class="flex-1"
            />
            <Button
              label="Reset"
              severity="secondary"
              @click="resetFilters"
            />
          </div>
        </div>
      </template>
    </Card>

    <!-- Data Table -->
    <Card class="bg-white dark:bg-slate-800">
      <template #content>
        <DataTable
          :value="transfers.data"
          :paginator="true"
          :rows="transfers.per_page"
          :total-records="transfers.total"
          :first="(transfers.current_page - 1) * transfers.per_page"
          lazy
          data-key="id"
          size="small"
          class="p-datatable-sm"
          paginatorTemplate="RowsPerPageDropdown FirstPageLink PrevPageLink PageLinks NextPageLink LastPageLink CurrentPageReport"
          currentPageReportTemplate="Hiển thị {first} - {last} trên tổng số {totalRecords} bản ghi"
          :rowsPerPageOptions="[10, 15, 25]"
          @page="onPage"
        >
          <Column field="student.code" header="Mã HV" :sortable="true">
            <template #body="{ data }">
              <Link
                :href="route('manager.students.show', data.student_id)"
                class="text-blue-600 hover:text-blue-800 font-medium"
              >
                {{ data.student?.code }}
              </Link>
            </template>
          </Column>

          <Column field="student.name" header="Tên học viên" :sortable="true">
            <template #body="{ data }">
              {{ data.student?.name }}
            </template>
          </Column>

          <Column header="Chuyển từ">
            <template #body="{ data }">
              <div class="text-sm">
                <div class="font-medium">{{ data.from_class?.code }}</div>
                <div class="text-slate-500">{{ data.from_class?.name }}</div>
              </div>
            </template>
          </Column>

          <Column header="Chuyển đến">
            <template #body="{ data }">
              <div class="text-sm">
                <div class="font-medium">
                  {{ data.status === 'retargeted' ? data.retargeted_to_class?.code : data.to_class?.code }}
                </div>
                <div class="text-slate-500">
                  {{ data.status === 'retargeted' ? data.retargeted_to_class?.name : data.to_class?.name }}
                </div>
              </div>
            </template>
          </Column>

          <Column field="effective_date" header="Ngày hiệu lực" :sortable="true">
            <template #body="{ data }">
              {{ formatDate(data.effective_date) }}
            </template>
          </Column>

          <Column field="created_at" header="Ngày tạo" :sortable="true">
            <template #body="{ data }">
              {{ formatDate(data.created_at) }}
            </template>
          </Column>

          <Column field="status" header="Trạng thái" :sortable="true">
            <template #body="{ data }">
              <Tag
                :value="getStatusLabel(data.status)"
                :severity="getStatusSeverity(data.status)"
              />
            </template>
          </Column>

          <Column field="created_by.name" header="Người tạo">
            <template #body="{ data }">
              {{ data.created_by?.name }}
            </template>
          </Column>

          <Column header="Thao tác">
            <template #body="{ data }">
              <div class="flex gap-1">
                <Link
                  :href="route('manager.transfers.show', data.id)"
                  class="p-1.5 text-blue-600 hover:bg-blue-50 rounded"
                  title="Xem chi tiết"
                >
                  <i class="pi pi-eye"></i>
                </Link>

                <button
                  v-if="data.status === 'active'"
                  @click="handleRevert(data)"
                  class="p-1.5 text-orange-600 hover:bg-orange-50 rounded"
                  title="Hoàn tác"
                >
                  <i class="pi pi-undo"></i>
                </button>
              </div>
            </template>
          </Column>

          <template #empty>
            <div class="text-center py-6 text-slate-500">
              Không có dữ liệu chuyển lớp
            </div>
          </template>
        </DataTable>
      </template>
    </Card>
  </div>

  <!-- Revert Transfer Dialog -->
  <Dialog
    v-model:visible="showRevertDialog"
    modal
    header="Hoàn tác chuyển lớp"
    :style="{width: '500px'}"
    class="revert-dialog"
  >
    <div class="space-y-4">
      <div v-if="revertData.transfer" class="bg-blue-50 dark:bg-blue-900/20 p-4 rounded-lg mb-4">
        <h4 class="font-medium mb-2">Thông tin chuyển lớp:</h4>
        <div class="text-sm space-y-1">
          <div><strong>Học viên:</strong> {{ revertData.transfer.student?.code }} - {{ revertData.transfer.student?.name }}</div>
          <div><strong>Từ lớp:</strong> {{ revertData.transfer.from_class?.code }} - {{ revertData.transfer.from_class?.name }}</div>
          <div><strong>Đến lớp:</strong> {{ revertData.transfer.to_class?.code }} - {{ revertData.transfer.to_class?.name }}</div>
        </div>
      </div>

      <p class="text-slate-600 dark:text-slate-400 mb-4">
        Bạn có chắc chắn muốn hoàn tác việc chuyển lớp này không?
        Học viên sẽ được chuyển về lớp cũ.
      </p>

      <div class="space-y-3">
        <div>
          <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">
            Lý do hoàn tác <span class="text-red-500">*</span>
          </label>
          <Textarea
            v-model="revertData.reason"
            placeholder="Nhập lý do hoàn tác..."
            rows="3"
            class="w-full"
            :class="{'border-red-500': !revertData.reason.trim()}"
          />
        </div>

        <div>
          <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">
            Ghi chú thêm
          </label>
          <Textarea
            v-model="revertData.notes"
            placeholder="Ghi chú bổ sung (tùy chọn)..."
            rows="2"
            class="w-full"
          />
        </div>
      </div>
    </div>

    <template #footer>
      <div class="flex justify-end gap-2">
        <Button
          label="Hủy"
          severity="secondary"
          @click="cancelRevert"
          class="px-4 py-2"
        />
        <Button
          label="Xác nhận hoàn tác"
          severity="danger"
          @click="confirmRevert"
          :disabled="!revertData.reason.trim()"
          class="px-4 py-2"
        />
      </div>
    </template>
  </Dialog>
</template>
