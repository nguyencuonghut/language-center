<script setup>
import { reactive, ref, computed } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'

// PrimeVue
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import Select from 'primevue/select'
import Button from 'primevue/button'
import Tag from 'primevue/tag'
import Checkbox from 'primevue/checkbox'

defineOptions({ layout: AppLayout })

const props = defineProps({
  timesheets: Object, // paginator
  branches: Array,
  filters: Object,    // { status, branch, perPage }
})

const state = reactive({
  status: props.filters?.status ?? 'draft',
  branch: props.filters?.branch ?? 'all',
  perPage: props.filters?.perPage ?? (props.timesheets?.per_page ?? 20),
})

function buildQuery(extra = {}) {
  const q = {}
  if (state.status) q.status = state.status
  if (state.branch && state.branch !== 'all') q.branch = state.branch
  if (state.perPage && state.perPage !== props.timesheets?.per_page) q.per_page = state.perPage
  Object.assign(q, extra)
  return q
}
function applyFilters() {
  router.visit(route('manager.timesheets.index', buildQuery()), { preserveScroll: true, preserveState: true })
}
function onPage(e) {
  const page = Math.floor(e.first / e.rows) + 1
  router.visit(route('manager.timesheets.index', buildQuery({
    per_page: e.rows,
    page: page > 1 ? page : undefined
  })), { preserveScroll: true, preserveState: true })
}

const value = computed(() => props.timesheets?.data ?? [])
const totalRecords = computed(() => props.timesheets?.total ?? value.value.length)
const rows = computed(() => props.timesheets?.per_page ?? 20)
const first = computed(() => Math.max(0, (props.timesheets?.from ?? 1) - 1))

/* Bulk select */
const selectedIds = ref([])
function toggleSelect(id, checked) {
  if (checked) {
    if (!selectedIds.value.includes(id)) selectedIds.value.push(id)
  } else {
    selectedIds.value = selectedIds.value.filter(x => x !== id)
  }
}
function approveOne(id) {
  router.post(route('manager.timesheets.approve', id), {}, { preserveScroll: true })
}
function bulkApprove() {
  if (!selectedIds.value.length) return
  router.post(route('manager.timesheets.bulk-approve'), { ids: selectedIds.value }, {
    preserveScroll: true,
    onSuccess: () => { selectedIds.value = [] }
  })
}
</script>

<template>
  <Head title="Duyệt bảng công" />

  <div class="mb-3 flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
    <h1 class="text-xl md:text-2xl font-heading font-semibold">Duyệt bảng công</h1>

    <div class="flex flex-wrap items-center gap-2">
      <Select
        v-model="state.status"
        :options="[
          {label:'Nháp', value:'draft'},
          {label:'Đã duyệt', value:'approved'},
          {label:'Khoá', value:'locked'},
          {label:'Tất cả', value:'all'},
        ]"
        optionLabel="label" optionValue="value" class="w-44" @change="applyFilters"
      />
      <Select
        v-model="state.branch"
        :options="[{label:'Tất cả chi nhánh', value:'all'}, ...(props.branches||[]).map(b=>({label:b.name, value:String(b.id)}))]"
        optionLabel="label" optionValue="value" class="w-56" @change="applyFilters"
      />
      <Select
        v-model="state.perPage"
        :options="[{label:'20 / trang',value:20},{label:'50 / trang',value:50},{label:'100 / trang',value:100}]"
        optionLabel="label" optionValue="value" class="w-40" @change="applyFilters"
      />

      <Button label="Duyệt đã chọn" icon="pi pi-check" @click="bulkApprove" />
    </div>
  </div>

  <div class="rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 p-2">
    <DataTable
      :value="value"
      :paginator="true"
      :rows="rows"
      :totalRecords="totalRecords"
      :first="first"
      lazy
      @page="onPage"
      dataKey="id"
      responsiveLayout="scroll"
      size="small"
    >
      <Column header="" style="width: 60px">
        <template #body="{ data }">
          <Checkbox :modelValue="selectedIds.includes(data.id)" @update:modelValue="(v)=>toggleSelect(data.id,v)" :binary="true" :disabled="data.status!=='draft'" />
        </template>
      </Column>

      <Column field="id" header="#" style="width: 80px" />
      <Column header="Giáo viên" style="width: 220px">
        <template #body="{ data }">{{ data.teacher?.name ?? '—' }}</template>
      </Column>
      <Column header="Lớp" style="width: 220px">
        <template #body="{ data }">{{ data.session?.classroom?.name ?? data.session?.classroom?.code ?? '—' }}</template>
      </Column>
      <Column header="Ngày" style="width: 140px">
        <template #body="{ data }">{{ data.session?.date ?? '—' }}</template>
      </Column>
      <Column header="Giờ" style="width: 160px">
        <template #body="{ data }">
          {{ (data.session?.start_time || '').slice(0,5) }}–{{ (data.session?.end_time || '').slice(0,5) }}
        </template>
      </Column>
      <Column header="Phòng" style="width: 180px">
        <template #body="{ data }">{{ data.session?.room?.code ? (data.session.room.code+' - ') : '' }}{{ data.session?.room?.name ?? '—' }}</template>
      </Column>
      <Column field="amount" header="Tiền buổi (VND)" style="width: 160px" />
      <Column field="status" header="Trạng thái" style="width: 140px">
        <template #body="{ data }">
          <Tag :value="data.status" :severity="data.status==='draft' ? 'info' : data.status==='approved' ? 'success' : 'warning'" />
        </template>
      </Column>
      <Column header="" style="width: 160px">
        <template #body="{ data }">
          <div class="flex justify-end">
            <Button
              v-if="data.status==='draft'"
              label="Duyệt"
              icon="pi pi-check"
              size="small"
              @click="approveOne(data.id)"
            />
          </div>
        </template>
      </Column>

      <template #empty>
        <div class="p-6 text-center text-slate-500 dark:text-slate-400">Không có bản ghi.</div>
      </template>
    </DataTable>
  </div>
</template>
