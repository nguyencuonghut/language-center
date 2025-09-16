<script setup>
import { usePage, Head, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import Button from 'primevue/button'

defineOptions({ layout: AppLayout })

const { classroom, schedules } = usePage().props

const formatWeekday = (weekday) => {
  const days = ['Chủ nhật', 'Thứ 2', 'Thứ 3', 'Thứ 4', 'Thứ 5', 'Thứ 6', 'Thứ 7']
  return days[weekday] || 'Không xác định'
}

const classDetail = () => {
  router.visit(route('teacher.classrooms.show', classroom.id))
}

const classList = () => {
  router.visit(route('teacher.classrooms.index'))
}
</script>

<template>
  <Head title="Lịch học" />

  <div class="mb-3 flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
    <h1 class="text-xl md:text-2xl font-heading font-semibold">Lịch học - {{ classroom.name }}</h1>
    <div class="flex gap-2">
      <Button
        label="Chi tiết lớp"
        outlined
        icon="pi pi-arrow-left"
        severity="help"
        @click="classDetail"
        class="p-button-sm"
      />
      <Button
        label="Danh sách lớp"
        outlined
        icon="pi pi-arrow-left"
        severity="info"
        @click="classList"
        class="p-button-sm"
      />
    </div>
  </div>

  <div class="rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 p-4">
    <DataTable
      :value="schedules"
      dataKey="id"
      class="p-datatable-sm"
      responsiveLayout="scroll"
      stripedRows
      :emptyMessage="'Không có lịch học nào'"
    >
      <Column header="Thứ">
        <template #body="{ data }">
          {{ formatWeekday(data.weekday) }}
        </template>
      </Column>
      <Column field="start_time" header="Bắt đầu" />
      <Column field="end_time" header="Kết thúc" />
    </DataTable>
  </div>
</template>
