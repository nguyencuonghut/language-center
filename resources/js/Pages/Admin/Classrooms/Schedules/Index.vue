<script setup>
import { reactive, computed, ref } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import { createClassScheduleService } from '@/service/ClassScheduleService'
import { usePageToast } from '@/composables/usePageToast'

// PrimeVue
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import Select from 'primevue/select'
import Button from 'primevue/button'
import Tag from 'primevue/tag'
import Dialog from 'primevue/dialog'
import InputText from 'primevue/inputtext'
import Checkbox from 'primevue/checkbox'
import DatePicker from 'primevue/datepicker' // PrimeVue v4

defineOptions({ layout: AppLayout })

const props = defineProps({
  classroom: Object,   // {id,code,name,branch_id}
  schedules: Object,   // paginator
  filters: Object,     // {sort, order, perPage}
})

/* (giữ lại service cho list/delete) */
const { showSuccess, showError } = usePageToast()
const scheduleService = createClassScheduleService({ showSuccess, showError })

/* -------------------------------------------------
   Dialog "Phát sinh buổi"
-------------------------------------------------- */
const showDialog = ref(false)
const form = reactive({
  from_date: null,     // Date | null
  max_sessions: null,  // number | null
  reset: false,        // boolean
})

function openGenerateDialog() {
  showDialog.value = true
}

function submitGenerate() {
  router.post(
    route('admin.classrooms.sessions.generate', { classroom: props.classroom.id }),
    {
      from_date: form.from_date instanceof Date
        ? new Date(form.from_date).toISOString().slice(0, 10)
        : null,
      max_sessions: form.max_sessions || null,
      reset: !!form.reset,
    },
    {
      preserveScroll: true,
      onSuccess: () => {
        // KHÔNG show toast tại FE để tránh trùng với flash từ backend
        showDialog.value = false
      },
      // onError: để layout hiển thị flash error (nếu có), tránh showError ở đây để không bị đôi
    }
  )
}

/* -------------------------------------------------
   Local UI state / sorting / filters
-------------------------------------------------- */
const state = reactive({
  perPage: props.filters?.perPage ?? (props.schedules?.per_page ?? 12),
})

const sortField = ref(props.filters?.sort || null)
const sortOrder = ref(
  props.filters?.order === 'asc' ? 1 :
  props.filters?.order === 'desc' ? -1 : null
)

function applyFilters() {
  const query = {}
  if (state.perPage && state.perPage !== props.schedules?.per_page) query.per_page = state.perPage
  if (sortField.value) query.sort = sortField.value
  if (sortOrder.value !== null) query.order = sortOrder.value === 1 ? 'asc' : 'desc'
  scheduleService.getList(props.classroom.id, query)
}

function onPage(event) {
  const page = Math.floor(event.first / event.rows) + 1
  const query = {}
  if (event.rows) query.per_page = event.rows
  if (page > 1) query.page = page
  if (sortField.value) query.sort = sortField.value
  if (sortOrder.value !== null) query.order = sortOrder.value === 1 ? 'asc' : 'desc'
  scheduleService.getList(props.classroom.id, query)
}

function onSort(event) {
  sortField.value = event.sortField
  sortOrder.value = event.sortOrder
  applyFilters()
}

function destroy(id) {
  scheduleService.delete(props.classroom.id, id)
}

/* -------------------------------------------------
   DataTable computed
-------------------------------------------------- */
const value = computed(() => props.schedules?.data ?? [])
const totalRecords = computed(() => props.schedules?.total ?? value.value.length)
const rows = computed(() => props.schedules?.per_page ?? 12)
const first = computed(() => Math.max(0, (props.schedules?.from ?? 1) - 1))

/* -------------------------------------------------
   Helpers hiển thị
-------------------------------------------------- */
const weekdays = ['CN','T2','T3','T4','T5','T6','T7']
</script>

<template>
  <Head :title="`Lịch học - ${classroom.name}`" />

  <!-- Sticky header: breadcrumb + actions -->
  <div class="sticky top-0 z-10 bg-gray-50/70 dark:bg-slate-900/70 backdrop-blur border-b border-slate-200 dark:border-slate-700 -mx-3 md:-mx-5 px-3 md:px-5 py-2">
    <div class="flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
      <!-- Breadcrumb -->
      <nav class="text-sm text-slate-600 dark:text-slate-300 flex items-center gap-2">
        <Link :href="route('admin.classrooms.index')" class="hover:text-emerald-600 dark:hover:text-emerald-300">
          Lớp học
        </Link>
        <span>/</span>
        <Link :href="route('admin.classrooms.edit', { classroom: classroom.id })" class="hover:text-emerald-600 dark:hover:text-emerald-300">
          {{ classroom.name }}
        </Link>
        <span>/</span>
        <span class="font-medium text-slate-900 dark:text-slate-100">Lịch học</span>
      </nav>

      <!-- Actions -->
      <div class="flex flex-wrap items-center gap-2">
        <Link
          :href="route('admin.classrooms.index')"
          class="px-3 py-1.5 rounded-lg border border-slate-300 dark:border-slate-600 hover:bg-slate-100 dark:hover:bg-slate-800"
        >
          ← Danh sách lớp
        </Link>

        <Link
          :href="route('admin.classrooms.edit', { classroom: classroom.id })"
          class="px-3 py-1.5 rounded-lg border border-slate-300 dark:border-slate-600 hover:bg-slate-100 dark:hover:bg-slate-800"
        >
          Chi tiết lớp
        </Link>

        <!-- NEW: mở dialog phát sinh buổi -->
        <Button
          label="Phát sinh buổi"
          icon="pi pi-refresh"
          class="px-3 py-2 rounded-lg bg-indigo-600 text-white hover:bg-indigo-700"
          @click="openGenerateDialog"
        />

        <Link
          :href="route('admin.classrooms.schedules.create', { classroom: classroom.id })"
          class="px-3 py-2 rounded-lg bg-emerald-600 text-white hover:bg-emerald-700"
        >
          <i class="pi pi-plus mr-1"></i> Thêm lịch
        </Link>

        <Select
          v-model="state.perPage"
          :options="[{label:'12 / trang',value:12},{label:'24 / trang',value:24},{label:'50 / trang',value:50}]"
          optionLabel="label" optionValue="value"
          class="w-36"
          @change="applyFilters"
        />
      </div>
    </div>
  </div>

  <!-- Table -->
  <div class="mt-3 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 p-2">
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
      <Column field="weekday" header="Thứ" style="width: 100px" :sortable="true">
        <template #body="{ data }">
          <Tag :value="weekdays[data.weekday]" />
        </template>
      </Column>

      <Column field="start_time" header="Bắt đầu" style="width: 120px" :sortable="true" />
      <Column field="end_time" header="Kết thúc" style="width: 120px" :sortable="true" />

      <Column header="" style="width: 200px">
        <template #body="{ data }">
          <div class="flex gap-2 justify-end">
            <Link
              :href="route('admin.classrooms.schedules.edit', { classroom: classroom.id, schedule: data.id })"
              class="px-3 py-1.5 rounded border border-emerald-300 text-emerald-700 hover:bg-emerald-50 dark:border-emerald-700 dark:text-emerald-300 dark:hover:bg-emerald-900/20"
            >
              <i class="pi pi-pencil mr-1"></i>Sửa
            </Link>
            <button
              @click="destroy(data.id)"
              class="px-3 py-1.5 rounded border border-red-300 text-red-600 hover:bg-red-50 dark:border-red-700 dark:text-red-300 dark:hover:bg-red-900/20"
            >
              <i class="pi pi-trash mr-1"></i>Xoá
            </button>
          </div>
        </template>
      </Column>

      <template #empty>
        <div class="p-6 text-center text-slate-500 dark:text-slate-400">Chưa có lịch nào.</div>
      </template>
    </DataTable>
  </div>

  <!-- Dialog phát sinh buổi -->
  <Dialog v-model:visible="showDialog" modal header="Phát sinh buổi" :style="{ width: '420px' }">
    <div class="flex flex-col gap-4">
      <div>
        <label class="block text-sm font-medium mb-1">Ngày bắt đầu</label>
        <DatePicker
          v-model="form.from_date"
          dateFormat="yy-mm-dd"
          showIcon
          iconDisplay="input"
          class="w-full"
        />
        <small class="text-slate-500">Bỏ trống để dùng ngày bắt đầu của lớp.</small>
      </div>

      <div>
        <label class="block text-sm font-medium mb-1">Số buổi cần phát sinh</label>
        <InputText
          v-model.number="form.max_sessions"
          type="number"
          min="1"
          placeholder="VD: 10"
          class="w-full"
        />
        <small class="text-slate-500">Bỏ trống để tự tính theo tổng số buổi của lớp còn thiếu.</small>
      </div>

      <div class="flex items-center gap-2">
        <Checkbox v-model="form.reset" :binary="true" inputId="reset" />
        <label for="reset" class="cursor-pointer select-none">Xoá hết buổi đang planned và phát sinh lại</label>
      </div>
    </div>

    <template #footer>
      <Button label="Huỷ" icon="pi pi-times" text @click="showDialog=false" />
      <Button label="Phát sinh" icon="pi pi-check" @click="submitGenerate" autofocus />
    </template>
  </Dialog>
</template>
