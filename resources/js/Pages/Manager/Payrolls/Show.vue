<script setup>
import { computed } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'

// PrimeVue
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import Tag from 'primevue/tag'
import Button from 'primevue/button'
import Select from 'primevue/select'

defineOptions({ layout: AppLayout })

/**
 * EXPECTED PROPS (tối ưu hoá để linh hoạt):
 * - payroll: {
 *     id, code, status, period_from, period_to, total_amount, branch_id, branch_name? | branch?.name
 *   }
 * - items: LengthAwarePaginator [{
 *     id, teacher_id, teacher_name, class_session_id, class_name?, date, amount, status
 *   }]
 * - filters: { perPage?: number, sort?: string, order?: 'asc'|'desc' }
 * - errors/auth/flash: nhận để tránh Vue warn
 */
const props = defineProps({
  errors: Object,
  auth: Object,
  flash: Object,
  payroll: { type: Object, required: true },
  items: { type: Object, required: true },
  filters: { type: Object, default: () => ({}) }
})

/* ---------------- Helpers ---------------- */
function fmtDateDDMMYYYY(date) {
  if (!date) return ''
  const d = new Date(date)
  if (isNaN(d.getTime())) return ''
  const day = String(d.getDate()).padStart(2, '0')
  const month = String(d.getMonth() + 1).padStart(2, '0')
  const year = d.getFullYear()
  return `${day}/${month}/${year}`
}

function vnd(n) {
  const x = Number(n ?? 0)
  return x.toLocaleString('vi-VN') + ' ₫'
}

function statusTagSeverity(st) {
  switch (st) {
    case 'draft': return 'info'
    case 'approved': return 'success'
    case 'locked': return 'warning'
    default: return 'info'
  }
}

const branchName = computed(() => props.payroll.branch?.name ?? props.payroll.branch_name ?? 'Tất cả chi nhánh')

/* ---------------- Pagination/Sorting ---------------- */
const rows = computed(() => props.items?.per_page ?? 20)
const first = computed(() => Math.max(0, (props.items?.from ?? 1) - 1))
const totalRecords = computed(() => props.items?.total ?? 0)

function buildQuery(extra = {}) {
  const q = {}
  if (props.filters?.perPage && props.filters.perPage !== rows.value) q.per_page = props.filters.perPage
  if (props.filters?.sort) q.sort = props.filters.sort
  if (props.filters?.order) q.order = props.filters.order
  return { ...q, ...extra }
}

function onPage(e) {
  const page = Math.floor(e.first / e.rows) + 1
  router.visit(route('manager.payrolls.show', props.payroll.id), {
    data: buildQuery({ per_page: e.rows, page: page > 1 ? page : undefined }),
    preserveScroll: true,
    preserveState: true
  })
}

/* ---------------- Actions ---------------- */
function approve() {
  if (!confirm('Duyệt bảng lương này?')) return
  router.post(route('manager.payrolls.approve', props.payroll.id), {}, { preserveScroll: true })
}
function lockPayroll() {
  if (!confirm('Khóa bảng lương này? Sau khi khóa sẽ không chỉnh sửa.')) return
  router.post(route('manager.payrolls.lock', props.payroll.id), {}, { preserveScroll: true })
}
</script>

<template>
  <Head :title="`Bảng lương ${payroll.code || '#'+payroll.id}`" />

  <div class="mb-4">
    <div class="flex items-start justify-between gap-3">
      <div>
        <h1 class="text-2xl font-heading font-semibold">
          Bảng lương {{ payroll.code || ('#' + payroll.id) }}
        </h1>
        <div class="mt-1 text-sm text-slate-600 dark:text-slate-300 space-x-2">
          <span>Chi nhánh: <b>{{ branchName }}</b></span>
        </div>
      </div>

      <div class="flex items-center gap-2">
        <Button
          v-if="payroll.status === 'draft'"
          label="Duyệt"
          icon="pi pi-check"
          @click="approve"
        />
        <Button
          v-if="payroll.status === 'approved'"
          label="Khóa"
          icon="pi pi-lock"
          severity="warning"
          @click="lockPayroll"
        />
        <Link
          :href="route('manager.payrolls.index')"
          class="px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-600 hover:bg-slate-100 dark:hover:bg-slate-800"
        >
          ← Danh sách
        </Link>
      </div>
    </div>
  </div>

  <!-- Tổng quan -->
  <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
    <div class="rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 p-4">
      <div class="text-sm text-slate-500 dark:text-slate-400">Tổng tiền</div>
      <div class="mt-1 text-2xl font-semibold">{{ vnd(payroll.total_amount) }}</div>
    </div>
    <div class="rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 p-4">
      <div class="text-sm text-slate-500 dark:text-slate-400">Kỳ trả lương</div>
      <div class="mt-1 font-medium">
        {{ fmtDateDDMMYYYY(payroll.period_from) }} — {{ fmtDateDDMMYYYY(payroll.period_to) }}
      </div>
    </div>
    <div class="rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 p-4">
      <div class="text-sm text-slate-500 dark:text-slate-400">Trạng thái</div>
      <div class="mt-1">
        <Tag :value="payroll.status" :severity="statusTagSeverity(payroll.status)" />
      </div>
    </div>
  </div>

  <!-- Items -->
  <div class="rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 p-2">
    <div class="flex items-center justify-between px-2 py-2">
      <div class="font-medium">Chi tiết tiền buổi</div>
      <Select
        :modelValue="rows"
        :options="[{label:'20 / trang',value:20},{label:'50 / trang',value:50},{label:'100 / trang',value:100}]"
        optionLabel="label" optionValue="value"
        class="w-40"
        @change="(e)=> onPage({ first:0, rows:e.value })"
      />
    </div>

    <DataTable
      :value="items?.data ?? []"
      :paginator="true"
      :rows="rows"
      :totalRecords="totalRecords"
      :first="first"
      dataKey="id"
      responsiveLayout="scroll"
      size="small"
      @page="onPage"
    >
      <Column field="id" header="#" style="width: 80px" >
        <template #body="slotProps">
            {{ slotProps.index + 1 }}
        </template>
      </Column>
      <Column field="teacher.name" header="Giáo viên" />
      <Column field="session.classroom.name" header="Lớp" />
      <Column field="session.date" header="Ngày" style="width: 140px">
        <template #body="{ data }">
          {{ fmtDateDDMMYYYY(data.session.date) }}
        </template>
      </Column>
      <Column field="amount" header="Tiền buổi" style="width: 160px">
        <template #body="{ data }">
          {{ vnd(data.amount) }}
        </template>
      </Column>
      <Column field="timesheet_status" header="Trạng thái" style="width: 120px">
        <template #body="{ data }">
          <Tag :value="data.timesheet_status" :severity="statusTagSeverity(data.timesheet_status)" />
        </template>
      </Column>

      <template #empty>
        <div class="p-6 text-center text-slate-500 dark:text-slate-400">
          Chưa có dòng nào trong bảng lương này.
        </div>
      </template>
    </DataTable>
  </div>
</template>
