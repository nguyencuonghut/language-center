<script setup>
import { usePage, Head, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import Button from 'primevue/button'

defineOptions({ layout: AppLayout })

const { classroom } = usePage().props

const statusLabel = status => {
  switch (status) {
    case 'open': return { text: 'Đang mở', severity: 'success' }
    case 'closed': return { text: 'Đã đóng', severity: 'danger' }
    default: return { text: status, severity: 'secondary' }
  }
}

const viewSchedules = () => {
  router.visit(route('teacher.classrooms.schedules.index', classroom.id))
}

const viewSessions = () => {
  router.visit(route('teacher.classrooms.sessions.index', classroom.id))
}

const viewEnrollments = () => {
  router.visit(route('teacher.classrooms.enrollments.index', classroom.id))
}

const viewAttendance = () => {
  router.visit(route('teacher.attendance.index'))
}

const viewClassList = () => {
  router.visit(route('teacher.classrooms.index'))
}
</script>

<template>
  <Head title="Chi tiết lớp" />

  <div class="mb-3 flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
    <h1 class="text-xl md:text-2xl font-heading font-semibold">Chi tiết lớp: {{ classroom.name }}</h1>
    <Button
      label="Danh sách lớp"
      outlined
      icon="pi pi-list"
      severity="info"
      @click="viewClassList"
      class="p-button-sm"
    />
  </div>

  <div class="rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 p-4">
    <div class="mb-4">
      <div class="mb-2">Mã lớp: <b>{{ classroom.code }}</b></div>
      <div class="mb-2">Khóa học: <b>{{ classroom.course?.name }}</b></div>
      <div class="mb-2">Cơ sở: <b>{{ classroom.branch?.name }}</b></div>
      <div class="mb-2">Ngày khai giảng: <b>{{ classroom.start_date }}</b></div>
      <div class="mb-2">Trạng thái: <b>{{ statusLabel(classroom.status).text }}</b></div>
    </div>
    <div class="flex gap-3">
      <Button
        label="Lịch học"
        outlined
        icon="pi pi-calendar"
        severity="info"
        @click="viewSchedules"
        class="p-button-sm"
      />
      <Button
        label="Buổi học"
        outlined
        icon="pi pi-clock"
        severity="info"
        @click="viewSessions"
        class="p-button-sm"
      />
      <Button
        label="Học viên"
        outlined
        icon="pi pi-users"
        severity="info"
        @click="viewEnrollments"
        class="p-button-sm"
      />
      <Button
        label="Điểm danh"
        outlined
        icon="pi pi-check-circle"
        severity="success"
        @click="viewAttendance"
        class="p-button-sm"
      />
    </div>
  </div>
</template>
