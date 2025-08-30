import { router } from '@inertiajs/vue3'

export const createTeachingAssignmentService = () => ({
  getList(classroomId, params = {}) {
    router.visit(route('manager.classrooms.assignments.index', { classroom: classroomId, ...params }), {
      preserveScroll: true,
      preserveState: true,
    })
  },

  create(classroomId, data, callbacks = {}) {
    router.post(route('manager.classrooms.assignments.store', { classroom: classroomId }), data, {
      onFinish: () => callbacks.onFinish?.(),
      onSuccess: () => callbacks.onSuccess?.(),
      onError: (errors) => callbacks.onError?.(errors),
      preserveScroll: true,
    })
  },

  update(classroomId, id, data, callbacks = {}) {
    router.put(route('manager.classrooms.assignments.update', { classroom: classroomId, assignment: id }), data, {
      onFinish: () => callbacks.onFinish?.(),
      onSuccess: () => callbacks.onSuccess?.(),
      onError: (errors) => callbacks.onError?.(errors),
      preserveScroll: true,
    })
  },

  delete(classroomId, id, callbacks = {}) {
    router.delete(route('manager.classrooms.assignments.destroy', { classroom: classroomId, assignment: id }), {
      onFinish: () => callbacks.onFinish?.(),
      onSuccess: () => callbacks.onSuccess?.(),
      onError: (errors) => callbacks.onError?.(errors),
      preserveScroll: true,
    })
  },
})
