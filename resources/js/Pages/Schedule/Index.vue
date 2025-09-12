<script setup>
import { reactive, ref, computed } from 'vue'
import { Head, router, Link, usePage } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'

// PrimeVue
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import Select from 'primevue/select'
import DatePicker from 'primevue/datepicker'
import Button from 'primevue/button'
import Tag from 'primevue/tag'

defineOptions({ layout: AppLayout })

const props = defineProps({
  filters: Object,
  branches: Array,
  classes: Array,
  teachers: Array,
  sessions: Object,
})

const page = usePage()
const userRole = computed(() => page.props.auth?.user?.roles?.[0]?.name || '')

const state = reactive({
  branch_id: props.filters?.branch_id ?? null,
  class_id: props.filters?.class_id ?? null,
  teacher_id: props.filters?.teacher_id ?? null,
  from: props.filters?.from ?? '',
  to: props.filters?.to ?? '',
  order: props.filters?.order ?? 'asc',
  perPage: props.filters?.perPage ?? 20,
})

function apply(page = 1) {
  router.visit(route('schedule.index'), {
    method: 'get',
    data: {
      ...state,
      page,
    },
    preserveState: true,
    preserveScroll: true,
  })
}

function resetFilters() {
  state.branch_id = null
  state.class_id = null
  state.teacher_id = null
  state.from = ''
  state.to = ''
  state.order = 'asc'
  state.perPage = 20
  apply(1)
}

// Helper hiển thị ngày dd/mm/yyyy
function toYmdLocal(d){
  if (!d) return null
  const dt = new Date(d)
  const y = dt.getFullYear(), m = String(dt.getMonth()+1).padStart(2,'0'), day = String(dt.getDate()).padStart(2,'0')
  return `${y}-${m}-${day}`
}
</script>

<template>
  <Head title="Lịch lớp" />

  <!-- Header -->
  <div class="mb-2">
    <h1 class="text-xl md:text-2xl font-heading font-semibold">Lịch lớp</h1>
  </div>

  <!-- Filters & Actions -->
  <div class="mb-3 flex flex-col md:flex-row md:items-end md:justify-between gap-2">
    <!-- Filters: 2 rows, 3 columns each -->
    <div class="flex flex-col gap-2 w-full md:w-auto">
      <div class="flex flex-col md:flex-row gap-2">
        <div class="min-w-[180px] flex-1">
          <label class="block text-xs text-slate-500 mb-1">Chi nhánh</label>
          <Select
            v-model="state.branch_id"
            :options="[{label:'Tất cả', value:null}, ...(props.branches||[]).map(b=>({label:b.name, value:b.id}))]"
            optionLabel="label"
            optionValue="value"
            class="w-full"
            showClear
            placeholder="Tất cả"
          />
        </div>
        <div class="min-w-[180px] flex-1">
          <label class="block text-xs text-slate-500 mb-1">Lớp</label>
          <Select
            v-model="state.class_id"
            :options="[{label:'Tất cả', value:null}, ...(props.classes||[]).map(c=>({label:`${c.code} · ${c.name}`, value:c.id}))]"
            optionLabel="label"
            optionValue="value"
            class="w-full"
            showClear
            placeholder="Tất cả"
          />
        </div>
        <div v-if="userRole === 'admin' || userRole === 'manager'" class="min-w-[180px] flex-1">
          <label class="block text-xs text-slate-500 mb-1">Giáo viên</label>
          <Select
            v-model="state.teacher_id"
            :options="[{label:'Tất cả', value:null}, ...(props.teachers||[]).map(t=>({label:t.name, value:t.id}))]"
            optionLabel="label"
            optionValue="value"
            class="w-full"
            showClear
            placeholder="Tất cả"
          />
        </div>
      </div>
      <div class="flex flex-col md:flex-row gap-2">
        <div class="min-w-[180px] flex-1">
          <label class="block text-xs text-slate-500 mb-1">Từ ngày</label>
          <DatePicker v-model="state.from" dateFormat="yy-mm-dd" showIcon iconDisplay="input" class="w-full" />
        </div>
        <div class="min-w-[180px] flex-1">
          <label class="block text-xs text-slate-500 mb-1">Đến ngày</label>
          <DatePicker v-model="state.to" dateFormat="yy-mm-dd" showIcon iconDisplay="input" class="w-full" />
        </div>
        <div class="min-w-[180px] flex-1">
          <label class="block text-xs text-slate-500 mb-1">Sắp xếp</label>
          <Select
            v-model="state.order"
            :options="[{label:'Tăng dần',value:'asc'},{label:'Giảm dần',value:'desc'}]"
            optionLabel="label"
            optionValue="value"
            class="w-full"
          />
        </div>
      </div>
    </div>
    <!-- Actions -->
    <div class="flex gap-2 mt-2 md:mt-0 justify-end">
      <Button label="Lọc" icon="pi pi-filter" @click="apply()" />
      <Button label="Xóa lọc" icon="pi pi-times" severity="warn" @click="resetFilters" outlined />
      <Link :href="route('schedule.week')" class="p-button p-component p-button-info !px-4 !py-2 rounded-md flex items-center gap-2">
        <i class="pi pi-calendar"></i>
        <span>Lịch theo tuần</span>
      </Link>
    </div>
  </div>

  <!-- Table -->
  <DataTable
    :value="props.sessions.data"
    :paginator="true"
    :rows="props.sessions.per_page"
    :totalRecords="props.sessions.total"
    :first="(props.sessions.current_page - 1) * props.sessions.per_page"
    :lazy="true"
    responsiveLayout="scroll"
    class="shadow-sm"
    @page="e => apply(e.page + 1)"
  >
    <Column header="Ngày" style="width: 160px">
      <template #body="{ data }">{{ toYmdLocal(data.date) }}</template>
    </Column>
    <Column header="Giờ" style="width: 160px">
      <template #body="{ data }">{{ data.start_time }}–{{ data.end_time }}</template>
    </Column>
    <Column header="Lớp">
      <template #body="{ data }">
        <span v-if="data.classroom">{{ data.classroom.code }} · {{ data.classroom.name }}</span>
      </template>
    </Column>
    <Column header="Phòng">
      <template #body="{ data }">
        <span v-if="data.room">{{ data.room.name }}</span>
        <span v-else>—</span>
      </template>
    </Column>
    <Column header="Giáo viên" style="width: 260px">
      <template #body="{ data }">
        <span v-if="data.substitute">
          {{ data.substitute.name }}
          <Tag value="Dạy thay" severity="warning" class="mr-2" />
        </span>
        <span v-else class="text-slate-500">Theo phân công</span>
      </template>
    </Column>
    <Column header="Trạng thái" style="width: 120px">
      <template #body="{ data }">
        <Tag :value="data.status" :severity="data.status === 'planned' ? 'info' : (data.status === 'moved' ? 'warning' : 'danger')" />
      </template>
    </Column>
  </DataTable>
</template>
