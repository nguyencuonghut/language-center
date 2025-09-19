<script setup>
import { Head, Link, router } from '@inertiajs/vue3'
import { reactive, computed } from 'vue'
import AppLayout from '@/Layouts/AppLayout.vue'

// PrimeVue
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import Button from 'primevue/button'
import Tag from 'primevue/tag'
import Select from 'primevue/select'

defineOptions({ layout: AppLayout })

const props = defineProps({
  holidays: Object,     // paginator
  filters: Object,      // {scope, branch_id, class_id, perPage}
  branches: Array,      // [{id,name}]
  classes: Array        // [{id,code,name}]
})

const rows = computed(() => props.holidays?.per_page ?? 20)
const totalRecords = computed(() => props.holidays?.total ?? 0)
const value = computed(() => props.holidays?.data ?? [])

function toDdMmYyyy(d) {
  if (!d) return '—'
  const dt = new Date(String(d).replace(' ', 'T'))
  if (isNaN(dt.getTime())) return String(d)
  const dd = String(dt.getDate()).padStart(2, '0')
  const mm = String(dt.getMonth() + 1).padStart(2, '0')
  const yy = dt.getFullYear()
  return `${dd}/${mm}/${yy}`
}

function destroyHoliday(id) {
  if (!confirm('Bạn có chắc muốn xoá ngày nghỉ này?')) return
  router.delete(route('admin.holidays.destroy', id), { preserveScroll: true })
}

/* -------- Filters state & actions -------- */
const state = reactive({
  scope: props.filters?.scope ?? 'all',
  branch_id: props.filters?.branch_id ? String(props.filters.branch_id) : null,
  class_id:  props.filters?.class_id  ? String(props.filters.class_id)  : null,
  perPage: props.filters?.perPage ?? (props.holidays?.per_page ?? 20),
})

function buildQuery(extra = {}) {
  const q = {}
  if (state.scope && state.scope !== 'all') q.scope = state.scope
  if (state.scope === 'branch' && state.branch_id) q.branch_id = state.branch_id
  if (state.scope === 'class'  && state.class_id)  q.class_id  = state.class_id
  if (state.perPage && state.perPage !== props.holidays?.per_page) q.per_page = state.perPage
  return Object.assign(q, extra)
}
function applyFilters() {
  router.visit(route('admin.holidays.index', buildQuery()), {
    preserveScroll: true, preserveState: true
  })
}
function onPage(e) {
  const page = Math.floor(e.first / e.rows) + 1
  router.visit(route('admin.holidays.index', buildQuery({
    per_page: e.rows, page: page > 1 ? page : undefined
  })), { preserveScroll: true, preserveState: true })
}
</script>

<template>
  <Head title="Ngày nghỉ" />

  <div class="mb-3 flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
    <h1 class="text-xl md:text-2xl font-heading font-semibold">Ngày nghỉ</h1>

    <div class="flex flex-wrap items-center gap-2">
      <!-- Scope -->
      <Select
        v-model="state.scope"
        :options="[
          {label:'Tất cả phạm vi', value:'all'},
          {label:'Toàn hệ thống',  value:'global'},
          {label:'Theo chi nhánh', value:'branch'},
          {label:'Theo lớp',       value:'class'},
        ]"
        optionLabel="label" optionValue="value"
        class="min-w-[200px]"
        @change="applyFilters"
      />

      <!-- Branch (chỉ hiện khi scope=branch) -->
      <Select
        v-if="state.scope==='branch'"
        v-model="state.branch_id"
        :options="(props.branches||[]).map(b=>({label:b.name, value:String(b.id)}))"
        optionLabel="label" optionValue="value"
        class="min-w-[220px]"
        placeholder="Chọn chi nhánh…"
        showClear
        @change="applyFilters"
      />

      <!-- Class (chỉ hiện khi scope=class) -->
      <Select
        v-if="state.scope==='class'"
        v-model="state.class_id"
        :options="(props.classes||[]).map(c=>({label:`${c.code} · ${c.name}`, value:String(c.id)}))"
        optionLabel="label" optionValue="value"
        class="min-w-[280px]"
        placeholder="Chọn lớp…"
        showClear
        @change="applyFilters"
      />

      <!-- PerPage -->
      <Select
        v-model="state.perPage"
        :options="[{label:'20 / trang',value:20},{label:'50 / trang',value:50},{label:'100 / trang',value:100}]"
        optionLabel="label" optionValue="value"
        class="w-40"
        @change="applyFilters"
      />

      <Link
        :href="route('admin.holidays.create')"
        class="px-3 py-2 rounded-lg bg-emerald-600 text-white hover:bg-emerald-700"
      >
        + Thêm ngày nghỉ
      </Link>
    </div>
  </div>

  <div class="rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 p-3">
    <DataTable
      :value="value"
      :paginator="true"
      :rows="rows"
      :totalRecords="totalRecords"
      @page="onPage"
      dataKey="id"
      size="small"
      responsiveLayout="scroll"
    >
      <Column field="id" header="#" style="width: 80px" />
      <Column field="name" header="Tên" />
      <Column header="Khoảng ngày" style="width: 240px">
        <template #body="{ data }">
          {{ toDdMmYyyy(data.start_date) }} — {{ toDdMmYyyy(data.end_date) }}
        </template>
      </Column>
      <Column field="scope" header="Phạm vi" style="width: 140px">
        <template #body="{ data }">
          <Tag :value="data.scope" severity="info" />
        </template>
      </Column>
      <Column field="recurring_yearly" header="Lặp lại" style="width: 110px">
        <template #body="{ data }">
          <i v-if="data.recurring_yearly" class="pi pi-check text-green-500" />
          <i v-else class="pi pi-times text-red-500" />
        </template>
      </Column>
      <Column header="Hành động" style="width: 170px">
        <template #body="{ data }">
          <div class="flex justify-end gap-2">
            <Link
              :href="route('admin.holidays.edit', data.id)"
              class="px-3 py-1.5 text-sm rounded border border-emerald-300 text-emerald-700 hover:bg-emerald-50
                     dark:border-emerald-700 dark:text-emerald-300 dark:hover:bg-emerald-900/20"
            >Sửa</Link>
            <button
              class="px-3 py-1.5 text-sm rounded border border-red-300 text-red-600 hover:bg-red-50
                     dark:border-red-700 dark:text-red-300 dark:hover:bg-red-900/20"
              @click="destroyHoliday(data.id)"
            >Xoá</button>
          </div>
        </template>
      </Column>

      <template #empty>
        <div class="p-4 text-center text-slate-500 dark:text-slate-400">Chưa có ngày nghỉ nào.</div>
      </template>
    </DataTable>
  </div>
</template>
