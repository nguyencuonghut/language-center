<script setup>
import { reactive, ref, computed, watch } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'

// PrimeVue
import Select from 'primevue/select'
import DatePicker from 'primevue/datepicker'
import Button from 'primevue/button'
import Tag from 'primevue/tag'

defineOptions({ layout: AppLayout })

const props = defineProps({
  filters: Object,          // {branch_id,class_id,teacher_id,week_start}
  week: Object,             // {start,end,days:[{iso,label,items:[]}]}
  branches: Array,
  classes: Array,
  teachers: Array,
})

const state = reactive({
  branch_id: props.filters?.branch_id ? String(props.filters.branch_id) : null,
  class_id:  props.filters?.class_id ? String(props.filters.class_id)   : null,
  teacher_id:props.filters?.teacher_id ? String(props.filters.teacher_id) : null,
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
          :options="[{label:'Tất cả', value:null}, ...(props.branches||[]).map(b=>({label:b.name, value:String(b.id)}))]"
          optionLabel="label" optionValue="value" class="w-full" showClear
          @change="apply"
        />
      </div>

      <div class="min-w-[260px]">
        <label class="block text-xs text-slate-500 mb-1">Lớp</label>
        <Select
          v-model="state.class_id"
          :options="[{label:'Tất cả', value:null}, ...(props.classes||[]).map(c=>({label:`${c.code} · ${c.name}`, value:String(c.id)}))]"
          optionLabel="label" optionValue="value" class="w-full" showClear
          @change="apply"
        />
      </div>

      <div class="min-w-[220px]">
        <label class="block text-xs text-slate-500 mb-1">Giáo viên</label>
        <Select
          v-model="state.teacher_id"
          :options="[{label:'Tất cả', value:null}, ...(props.teachers||[]).map(t=>({label:t.name, value:String(t.id)}))]"
          optionLabel="label" optionValue="value" class="w-full" showClear
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
          :href="route('manager.schedule.index', { branch_id: state.branch_id, class_id: state.class_id, teacher_id: state.teacher_id, from: week.start, to: week.end })"
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
          class="rounded border border-slate-200 dark:border-slate-600 p-2"
        >
          <div class="text-xs flex items-center justify-between text-slate-500">{{ s.start_time }}–{{ s.end_time }}
            <Tag
              v-if="s.substitute"
              value="Dạy thay"
              severity="warn"
              class="ml-2"
            />
          </div>
          <div class="text-sm font-medium truncate">
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
</template>
