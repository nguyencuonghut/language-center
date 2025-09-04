<script setup>
import { reactive, ref, computed } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'

// PrimeVue
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import Button from 'primevue/button'
import Card from 'primevue/card'
import InputText from 'primevue/inputtext'
import Select from 'primevue/select'
import MultiSelect from 'primevue/multiselect'
import DatePicker from 'primevue/datepicker'
import InputNumber from 'primevue/inputnumber'
import Tag from 'primevue/tag'
import Badge from 'primevue/badge'
import Accordion from 'primevue/accordion'
import AccordionPanel from 'primevue/accordionpanel'

defineOptions({ layout: AppLayout })

const props = defineProps({
  transfers: Object,
  filters: Object,
  filterOptions: Object,
})

// Reactive filters
const filters = reactive({
  search: props.filters?.search ?? '',
  status: props.filters?.status ?? null,
  priority: props.filters?.priority ?? null,
  source_system: props.filters?.source_system ?? '',
  created_by: props.filters?.created_by ?? null,
  date_from: props.filters?.date_from ? new Date(props.filters.date_from) : null,
  date_to: props.filters?.date_to ? new Date(props.filters.date_to) : null,
  fee_min: props.filters?.fee_min ?? null,
  fee_max: props.filters?.fee_max ?? null,
  reason: props.filters?.reason ?? '',
  from_class: props.filters?.from_class ?? null,
  to_class: props.filters?.to_class ?? null,
  sort_field: props.filters?.sort_field ?? 'created_at',
  sort_direction: props.filters?.sort_direction ?? 'desc',
})

const showAdvancedFilters = ref(false)

// Computed
const hasActiveFilters = computed(() => {
  return Object.keys(filters).some(key => {
    if (['sort_field', 'sort_direction'].includes(key)) return false
    const value = filters[key]
    return value !== null && value !== '' && value !== undefined
  })
})

// Methods
function search() {
  const params = { ...filters }

  // Convert dates to strings
  if (params.date_from) {
    params.date_from = params.date_from.toISOString().split('T')[0]
  }
  if (params.date_to) {
    params.date_to = params.date_to.toISOString().split('T')[0]
  }

  router.get(route('manager.transfers.advanced.search'), params, {
    preserveState: true,
    preserveScroll: true,
  })
}

function resetFilters() {
  Object.keys(filters).forEach(key => {
    if (['sort_field', 'sort_direction'].includes(key)) return
    filters[key] = ['status'].includes(key) ? null : ''
  })
  filters.priority = null
  filters.created_by = null
  filters.from_class = null
  filters.to_class = null
  filters.date_from = null
  filters.date_to = null
  filters.fee_min = null
  filters.fee_max = null
  search()
}

function exportResults() {
  const params = { ...filters }
  if (params.date_from) params.date_from = params.date_from.toISOString().split('T')[0]
  if (params.date_to) params.date_to = params.date_to.toISOString().split('T')[0]

  window.open(route('manager.transfers.advanced.reports.export', params), '_blank')
}

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
  return new Date(date).toLocaleDateString('vi-VN')
}

function onSort(event) {
  filters.sort_field = event.sortField
  filters.sort_direction = event.sortOrder === 1 ? 'asc' : 'desc'
  search()
}
</script>

<template>
  <Head title="Tìm kiếm nâng cao - Chuyển lớp" />

  <div class="space-y-6">
    <!-- Page Header -->
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-bold text-slate-900 dark:text-slate-100">
          Tìm kiếm nâng cao
        </h1>
        <p class="text-slate-600 dark:text-slate-400">
          Tìm kiếm và lọc chuyển lớp với nhiều tiêu chí
        </p>
      </div>
      <div class="flex items-center gap-2">
        <Button
          label="Xuất kết quả"
          icon="pi pi-download"
          @click="exportResults"
          severity="success"
          :disabled="!transfers.data?.length"
        />
        <Button
          label="Báo cáo"
          icon="pi pi-chart-bar"
          @click="router.visit(route('manager.transfers.advanced.reports'))"
          severity="info"
        />
      </div>
    </div>

    <!-- Search Filters -->
    <Card class="bg-white dark:bg-slate-800">
      <template #content>
        <div class="space-y-4">
          <!-- Basic Search -->
          <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <div>
              <label class="block text-sm font-medium mb-1">Tìm kiếm</label>
              <InputText
                v-model="filters.search"
                placeholder="Tên, mã học viên, lý do..."
                class="w-full"
                @keyup.enter="search"
              />
            </div>

            <div>
              <label class="block text-sm font-medium mb-1">Trạng thái</label>
              <MultiSelect
                v-model="filters.status"
                :options="filterOptions.statuses"
                optionLabel="label"
                optionValue="value"
                placeholder="Chọn trạng thái"
                class="w-full"
                :maxSelectedLabels="2"
              />
            </div>

            <div>
              <label class="block text-sm font-medium mb-1">Ưu tiên</label>
              <Select
                v-model="filters.priority"
                :options="[
                  { label: 'Tất cả', value: null },
                  { label: 'Ưu tiên', value: true },
                  { label: 'Thường', value: false }
                ]"
                optionLabel="label"
                optionValue="value"
                placeholder="Chọn mức độ"
                class="w-full"
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
                icon="pi pi-filter"
                @click="showAdvancedFilters = !showAdvancedFilters"
                :severity="hasActiveFilters ? 'info' : 'secondary'"
                outlined
              />
            </div>
          </div>

          <!-- Advanced Filters -->
          <Accordion v-if="showAdvancedFilters" class="mt-4">
            <AccordionPanel value="0">
              <template #header>
                <span class="flex items-center gap-2">
                  <i class="pi pi-filter"></i>
                  Bộ lọc nâng cao
                  <Badge v-if="hasActiveFilters" :value="Object.keys(filters).filter(k => filters[k]).length" severity="info" />
                </span>
              </template>
              <template #content>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                  <!-- Date Range -->
                  <div>
                    <label class="block text-sm font-medium mb-1">Từ ngày</label>
                    <DatePicker
                      v-model="filters.date_from"
                      dateFormat="dd/mm/yy"
                      placeholder="Chọn ngày bắt đầu"
                      class="w-full"
                    />
                  </div>

                  <div>
                    <label class="block text-sm font-medium mb-1">Đến ngày</label>
                    <DatePicker
                      v-model="filters.date_to"
                      dateFormat="dd/mm/yy"
                      placeholder="Chọn ngày kết thúc"
                      class="w-full"
                    />
                  </div>

                  <!-- Fee Range -->
                  <div>
                    <label class="block text-sm font-medium mb-1">Phí từ (VND)</label>
                    <InputNumber
                      v-model="filters.fee_min"
                      placeholder="Phí tối thiểu"
                      class="w-full"
                      :min="0"
                      mode="currency"
                      currency="VND"
                      locale="vi-VN"
                    />
                  </div>

                  <div>
                    <label class="block text-sm font-medium mb-1">Phí đến (VND)</label>
                    <InputNumber
                      v-model="filters.fee_max"
                      placeholder="Phí tối đa"
                      class="w-full"
                      :min="0"
                      mode="currency"
                      currency="VND"
                      locale="vi-VN"
                    />
                  </div>

                  <!-- Source System -->
                  <div>
                    <label class="block text-sm font-medium mb-1">Nguồn tạo</label>
                    <Select
                      v-model="filters.source_system"
                      :options="filterOptions.sources"
                      optionLabel="label"
                      optionValue="value"
                      placeholder="Chọn nguồn"
                      class="w-full"
                    />
                  </div>

                  <!-- Created By -->
                  <div>
                    <label class="block text-sm font-medium mb-1">Người tạo</label>
                    <Select
                      v-model="filters.created_by"
                      :options="filterOptions.creators"
                      optionLabel="label"
                      optionValue="value"
                      placeholder="Chọn người tạo"
                      class="w-full"
                      filter
                    />
                  </div>

                  <!-- Classes -->
                  <div>
                    <label class="block text-sm font-medium mb-1">Từ lớp</label>
                    <Select
                      v-model="filters.from_class"
                      :options="filterOptions.classes"
                      optionLabel="label"
                      optionValue="value"
                      placeholder="Chọn lớp nguồn"
                      class="w-full"
                      filter
                    />
                  </div>

                  <div>
                    <label class="block text-sm font-medium mb-1">Đến lớp</label>
                    <Select
                      v-model="filters.to_class"
                      :options="filterOptions.classes"
                      optionLabel="label"
                      optionValue="value"
                      placeholder="Chọn lớp đích"
                      class="w-full"
                      filter
                    />
                  </div>

                  <!-- Reason -->
                  <div>
                    <label class="block text-sm font-medium mb-1">Lý do</label>
                    <InputText
                      v-model="filters.reason"
                      placeholder="Tìm theo lý do..."
                      class="w-full"
                    />
                  </div>
                </div>

                <div class="flex items-center justify-end gap-2 mt-4 pt-4 border-t">
                  <Button
                    label="Đặt lại"
                    severity="secondary"
                    @click="resetFilters"
                    outlined
                  />
                  <Button
                    label="Áp dụng lọc"
                    icon="pi pi-search"
                    @click="search"
                  />
                </div>
              </template>
            </AccordionPanel>
          </Accordion>
        </div>
      </template>
    </Card>

    <!-- Results -->
    <Card class="bg-white dark:bg-slate-800">
      <template #title>
        <div class="flex items-center justify-between">
          <span>Kết quả tìm kiếm</span>
          <div class="text-sm text-slate-500">
            {{ transfers.total }} kết quả
          </div>
        </div>
      </template>
      <template #content>
        <DataTable
          :value="transfers.data"
          :lazy="true"
          :paginator="true"
          :rows="transfers.per_page"
          :totalRecords="transfers.total"
          :first="(transfers.current_page - 1) * transfers.per_page"
          @sort="onSort"
          stripedRows
          size="small"
          class="p-datatable-sm"
        >
          <Column field="id" header="ID" :sortable="true" style="width: 80px" />

          <Column field="student.code" header="Mã HV" :sortable="true">
            <template #body="{ data }">
              <router-link
                :to="route('manager.students.show', data.student_id)"
                class="text-blue-600 hover:text-blue-800 font-medium"
              >
                {{ data.student?.code }}
              </router-link>
            </template>
          </Column>

          <Column field="student.name" header="Học viên" :sortable="true">
            <template #body="{ data }">
              <div class="flex items-center gap-2">
                {{ data.student?.name }}
                <Badge v-if="data.is_priority" value="!" severity="danger" />
              </div>
            </template>
          </Column>

          <Column header="Chuyển từ → đến">
            <template #body="{ data }">
              <div class="text-sm">
                <div class="flex items-center gap-2">
                  <span class="font-medium">{{ data.from_class?.code }}</span>
                  <i class="pi pi-arrow-right text-xs text-slate-400"></i>
                  <span class="font-medium text-green-600">{{ data.to_class?.code }}</span>
                </div>
                <div class="text-slate-500 text-xs">
                  {{ data.from_class?.name }} → {{ data.to_class?.name }}
                </div>
              </div>
            </template>
          </Column>

          <Column field="reason" header="Lý do">
            <template #body="{ data }">
              <div class="max-w-xs truncate" :title="data.reason">
                {{ data.reason }}
              </div>
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

          <Column field="transfer_fee" header="Phí" :sortable="true">
            <template #body="{ data }">
              {{ formatCurrency(data.transfer_fee) }}
            </template>
          </Column>

          <Column field="source_system" header="Nguồn">
            <template #body="{ data }">
              <Badge
                :value="data.source_system"
                :severity="data.source_system === 'manual' ? 'info' : 'secondary'"
              />
            </template>
          </Column>

          <Column field="created_at" header="Ngày tạo" :sortable="true">
            <template #body="{ data }">
              {{ formatDate(data.created_at) }}
            </template>
          </Column>

          <Column header="Thao tác" style="width: 100px">
            <template #body="{ data }">
              <div class="flex gap-1">
                <router-link
                  :to="route('manager.transfers.show', data.id)"
                  class="p-1.5 text-blue-600 hover:bg-blue-50 rounded"
                  title="Xem chi tiết"
                >
                  <i class="pi pi-eye"></i>
                </router-link>
                <router-link
                  :to="route('manager.students.transfer-history', data.student_id)"
                  class="p-1.5 text-purple-600 hover:bg-purple-50 rounded"
                  title="Lịch sử chuyển lớp"
                >
                  <i class="pi pi-history"></i>
                </router-link>
              </div>
            </template>
          </Column>

          <template #empty>
            <div class="text-center py-6 text-slate-500">
              <i class="pi pi-search text-3xl mb-2"></i>
              <div>Không tìm thấy kết quả phù hợp</div>
              <div class="text-sm">Thử điều chỉnh bộ lọc hoặc từ khóa tìm kiếm</div>
            </div>
          </template>
        </DataTable>
      </template>
    </Card>
  </div>
</template>

<style scoped>
.p-accordion-content {
  padding: 1rem;
}
</style>
