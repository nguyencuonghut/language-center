<script setup>
import { reactive, ref } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import SessionSubstituteDialog from '@/Pages/Manager/Classrooms/Sessions/Partials/SessionSubstituteDialog.vue'


// PrimeVue
import Select from 'primevue/select'
import DatePicker from 'primevue/datepicker'
import Button from 'primevue/button'
import Tag from 'primevue/tag'
import Drawer from 'primevue/drawer'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'

defineOptions({ layout: AppLayout })

const props = defineProps({
  filters: Object,          // {branch_id,class_id,teacher_id,week_start}
  week: Object,             // {start,end,days:[{iso,label,items:[]}]}
  branches: Array,
  classes: Array,
  teachers: Array,
})

const state = reactive({
  branch_id: props.filters?.branch_id ?? null,
  class_id: props.filters?.class_id ?? null,
  teacher_id: props.filters?.teacher_id ?? null,
  week_start: props.filters?.week_start ? new Date(props.filters.week_start+'T00:00:00') : new Date(),
})

function toYmdLocal(d){
  if (!d) return null
  const dt = new Date(d)
  const y = dt.getFullYear()
  const m = String(dt.getMonth()+1).padStart(2,'0')
  const day = String(dt.getDate()).padStart(2,'0')
  return `${y}-${m}-${day}`
}
function statusSeverity(s){
  switch (s) {
    case 'planned': return 'info'
    case 'moved':   return 'warning'
    case 'canceled':return 'danger'
    default:        return 'info'
  }
}

function apply() {
  const q = {}
  if (state.branch_id) q.branch_id = state.branch_id
  if (state.class_id)  q.class_id  = state.class_id
  if (state.teacher_id) q.teacher_id = state.teacher_id
  if (state.week_start) q.week_start = toYmdLocal(state.week_start)
  router.visit(route('manager.schedule.week', q), {
    preserveScroll: true,
    preserveState: true,
  })
}

function shiftWeek(delta){
  // delta = -7 hoặc +7 ngày
  const cur = state.week_start ? new Date(state.week_start) : new Date()
  cur.setDate(cur.getDate() + delta)
  state.week_start = cur
  apply()
}

/* ================= Drawer chi tiết buổi ================= */
const showDrawer = ref(false)
const drawerLoading = ref(false)
const detail = reactive({
  session: null,        // {id,date,start_time,end_time,status,classroom,room,teacher_names,substitute,...}
  enrollments: [],      // [{student:{code,name}, ...}]
  conflicts: { room: [], teacher: [] }, // {message:string}[]
})

function hhmm(t){ return t ? String(t).slice(0,5) : '—' }

async function openDetail(item) {
  // Mở Drawer ngay với dữ liệu sẵn có (fallback)
  showDrawer.value = true
  drawerLoading.value = true
  detail.teachers = []
  detail.substitutes = []
  detail.session = []
  detail.enrollments = []
  detail.conflicts = { room: [], teacher: [] }

  try {
    // Gọi API meta để lấy đủ dữ liệu (nếu bạn đã làm endpoint)
    // Đổi route name theo BE của bạn:
    const url = route('manager.schedule.session.meta', { session: item.id })
    const res = await fetch(url, {
      headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
    })
    if (res.ok) {
      const data = await res.json()
      // Kỳ vọng payload: { session: {...}, enrollments: [...], conflicts: {room:[],teacher:[]} }
      if (data?.session) detail.session = data.session
      if (Array.isArray(data?.enrollments)) detail.enrollments = data.enrollments
      if (Array.isArray(data?.teachers)) detail.teachers = data.teachers
      if (Array.isArray(data?.substitutes)) detail.substitutes = data.substitutes
      if (Array.isArray(data?.room)) detail.room = data.room
      if (data?.conflicts) detail.conflicts = data.conflicts
    }
  } catch (e) {
    // giữ fallback
  } finally {
    drawerLoading.value = false
  }
}

/* Quick actions: tuỳ dự án bạn có route nào thì thay cho hợp lý */
function goSessionListOfClass() {
  const clsId = detail.session?.classroom?.id
  if (!clsId) return
  router.visit(route('manager.classrooms.sessions.index', { classroom: clsId }))
}
function goClassDetail() {
  const clsId = detail.session?.classroom?.id
  if (!clsId) return
  router.visit(route('manager.classrooms.edit', { classroom: clsId }))
}

// State for substitute dialog
const showSubstituteDialog = ref(false)
const substituteSessionId = ref(null)
const substituteClassroomId = ref('')

// Mở dialog gán dạy thay
function goAssignSubstitute() {
  const sessId = detail.session?.id
  const classID = detail.session?.classroom?.id
  if (!sessId || !classID) return
  substituteSessionId.value = sessId
  substituteClassroomId.value = classID
  showSubstituteDialog.value = true
}
</script>

<template>
  <Head title="Lịch theo tuần (Manager)" />

  <!-- Header -->
  <div class="mb-3 flex flex-col gap-3 md:flex-row md:items-end md:justify-between">
    <div>
      <h1 class="text-xl md:text-2xl font-heading font-semibold">Lịch theo tuần</h1>
      <div class="text-slate-500 dark:text-slate-400 text-sm">
        {{ week.start }} — {{ week.end }}
      </div>
    </div>

    <div class="flex flex-wrap items-end gap-2">
      <div class="min-w-[220px]">
        <label class="block text-xs text-slate-500 mb-1">Chi nhánh</label>
        <Select
          v-model="state.branch_id"
          :options="[{label:'Tất cả', value:null}, ...(props.branches||[]).map(b=>({label:b.name, value:b.id}))]"
          optionLabel="label"
          optionValue="value"
          placeholder="Tất cả"
          class="w-full"
          showClear
          @change="apply"
        />
      </div>

      <div class="min-w-[260px]">
        <label class="block text-xs text-slate-500 mb-1">Lớp</label>
        <Select
          v-model="state.class_id"
          :options="[{label:'Tất cả', value:null}, ...(props.classes||[]).map(c=>({label:`${c.code} · ${c.name}`, value:String(c.id)}))]"
          optionLabel="label"
          optionValue="value"
          placeholder="Tất cả"
          class="w-full"
          showClear
          @change="apply"
        />
      </div>

      <div class="min-w-[220px]">
        <label class="block text-xs text-slate-500 mb-1">Giáo viên</label>
        <Select
          v-model="state.teacher_id"
          :options="[{label:'Tất cả', value:null}, ...(props.teachers||[]).map(t=>({label:t.name, value:String(t.id)}))]"
          optionLabel="label"
          optionValue="value"
          placeholder="Tất cả"
          class="w-full"
          showClear
          @change="apply"
        />
      </div>

      <div>
        <label class="block text-xs text-slate-500 mb-1">Chọn tuần</label>
        <DatePicker v-model="state.week_start" dateFormat="yy-mm-dd" showIcon iconDisplay="input" @date-select="apply" />
      </div>

      <div class="flex items-center gap-2">
        <Button icon="pi pi-angle-left" text @click="shiftWeek(-7)" :aria-label="'Tuần trước'" />
        <Button icon="pi pi-angle-right" text @click="shiftWeek(+7)" :aria-label="'Tuần sau'" />
        <Link
          :href="route('manager.schedule.index')"
          class="px-3 py-1.5 rounded-lg border border-slate-300 dark:border-slate-600 hover:bg-slate-100 dark:hover:bg-slate-800"
        >
          Lịch lớp
        </Link>
      </div>
    </div>
  </div>

  <!-- Week grid -->
  <div class="grid grid-cols-1 md:grid-cols-7 gap-2">
    <div
      v-for="d in week.days"
      :key="d.iso"
      class="rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 p-2 min-h-[200px] flex flex-col"
    >
      <div class="text-sm font-medium mb-2">{{ d.label }}</div>

      <div class="flex-1 flex flex-col gap-2">
        <div
          v-for="s in d.items"
          :key="s.id"
          class="rounded border border-slate-200 dark:border-slate-600 p-2 hover:border-emerald-400 cursor-pointer transition-colors"
          @click="openDetail(s)"
        >
          <div class="text-xs flex items-center justify-between text-slate-500">
            <span>{{ s.start_time }}–{{ s.end_time }}</span>
            <Tag
              v-if="s.substitution"
              value="Dạy thay"
              severity="warn"
              class="ml-2"
            />
          </div>
          <div class="text-sm font-medium truncate mt-0.5">
            {{ s.classroom?.code }} · {{ s.classroom?.name }}
          </div>
          <div class="text-xs mt-1 flex items-center justify-between">
            <span class="truncate">
              <span v-if="s.room">{{ s.room.code ? (s.room.code + ' · ') : '' }}{{ s.room.name }}</span>
              <span v-else class="text-slate-400">Chưa gán phòng</span>
            </span>
            <Tag :value="s.status" :severity="statusSeverity(s.status)" />
          </div>
        </div>

        <div v-if="!d.items?.length" class="text-xs text-slate-400 text-center py-6">— Không có buổi —</div>
      </div>
    </div>
  </div>

  <!-- Drawer chi tiết -->
  <Drawer v-model:visible="showDrawer" position="right" :modal="true" class="!w-full md:!w-[520px]">
    <template #header>
      <div class="font-semibold">Chi tiết buổi học</div>
    </template>

    <div v-if="drawerLoading" class="p-4 text-slate-500">Đang tải...</div>

    <div v-else-if="detail.session" class="p-3 space-y-4">
      <!-- Thông tin buổi -->
      <div class="rounded border border-slate-200 dark:border-slate-700 p-3">
        <div class="flex justify-between items-center">
          <div class="font-medium">
            {{ detail.session.classroom?.code }} — {{ detail.session.classroom?.name }}
          </div>
          <Tag :value="detail.session.status" :severity="statusSeverity(detail.session.status)" />
        </div>
        <div class="text-sm mt-1">
          {{ toYmdLocal(detail.session.date) }} • {{ hhmm(detail.session.start_time) }}–{{ hhmm(detail.session.end_time) }}
        </div>
        <div v-if="detail.session.room"class="text-sm mt-1">Phòng: {{ detail.session.room?.code + ' · ' + detail.session.room?.name}}</div>
        <div v-else class="text-sm mt-1">Phòng: chưa gán</div>
        <div class="text-sm mt-1">GV:
            <span v-if="detail.session.substitution">
                {{ detail.session.substitution.name }}
                <Tag value="Dạy thay" severity="warn" class="ml-2" />
            </span>
            <span v-else-if="detail.teachers && detail.teachers.length">
                {{ detail.teachers.map(t => t.name).join(', ') }}
            </span>
            <span v-else>—</span>
        </div>
      </div>

      <!-- Xung đột -->
      <div class="rounded border border-slate-200 dark:border-slate-700 p-3">
        <div class="font-medium mb-1">Xung đột</div>
        <div v-if="!(detail.conflicts.room.length || detail.conflicts.teacher.length)" class="text-sm text-slate-500">
          Không có
        </div>
        <ul v-else class="list-disc pl-5 text-sm space-y-1">
          <li v-for="(c,i) in detail.conflicts.room" :key="'r'+i">{{ c.message }}</li>
          <li v-for="(c,i) in detail.conflicts.teacher" :key="'t'+i">{{ c.message }}</li>
        </ul>
      </div>

      <!-- Học viên -->
      <div class="rounded border border-slate-200 dark:border-slate-700 p-3">
        <div class="font-medium mb-2">Học viên</div>
        <DataTable :paginator="true"
               :rows="10"
               :value="detail.enrollments"
               size="small" responsiveLayout="scroll"
        >
          <Column header="#" style="width:40px">
            <template #body="{ index }">{{ index+1 }}</template>
          </Column>
          <Column header="Mã">
            <template #body="{ data }">{{ data.code ?? '—' }}</template>
          </Column>
          <Column header="Tên">
            <template #body="{ data }">{{ data.name ?? '—' }}</template>
          </Column>
        </DataTable>
      </div>

      <!-- Hành động nhanh -->
      <div class="rounded border border-slate-200 dark:border-slate-700 p-3">
        <div class="font-medium mb-2">Hành động</div>
        <div class="flex flex-wrap gap-2">
          <Button label="Mở danh sách buổi của lớp" icon="pi pi-list" @click="goSessionListOfClass" />
          <Button label="Chi tiết lớp" icon="pi pi-external-link" severity="secondary" @click="goClassDetail" />
          <Button label="Gán dạy thay" icon="pi pi-user-plus" severity="help" @click="goAssignSubstitute" />
        </div>
      </div>
    </div>
  </Drawer>

  <!-- Modal tạo dạy thay -->
  <SessionSubstituteDialog
    :visible="showSubstituteDialog"
    :classroom-id="substituteClassroomId"
    :session-id="substituteSessionId"
    :teachers="detail.substitutes"
    @update:visible="showSubstituteDialog = $event"
  />
</template>
