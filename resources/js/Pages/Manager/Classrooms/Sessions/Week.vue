<script setup>
import { reactive, computed } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import { usePageToast } from '@/composables/usePageToast'

// PrimeVue
import Select from 'primevue/select'
import Button from 'primevue/button'
import Tag from 'primevue/tag'

defineOptions({ layout: AppLayout })

const props = defineProps({
  classroom: Object,      // {id,code,name}
  filters: Object,        // {date, room_id}
  week: Object,           // {start, end, days:[]}
  rooms: Array,           // [{id,code,name,label,value}]
  sessionsByDay: Object,  // { '2025-08-18': [ {id,date,start_time,end_time,...}, ...] }
})

const { showError } = usePageToast()

/* ---- Local state ---- */
const state = reactive({
  roomId: props.filters?.room_id || null,
  refDate: props.filters?.date || new Date().toISOString().slice(0,10),
})

/* ---- Helpers ---- */
function applyFilters() {
  router.get(route('manager.classrooms.sessions.week', { classroom: props.classroom.id }), {
    date: state.refDate,
    room_id: state.roomId,
  }, {
    preserveScroll: true,
    preserveState: true,
    replace: true,
    onError: () => {
      showError('Lỗi tải dữ liệu', 'Không thể tải lịch tuần')
    }
  })
}

function prevWeek() {
  const d = new Date(props.week.start)
  d.setDate(d.getDate() - 7)
  state.refDate = d.toISOString().slice(0,10)
  applyFilters()
}

function nextWeek() {
  const d = new Date(props.week.start)
  d.setDate(d.getDate() + 7)
  state.refDate = d.toISOString().slice(0,10)
  applyFilters()
}

const weekdays = ['CN','T2','T3','T4','T5','T6','T7']

/* Format time hiển thị */
function fmt(t) {
  if (!t) return ''
  return t.slice(0,5) // HH:mm:ss -> HH:mm
}
</script>

<template>
  <Head :title="`Lịch tuần - ${classroom.name}`" />

  <!-- Header -->
  <div class="mb-3 flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
    <h1 class="text-xl md:text-2xl font-heading font-semibold">
      Lịch tuần: {{ classroom.name }}
    </h1>

    <div class="flex flex-wrap gap-2 items-center">
      <Button label="← Tuần trước" size="small" outlined @click="prevWeek" />
      <span class="px-2 text-slate-600 dark:text-slate-300">
        {{ week.start }} → {{ week.end }}
      </span>
      <Button label="Tuần sau →" size="small" outlined @click="nextWeek" />

      <Select
        v-model="state.roomId"
        :options="[{label:'Tất cả phòng', value:null}, ...(rooms||[])]"
        optionLabel="label" optionValue="value"
        class="w-48"
        @change="applyFilters"
      />

      <Link
        :href="route('manager.classrooms.sessions.index', { classroom: classroom.id })"
        class="px-3 py-1.5 rounded border border-slate-300 dark:border-slate-600 hover:bg-slate-100 dark:hover:bg-slate-800"
      >
        Danh sách buổi
      </Link>
    </div>
  </div>

  <!-- Week grid -->
  <div class="overflow-x-auto">
    <div class="grid grid-cols-7 border border-slate-300 dark:border-slate-600">
      <div v-for="(day,idx) in week.days" :key="day" class="border-l border-slate-200 dark:border-slate-700 min-w-[200px]">
        <div class="px-2 py-1 bg-slate-100 dark:bg-slate-700 text-sm font-semibold sticky top-0">
          {{ weekdays[idx] }} ({{ day }})
        </div>

        <div class="p-2 space-y-2">
          <div
            v-for="s in sessionsByDay[day] || []"
            :key="s.id"
            class="border rounded p-2 text-sm bg-white dark:bg-slate-800"
          >
            <div><b>{{ fmt(s.start_time) }} - {{ fmt(s.end_time) }}</b></div>
            <div>{{ s.room?.code }} - {{ s.room?.name }}</div>
            <div class="flex justify-between items-center">
              <Tag :value="s.status" />
              <Link
                :href="route('manager.classrooms.sessions.index', { classroom: classroom.id }) + '?focus='+s.id"
                class="text-emerald-600 hover:underline text-xs"
              >Chi tiết</Link>
            </div>
          </div>
          <div v-if="!(sessionsByDay[day] && sessionsByDay[day].length)" class="text-slate-400 text-sm italic">
            — Trống —
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
