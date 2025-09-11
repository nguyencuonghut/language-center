<script setup>
import { reactive, ref, computed } from 'vue'
import { Head, router } from '@inertiajs/vue3'
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
  router.visit(route('manager.schedule.index'), {
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

// Helper hiển thị ngày dd/mm
function toYmdLocal(d){
  if (!d) return null
  const dt = new Date(d)
  const y = dt.getFullYear(), m = String(dt.getMonth()+1).padStart(2,'0'), day = String(dt.getDate()).padStart(2,'0')
  return `${y}-${m}-${day}`
}
</script>

<template>
  <Head title="Lịch lớp (Manager)" />

  <!-- Header -->
  <div class="mb-2">
    <h1 class="text-xl md:text-2xl font-heading font-semibold">Lịch lớp</h1>
    <p class="text-slate-500 dark:text-slate-400 text-sm">Dạng danh sách theo khoảng thời gian</p>
  </div>

  <!-- Filters -->
  <div class="mb-3 flex flex-wrap items-end gap-2">
    <!-- Branch -->
    <div class="min-w-[220px]">
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

    <!-- Class -->
    <div class="min-w-[260px]">
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

    <!-- Teacher -->
    <div class="min-w-[220px]">
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

    <!-- From -->
    <div>
      <label class="block text-xs text-slate-500 mb-1">Từ ngày</label>
      <DatePicker v-model="state.from" dateFormat="yy-mm-dd" showIcon iconDisplay="input" />
    </div>
    <!-- To -->
    <div>
      <label class="block text-xs text-slate-500 mb-1">Đến ngày</label>
      <DatePicker v-model="state.to" dateFormat="yy-mm-dd" showIcon iconDisplay="input" />
    </div>

    <!-- Order -->
    <div>
      <label class="block text-xs text-slate-500 mb-1">Sắp xếp</label>
      <Select
        v-model="state.order"
        :options="[{label:'Tăng dần',value:'asc'},{label:'Giảm dần',value:'desc'}]"
        optionLabel="label"
        optionValue="value"
      />
    </div>

    <div>
      <Button label="Lọc" icon="pi pi-filter" @click="apply()" />
    </div>
    <div>
      <Button label="Xóa lọc" icon="pi pi-times" severity="secondary" @click="resetFilters" outlined />
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
