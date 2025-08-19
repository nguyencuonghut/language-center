import { router } from '@inertiajs/vue3'

export const createClassScheduleService = ({ showSuccess, showError }) => ({
  index(classroomId) {
    router.visit(route('admin.classrooms.schedules.index', classroomId), {
      preserveScroll: true,
      preserveState: true,
    })
  },
  create(classroomId, callbacks = {}) {
    router.visit(route('admin.classrooms.schedules.create', classroomId), {
      onError: callbacks.onError,
    })
  },
  store(classroomId, data, callbacks = {}) {
    router.post(route('admin.classrooms.schedules.store', classroomId), data, {
      onSuccess: callbacks.onSuccess,
      onError: callbacks.onError,
    })
  },
  edit(classroomId, scheduleId, callbacks = {}) {
    router.visit(route('admin.classrooms.schedules.edit', { classroom: classroomId, schedule: scheduleId }), {
      onError: callbacks.onError,
    })
  },
  update(classroomId, scheduleId, data, callbacks = {}) {
    router.put(route('admin.classrooms.schedules.update', { classroom: classroomId, schedule: scheduleId }), data, {
      onSuccess: callbacks.onSuccess,
      onError: callbacks.onError,
    })
  },
  delete(classroomId, scheduleId, callbacks = {}) {
    if (!confirm('Xác nhận xoá lịch học này?')) return
    router.delete(route('admin.classrooms.schedules.destroy', { classroom: classroomId, schedule: scheduleId }), {
      onSuccess: callbacks.onSuccess,
      onError: callbacks.onError,
    })
  }
})
