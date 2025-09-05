<script setup>
import { ref, computed } from 'vue'
import { Head } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'

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

defineOptions({ layout: AppLayout })

const props = defineProps({
  student: Object,
  transfers: Array,
  auditTrails: Object,
})

const selectedTransfer = ref(null)

// Computed
const allAuditEvents = computed(() => {
  const events = []

  Object.keys(props.auditTrails).forEach(transferId => {
    const transfer = props.transfers.find(t => t.id == transferId)
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
  const stats = {
    total: props.transfers.length,
    active: 0,
    reverted: 0,
    retargeted: 0,
    totalFees: 0
  }

  props.transfers.forEach(transfer => {
    stats[transfer.status]++
    stats.totalFees += parseFloat(transfer.transfer_fee || 0)
  })

  return stats
})

// Methods
function getStatusSeverity(status) {
  const severities = {
    active: 'success',
    reverted: 'warning',
    retargeted: 'info'
  }
  return severities[status] || 'secondary'
}

function getStatusLabel(status) {
  const labels = {
    active: 'Đang hoạt động',
    reverted: 'Đã hoàn tác',
    retargeted: 'Đã đổi hướng'
  }
  return labels[status] || status
}

function formatCurrency(value) {
  if (!value) return '0 VND'
  return new Intl.NumberFormat('vi-VN', {
    style: 'currency',
    currency: 'VND'
  }).format(value)
}

function formatDate(date) {
  if (!date) return ''
  return new Date(date).toLocaleDateString('vi-VN', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit'
  })
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

function viewTransferDetails(transfer) {
  selectedTransfer.value = transfer
}
</script>

<template>
  <Head :title="`Lịch sử chuyển lớp - ${student.name}`" />

  <div class="space-y-6">
    <!-- Page Header -->
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-bold text-slate-900 dark:text-slate-100">
          Lịch sử chuyển lớp
        </h1>
        <p class="text-slate-600 dark:text-slate-400">
          {{ student.name }} ({{ student.code }})
        </p>
      </div>
      <Button
        label="Quay lại"
        icon="pi pi-arrow-left"
        @click="goBack"
        severity="secondary"
        outlined
      />
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

    <!-- Main Content -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
      <!-- Transfer List -->
      <div class="lg:col-span-2">
        <Card class="bg-white dark:bg-slate-800">
          <template #title>Danh sách chuyển lớp</template>
          <template #content>
            <DataTable
              :value="transfers"
              :paginator="false"
              stripedRows
              size="small"
              class="p-datatable-sm"
              selectionMode="single"
              v-model:selection="selectedTransfer"
              @row-select="viewTransferDetails"
            >
              <Column field="id" header="ID" style="width: 60px" />

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

              <Column field="status" header="Trạng thái">
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

              <Column field="transfer_fee" header="Phí">
                <template #body="{ data }">
                  {{ formatCurrency(data.transfer_fee) }}
                </template>
              </Column>

              <Column field="created_at" header="Ngày tạo">
                <template #body="{ data }">
                  <div class="text-sm">
                    {{ formatDate(data.created_at) }}
                  </div>
                </template>
              </Column>

              <Column header="Thao tác" style="width: 80px">
                <template #body="{ data }">
                  <router-link
                    :to="route('manager.transfers.show', data.id)"
                    class="p-1.5 text-blue-600 hover:bg-blue-50 rounded"
                    title="Xem chi tiết"
                  >
                    <i class="pi pi-eye"></i>
                  </router-link>
                </template>
              </Column>

              <template #empty>
                <div class="text-center py-6 text-slate-500">
                  <i class="pi pi-info-circle text-3xl mb-2"></i>
                  <div>Học viên này chưa có chuyển lớp nào</div>
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
              Chi tiết Transfer #{{ selectedTransfer.id }}
            </div>
            <div v-else>
              Chọn transfer để xem chi tiết
            </div>
          </template>
          <template #content>
            <div v-if="selectedTransfer" class="space-y-4">
              <!-- Transfer Info -->
              <div class="space-y-2">
                <div class="text-sm">
                  <span class="font-medium">Lý do:</span>
                  <div class="mt-1 text-slate-600">{{ selectedTransfer.reason || 'Không có' }}</div>
                </div>

                <div class="text-sm" v-if="selectedTransfer.notes">
                  <span class="font-medium">Ghi chú:</span>
                  <div class="mt-1 text-slate-600">{{ selectedTransfer.notes }}</div>
                </div>

                <div class="text-sm" v-if="selectedTransfer.admin_notes">
                  <span class="font-medium">Ghi chú admin:</span>
                  <div class="mt-1 text-slate-600">{{ selectedTransfer.admin_notes }}</div>
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
                        <Badge v-else-if="item.type === 'status_change'" value="Trạng thái" severity="warning" />
                        <Badge v-else value="Chỉnh sửa" severity="info" />
                      </div>
                      <div class="text-xs text-slate-500">
                        {{ formatDate(item.timestamp) }}
                        <span v-if="item.user">by {{ item.user.name }}</span>
                      </div>
                      <div v-if="item.details && item.details.reason" class="text-xs text-slate-600 mt-1">
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

    <!-- Complete Audit Timeline -->
    <Card class="bg-white dark:bg-slate-800">
      <template #title>
        <div class="flex items-center gap-2">
          <i class="pi pi-history"></i>
          Dòng thời gian hoạt động
        </div>
      </template>
      <template #content>
        <Timeline
          :value="allAuditEvents"
          align="alternate"
          class="w-full"
        >
          <template #content="{ item }">
            <div class="p-3 bg-slate-50 dark:bg-slate-700 rounded">
              <div class="flex items-center justify-between mb-2">
                <div class="flex items-center gap-2">
                  <span class="font-medium">Transfer #{{ item.transfer_id }}</span>
                  <Badge :value="item.transfer?.status" :severity="getStatusSeverity(item.transfer?.status)" />
                </div>
                <span class="text-xs text-slate-500">{{ formatDate(item.timestamp) }}</span>
              </div>
              <div class="text-sm">{{ item.description }}</div>
              <div v-if="item.details" class="text-xs text-slate-600 mt-2">
                <pre class="font-mono bg-slate-100 dark:bg-slate-600 p-2 rounded text-xs overflow-x-auto">{{ JSON.stringify(item.details, null, 2) }}</pre>
              </div>
            </div>
          </template>
          <template #marker="{ item }">
            <div
              class="w-5 h-5 rounded-full flex items-center justify-center text-white"
              :style="{ backgroundColor: getEventColor(item.type) }"
            >
              <i :class="getEventIcon(item.type)" class="text-xs"></i>
            </div>
          </template>
        </Timeline>
      </template>
    </Card>
  </div>
</template>

<script>
// Add goBack method for consistency
const goBack = () => {
    try {
        window.history.back()
    } catch (error) {
        router.visit(`/manager/students/${props.student.id}`)
    }
}

// Export goBack to be available in template
export { goBack }
</script>
