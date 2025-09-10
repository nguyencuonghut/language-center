<script setup>
import { reactive, ref, computed } from 'vue'
import { Head, Link } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import { createTransferService } from '@/service/TransferService.js'

// PrimeVue
import Card from 'primevue/card'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import Tag from 'primevue/tag'
import Badge from 'primevue/badge'
import Timeline from 'primevue/timeline'
import Button from 'primevue/button'
import TabView from 'primevue/tabview'
import TabPanel from 'primevue/tabpanel'
import InputText from 'primevue/inputtext'
import Select from 'primevue/select'
import DatePicker from 'primevue/datepicker'

defineOptions({ layout: AppLayout })

const props = defineProps({
  transfers: Object,
  auditTrails: Object,
  stats: Object,
  filters: Object,
  filterOptions: Object,
})

// Initialize TransferService
const transferService = createTransferService()

// Use utility functions from service
const { statusOptions, getStatusSeverity, getStatusLabel, formatDate } = transferService.utils

const selectedTransfer = ref(null)

// Local state for filters
const filters = reactive({
  search: props.filters?.search ?? '',
  status: props.filters?.status ?? '',
  date_from: props.filters?.date_from ?? '',
  date_to: props.filters?.date_to ?? '',
})

// Computed
const allAuditEvents = computed(() => {
  const events = []

  Object.keys(props.auditTrails).forEach(transferId => {
    const transfer = props.transfers.data.find(t => t.id == transferId)
    props.auditTrails[transferId].forEach(event => {
      events.push({
        ...event,
        transfer_id: transferId,
        transfer: transfer
      })
    })
  })

  // Sort by timestamp
  return events.sort((a, b) => new Date(b.timestamp) - new Date(a.timestamp))
})

const transferStats = computed(() => {
  // Use backend stats if available, otherwise calculate from current page data
  if (props.stats) {
    return {
      total: props.stats.total,
      active: props.stats.active,
      reverted: props.stats.reverted,
      retargeted: props.stats.retargeted,
      totalFees: props.stats.total_fees
    }
  }

  // Fallback: calculate from current page data
  const stats = {
    total: props.transfers.data.length,
    active: 0,
    reverted: 0,
    retargeted: 0,
    totalFees: 0
  }

  props.transfers.data.forEach(transfer => {
    stats[transfer.status]++
    stats.totalFees += parseFloat(transfer.transfer_fee || 0)
  })

  return stats
})

// Methods
function search(page = 1) {
  const params = { ...filters }

  // Format date parameters if they exist
  if (params.date_from && typeof params.date_from === 'object') {
    const year = params.date_from.getFullYear()
    const month = String(params.date_from.getMonth() + 1).padStart(2, '0')
    const day = String(params.date_from.getDate()).padStart(2, '0')
    params.date_from = `${year}-${month}-${day}`
  }
  if (params.date_to && typeof params.date_to === 'object') {
    const year = params.date_to.getFullYear()
    const month = String(params.date_to.getMonth() + 1).padStart(2, '0')
    const day = String(params.date_to.getDate()).padStart(2, '0')
    params.date_to = `${year}-${month}-${day}`
  }

  // Remove empty parameters
  Object.keys(params).forEach(key => {
    if (params[key] === '' || params[key] === null || params[key] === undefined) {
      delete params[key]
    }
  })

  if (page > 1) {
    params.page = page
  }

  transferService.getGeneralHistory(params)
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

function formatCurrency(value) {
  if (!value) return '0 VND'
  return new Intl.NumberFormat('vi-VN', {
    style: 'currency',
    currency: 'VND'
  }).format(value)
}

function getEventIcon(type) {
  const icons = {
    created: 'pi pi-plus-circle',
    status_change: 'pi pi-refresh',
    field_change: 'pi pi-pencil'
  }
  return icons[type] || 'pi pi-circle'
}

function getEventColor(type) {
  const colors = {
    created: '#10B981',
    status_change: '#F59E0B',
    field_change: '#6366F1'
  }
  return colors[type] || '#64748B'
}

function viewTransferDetails(event) {
  selectedTransfer.value = event.data
}
</script>

<template>
  <Head title="Lịch sử chuyển lớp" />

  <div class="space-y-6">
    <!-- Page Header -->
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-bold text-slate-900 dark:text-slate-100">
          Lịch sử chuyển lớp
        </h1>
        <p class="text-slate-600 dark:text-slate-400">
          Xem lịch sử chuyển lớp của tất cả học viên
        </p>
      </div>
      <div class="flex items-center gap-2">
        <Link
          :href="route('manager.transfers.index')"
          class="inline-flex items-center px-3 py-2 bg-slate-600 text-white rounded-lg hover:bg-slate-700 transition-colors text-sm"
        >
          <i class="pi pi-arrow-left mr-2"></i>
          Quay lại
        </Link>
        <Link
          :href="route('manager.transfers.advanced.search')"
          class="inline-flex items-center px-3 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors text-sm"
        >
          <i class="pi pi-search mr-2"></i>
          Tìm kiếm nâng cao
        </Link>
      </div>
    </div>

    <!-- Summary Stats -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4">
      <Card class="bg-white dark:bg-slate-800">
        <template #content>
          <div class="text-center">
            <div class="text-2xl font-bold text-blue-600">{{ transferStats.total }}</div>
            <div class="text-sm text-slate-600 dark:text-slate-400">Tổng chuyển lớp</div>
          </div>
        </template>
      </Card>

      <Card class="bg-white dark:bg-slate-800">
        <template #content>
          <div class="text-center">
            <div class="text-2xl font-bold text-green-600">{{ transferStats.active }}</div>
            <div class="text-sm text-slate-600 dark:text-slate-400">Đang hoạt động</div>
          </div>
        </template>
      </Card>

      <Card class="bg-white dark:bg-slate-800">
        <template #content>
          <div class="text-center">
            <div class="text-2xl font-bold text-orange-600">{{ transferStats.reverted }}</div>
            <div class="text-sm text-slate-600 dark:text-slate-400">Đã hoàn tác</div>
          </div>
        </template>
      </Card>

      <Card class="bg-white dark:bg-slate-800">
        <template #content>
          <div class="text-center">
            <div class="text-2xl font-bold text-purple-600">{{ transferStats.retargeted }}</div>
            <div class="text-sm text-slate-600 dark:text-slate-400">Đã đổi hướng</div>
          </div>
        </template>
      </Card>

      <Card class="bg-white dark:bg-slate-800">
        <template #content>
          <div class="text-center">
            <div class="text-lg font-bold text-emerald-600">{{ formatCurrency(transferStats.totalFees) }}</div>
            <div class="text-sm text-slate-600 dark:text-slate-400">Tổng phí</div>
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
              v-model="filters.search"
              placeholder="Tên học viên, mã học viên..."
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
              v-model="filters.date_from"
              dateFormat="yy-mm-dd"
              placeholder="yyyy-mm-dd"
              class="w-full"
              showClear
              @date-select="search"
            />
          </div>

          <div>
            <label class="block text-sm font-medium mb-1">Đến ngày</label>
            <DatePicker
              v-model="filters.date_to"
              dateFormat="yy-mm-dd"
              placeholder="yyyy-mm-dd"
              class="w-full"
              showClear
              @date-select="search"
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

    <!-- Main Content -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
      <!-- Transfer List -->
      <div class="lg:col-span-2">
        <Card class="bg-white dark:bg-slate-800">
          <template #title>Danh sách chuyển lớp</template>
          <template #content>
            <DataTable
              :value="transfers.data"
              :paginator="true"
              :rows="transfers.per_page"
              :total-records="transfers.total"
              :first="(transfers.current_page - 1) * transfers.per_page"
              lazy
              data-key="id"
              stripedRows
              size="small"
              class="p-datatable-sm"
              selectionMode="single"
              v-model:selection="selectedTransfer"
              @row-select="viewTransferDetails"
              @page="onPage"
              paginatorTemplate="RowsPerPageDropdown FirstPageLink PrevPageLink PageLinks NextPageLink LastPageLink CurrentPageReport"
              currentPageReportTemplate="Hiển thị {first} - {last} trên tổng số {totalRecords} bản ghi"
              :rowsPerPageOptions="[10, 20, 50]"
            >
              <Column field="student.code" header="Mã HV" style="width: 100px">
                <template #body="{ data }">
                  <Link
                    v-if="data.student_id"
                    :href="route('manager.students.show', data.student_id)"
                    class="text-blue-600 hover:text-blue-800 font-medium"
                  >
                    {{ data.student?.code }}
                  </Link>
                  <span v-else class="text-slate-500">
                    {{ data.student?.code || 'N/A' }}
                  </span>
                </template>
              </Column>

              <Column field="student.name" header="Học viên" style="width: 150px">
                <template #body="{ data }">
                  {{ data.student?.name }}
                </template>
              </Column>

              <Column header="Chuyển từ → đến">
                <template #body="{ data }">
                  <div class="text-sm">
                    <div class="flex items-center gap-2">
                      <span class="font-medium">{{ data.from_class?.code }}</span>
                      <i class="pi pi-arrow-right text-xs text-slate-400"></i>
                      <span class="font-medium text-green-600">
                        {{ data.status === 'retargeted' ? data.retargeted_to_class?.code : data.to_class?.code }}
                      </span>
                    </div>
                    <div class="text-slate-500 text-xs">
                      {{ data.effective_date }}
                    </div>
                  </div>
                </template>
              </Column>

              <Column field="status" header="Trạng thái" style="width: 120px">
                <template #body="{ data }">
                  <div class="flex items-center gap-2">
                    <Tag
                      :value="getStatusLabel(data.status)"
                      :severity="getStatusSeverity(data.status)"
                    />
                    <Badge v-if="data.is_priority" value="!" severity="danger" />
                  </div>
                </template>
              </Column>

              <Column field="transfer_fee" header="Phí" style="width: 100px">
                <template #body="{ data }">
                  {{ formatCurrency(data.transfer_fee) }}
                </template>
              </Column>

              <Column field="created_at" header="Ngày tạo" style="width: 120px">
                <template #body="{ data }">
                  <div class="text-sm">
                    {{ formatDate(data.created_at) }}
                  </div>
                </template>
              </Column>

              <Column header="Thao tác" style="width: 80px">
                <template #body="{ data }">
                  <div class="flex gap-1">
                    <Link
                      :href="route('manager.transfers.show', data.id)"
                      class="p-1.5 text-blue-600 hover:bg-blue-50 rounded"
                      title="Xem chi tiết"
                    >
                      <i class="pi pi-eye"></i>
                    </Link>
                    <Link
                      v-if="data.student_id"
                      :href="route('manager.students.transfer-history', data.student_id)"
                      class="p-1.5 text-purple-600 hover:bg-purple-50 rounded"
                      title="Lịch sử học viên"
                    >
                      <i class="pi pi-history"></i>
                    </Link>
                    <span
                      v-else
                      class="p-1.5 text-slate-400 rounded"
                      title="Không có thông tin học viên"
                    >
                      <i class="pi pi-history"></i>
                    </span>
                  </div>
                </template>
              </Column>

              <template #empty>
                <div class="text-center py-6 text-slate-500">
                  <i class="pi pi-info-circle text-3xl mb-2"></i>
                  <div>Không có dữ liệu chuyển lớp</div>
                </div>
              </template>
            </DataTable>
          </template>
        </Card>
      </div>

      <!-- Transfer Details & Audit Trail -->
      <div>
        <Card class="bg-white dark:bg-slate-800">
          <template #title>
            <div v-if="selectedTransfer">
              Chi tiết chuyển lớp #{{ selectedTransfer.id }}
            </div>
            <div v-else>
              Chọn chuyển lớp để xem chi tiết
            </div>
          </template>
          <template #content>
            <div v-if="selectedTransfer" class="space-y-4">
              <!-- Student Info -->
              <div class="bg-slate-50 dark:bg-slate-700 p-3 rounded">
                <div class="font-medium mb-2">Thông tin học viên</div>
                <div class="text-sm space-y-1">
                  <div><span class="font-medium">Mã:</span> {{ selectedTransfer.student?.code }}</div>
                  <div><span class="font-medium">Tên:</span> {{ selectedTransfer.student?.name }}</div>
                  <Link
                    v-if="selectedTransfer.student_id"
                    :href="route('manager.students.show', selectedTransfer.student_id)"
                    class="text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300 text-sm"
                  >
                    <i class="pi pi-external-link mr-1"></i>
                    Xem hồ sơ
                  </Link>
                  <span v-else class="text-slate-500 dark:text-slate-400 text-sm">
                    Không có thông tin học viên
                  </span>
                </div>
              </div>

              <!-- Transfer Info -->
              <div class="space-y-2">
                <div class="text-sm">
                  <span class="font-medium">Lý do:</span>
                  <div class="mt-1 text-slate-600 dark:text-slate-300">{{ selectedTransfer.reason || 'Không có' }}</div>
                </div>

                <div class="text-sm" v-if="selectedTransfer.notes">
                  <span class="font-medium">Ghi chú:</span>
                  <div class="mt-1 text-slate-600 dark:text-slate-300">{{ selectedTransfer.notes }}</div>
                </div>

                <div class="text-sm" v-if="selectedTransfer.admin_notes">
                  <span class="font-medium">Ghi chú admin:</span>
                  <div class="mt-1 text-slate-600 dark:text-slate-300">{{ selectedTransfer.admin_notes }}</div>
                </div>

                <div class="text-sm">
                  <span class="font-medium">Người tạo:</span>
                  {{ selectedTransfer.created_by?.name || 'N/A' }}
                </div>

                <div class="text-sm">
                  <span class="font-medium">Nguồn:</span>
                  <Badge
                    :value="selectedTransfer.source_system"
                    :severity="selectedTransfer.source_system === 'manual' ? 'info' : 'secondary'"
                  />
                </div>
              </div>

              <!-- Audit Trail -->
              <div class="border-t pt-4">
                <h4 class="font-medium mb-3">Lịch sử thay đổi</h4>
                <Timeline
                  :value="auditTrails[selectedTransfer.id] || []"
                  align="left"
                  class="w-full"
                >
                  <template #content="{ item }">
                    <div class="ml-3">
                      <div class="flex items-center gap-2 mb-1">
                        <span class="text-sm font-medium">{{ item.description }}</span>
                        <Badge v-if="item.type === 'created'" value="Tạo" severity="success" />
                        <Badge v-else-if="item.type === 'status_change'" value="Trạng thái" severity="warn" />
                        <Badge v-else value="Chỉnh sửa" severity="info" />
                      </div>
                      <div class="text-xs text-slate-500 dark:text-slate-400">
                        {{ formatDate(item.timestamp) }}
                        <span v-if="item.user">by {{ item.user.name }}</span>
                      </div>
                      <div v-if="item.details && item.details.reason" class="text-xs text-slate-600 dark:text-slate-300 mt-1">
                        <i class="pi pi-comment mr-1"></i>
                        {{ item.details.reason }}
                      </div>
                    </div>
                  </template>
                  <template #marker="{ item }">
                    <div
                      class="w-4 h-4 rounded-full flex items-center justify-center text-white text-xs"
                      :style="{ backgroundColor: getEventColor(item.type) }"
                    >
                      <i :class="getEventIcon(item.type)" class="text-xs"></i>
                    </div>
                  </template>
                </Timeline>
              </div>
            </div>

            <div v-else class="text-center py-8 text-slate-500">
              <i class="pi pi-info-circle text-4xl mb-4"></i>
              <div>Chọn một transfer từ danh sách để xem chi tiết và lịch sử thay đổi</div>
            </div>
          </template>
        </Card>
      </div>
    </div>
  </div>
</template>
