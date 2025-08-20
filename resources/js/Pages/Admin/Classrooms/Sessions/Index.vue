<script setup>
import { reactive, computed, ref } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'

// PrimeVue
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import Select from 'primevue/select'
import Button from 'primevue/button'
import Tag from 'primevue/tag'
import InputText from 'primevue/inputtext'
import Textarea from 'primevue/textarea'
import DatePicker from 'primevue/datepicker' // PrimeVue v4

defineOptions({ layout: AppLayout })

const props = defineProps({
  classroom: Object,   // {id, code, name, branch_id}
  sessions: Object,    // LengthAwarePaginator
  rooms: Array,        // [{id,code,name,label,value}]
  filters: Object,     // {sort, order, perPage}
})


/* ---------- State: filter/sort/pagination ---------- */
const state = reactive({
  perPage: props.filters?.perPage ?? (props.sessions?.per_page ?? 20),
})
const sortField = ref(props.filters?.sort || 'date')
const sortOrder = ref(props.filters?.order === 'desc' ? -1 : 1)

function toHHmm(t) {
  if (!t) return ''
  // t có thể là '08:00:00' hoặc '08:00' → luôn trả '08:00'
  return String(t).slice(0,5)
}

/* ---------- Helpers gọi lại list ---------- */
function buildQuery(extra = {}) {
  const q = {}
  if (state.perPage && state.perPage !== props.sessions?.per_page) q.per_page = state.perPage
  if (sortField.value) q.sort = sortField.value
  if (sortOrder.value !== null) q.order = sortOrder.value === 1 ? 'asc' : 'desc'
  Object.assign(q, extra)
  return q
}
function applyFilters() {
  router.visit(route('admin.classrooms.sessions.index', { classroom: props.classroom.id, ...buildQuery() }), {
    preserveScroll: true,
    preserveState: true
  })
}
function onPage(event) {
  const page = Math.floor(event.first / event.rows) + 1
  router.visit(route('admin.classrooms.sessions.index', { classroom: props.classroom.id, ...buildQuery({
    per_page: event.rows,
    page: page > 1 ? page : undefined
  })}), {
    preserveScroll: true,
    preserveState: true
  })
}
function onSort(event) {
  sortField.value = event.sortField
  sortOrder.value = event.sortOrder
  applyFilters()
}

/* ---------- DataTable computed ---------- */
const value = computed(() => props.sessions?.data ?? [])
const totalRecords = computed(() => props.sessions?.total ?? value.value.length)
const rows = computed(() => props.sessions?.per_page ?? 20)
const first = computed(() => Math.max(0, (props.sessions?.from ?? 1) - 1))

/* ---------- Tuỳ chọn hiển thị ---------- */
const weekdays = ['CN','T2','T3','T4','T5','T6','T7']
const statusOptions = [
  { label: 'Kế hoạch', value: 'planned' },
  { label: 'Đã dạy', value: 'taught' },
  { label: 'Huỷ', value: 'cancelled' }
]

/* ---------- Row edit model ---------- */
const editing = reactive({}) // key theo session.id

function startEdit(row) {
  const id = row.id
  const dateObj = new Date(row.date + 'T00:00:00')
  editing[id] = {
    date: isNaN(dateObj.getTime()) ? null : dateObj,
    start_time: toHHmm(row.start_time),
    end_time: toHHmm(row.end_time),
    room_id: row.room_id ? String(row.room_id) : null,
    status: row.status,
    note: row.note ?? '',
    saving: false,
    errors: {}
  }
}
function cancelEdit(id) {
  delete editing[id]
}
function isEditing(id) {
  return !!editing[id]
}

/* ---------- Validate tối thiểu phía FE ---------- */
function isTime(hhmm) {
  return /^\d{2}:\d{2}$/.test(hhmm)
}

/* ---------- Save row ---------- */
function saveRow(row) {
  const id = row.id
  const model = editing[id]
  if (!model) return

  model.errors = {}
  if (!model.date) model.errors.date = 'Vui lòng chọn ngày'
  if (!isTime(model.start_time)) model.errors.start_time = 'Định dạng HH:mm'
  if (!isTime(model.end_time)) model.errors.end_time = 'Định dạng HH:mm'

  if (Object.keys(model.errors).length) return

  model.saving = true
  router.put(route('admin.classrooms.sessions.update', { classroom: props.classroom.id, session: id }), {
    date: model.date ? new Date(model.date).toISOString().slice(0,10) : null,
    start_time: toHHmm(model.start_time),
    end_time: toHHmm(model.end_time),
    room_id: model.room_id ? Number(model.room_id) : null,
    status: model.status,
    note: model.note ?? null,
  }, {
    preserveScroll: true,
    onFinish: () => { model.saving = false },
    onSuccess: () => {
      // reload lại row hiển thị từ props.sessions khi Inertia re-render
      delete editing[id]
    },
    onError: (errors) => {
      model.errors = errors || {}
      // Ví dụ trùng phòng backend trả về errors.room_id
    }
  })
}
</script>

<template>
  <Head :title="`Buổi học - ${classroom.name}`" />

  <!-- Header -->
  <div class="mb-3 flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
    <div>
      <h1 class="text-xl md:text-2xl font-heading font-semibold">Buổi học</h1>
      <div class="text-slate-500 dark:text-slate-400 text-sm">
        Lớp: <span class="font-medium text-slate-900 dark:text-slate-100">{{ classroom.name }}</span>
      </div>
    </div>

    <div class="flex items-center gap-2">
      <Link
        :href="route('admin.classrooms.schedules.index', { classroom: classroom.id })"
        class="px-3 py-1.5 rounded-lg border border-slate-300 dark:border-slate-600 hover:bg-slate-100 dark:hover:bg-slate-800"
      >
        ← Lịch tuần
      </Link>

      <Select
        v-model="state.perPage"
        :options="[{label:'20 / trang',value:20},{label:'50 / trang',value:50},{label:'100 / trang',value:100}]"
        optionLabel="label" optionValue="value"
        class="w-40"
        @change="applyFilters"
      />
    </div>
  </div>

  <!-- Bảng buổi học -->
  <div class="rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 p-2">
    <DataTable
      :value="value"
      :paginator="true"
      :rows="rows"
      :totalRecords="totalRecords"
      :first="first"
      :sortField="sortField"
      :sortOrder="sortOrder"
      lazy
      @page="onPage"
      @sort="onSort"
      dataKey="id"
      responsiveLayout="scroll"
      size="small"
    >
      <Column field="session_no" header="#" style="width: 80px" :sortable="true" />

      <Column field="date" header="Ngày" style="width: 160px" :sortable="true">
        <template #body="{ data }">
          <div v-if="isEditing(data.id)" class="flex items-center gap-2">
            <DatePicker v-model="editing[data.id].date" dateFormat="yy-mm-dd" showIcon iconDisplay="input" />
          </div>
          <div v-else>{{ data.date }}</div>
          <div v-if="editing[data.id]?.errors?.date" class="text-red-500 text-xs mt-1">
            {{ editing[data.id].errors.date }}
          </div>
        </template>
      </Column>

      <Column field="start_time" header="Bắt đầu" style="width: 120px" :sortable="true">
        <template #body="{ data }">
          <div v-if="isEditing(data.id)">
            <InputText v-model="editing[data.id].start_time" placeholder="HH:mm" class="w-24" />
            <div v-if="editing[data.id]?.errors?.start_time" class="text-red-500 text-xs mt-1">
              {{ editing[data.id].errors.start_time }}
            </div>
          </div>
          <div v-else>{{ toHHmm(data.start_time) }}</div>
        </template>
      </Column>

      <Column field="end_time" header="Kết thúc" style="width: 120px" :sortable="true">
        <template #body="{ data }">
          <div v-if="isEditing(data.id)">
            <InputText v-model="editing[data.id].end_time" placeholder="HH:mm" class="w-24" />
            <div v-if="editing[data.id]?.errors?.end_time" class="text-red-500 text-xs mt-1">
              {{ editing[data.id].errors.end_time }}
            </div>
          </div>
          <div v-else>{{ toHHmm(data.end_time) }}</div>
        </template>
      </Column>

      <Column header="Phòng" style="width: 220px">
        <template #body="{ data }">
          <div v-if="isEditing(data.id)">
            <Select
              v-model="editing[data.id].room_id"
              :options="(props.rooms || []).map(r => ({label: r.label, value: String(r.id)}))"
              optionLabel="label"
              optionValue="value"
              class="min-w-[200px]"
              placeholder="Chọn phòng…"
              showClear
            />
            <div v-if="editing[data.id]?.errors?.room_id" class="text-red-500 text-xs mt-1">
              {{ editing[data.id].errors.room_id }}
            </div>
          </div>
          <div v-else>
            <span v-if="data.room">{{ data.room.code ? (data.room.code + ' - ') : '' }}{{ data.room.name }}</span>
            <span v-else class="text-slate-400">—</span>
          </div>
        </template>
      </Column>

      <Column field="status" header="Trạng thái" style="width: 160px" :sortable="true">
        <template #body="{ data }">
          <div v-if="isEditing(data.id)">
            <Select
              v-model="editing[data.id].status"
              :options="statusOptions"
              optionLabel="label"
              optionValue="value"
              class="w-40"
            />
          </div>
          <div v-else>
            <Tag :value="data.status"
                 :severity="data.status==='planned' ? 'info' : data.status==='taught' ? 'success' : 'danger'" />
          </div>
        </template>
      </Column>

      <Column header="Ghi chú">
        <template #body="{ data }">
          <div v-if="isEditing(data.id)">
            <Textarea v-model="editing[data.id].note" autoResize rows="1" class="w-full" />
          </div>
          <div v-else class="truncate max-w-[360px]">{{ data.note || '—' }}</div>
        </template>
      </Column>

      <Column header="" style="width: 220px">
        <template #body="{ data }">
          <div class="flex justify-end gap-2">
            <template v-if="!isEditing(data.id)">
              <Button label="Sửa" icon="pi pi-pencil" text @click="startEdit(data)" />
            </template>
            <template v-else>
              <Button
                label="Huỷ"
                icon="pi pi-times"
                text
                :disabled="editing[data.id].saving"
                @click="cancelEdit(data.id)"
              />
              <Button
                label="Lưu"
                icon="pi pi-check"
                :loading="editing[data.id].saving"
                @click="saveRow(data)"
              />
            </template>
          </div>
        </template>
      </Column>

      <template #empty>
        <div class="p-6 text-center text-slate-500 dark:text-slate-400">Chưa có buổi nào.</div>
      </template>
    </DataTable>
  </div>
</template>
