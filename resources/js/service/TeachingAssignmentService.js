import { router } from '@inertiajs/vue3'

export const createTeachingAssignmentService = () => ({
  // List
  getList(classroomId, params = {}) {
    router.visit(route('manager.classrooms.assignments.index', { classroom: classroomId, ...params }), {
      preserveScroll: true,
      preserveState: true
    })
  },

  // Create
  create(classroomId, data, callbacks = {}) {
    router.post(route('manager.classrooms.assignments.store', { classroom: classroomId }), data, {
      onSuccess: () => callbacks.onSuccess?.(),
      onError: (errors) => callbacks.onError?.(errors)
    })
  },

  // Update
  update(classroomId, id, data, callbacks = {}) {
    router.put(route('manager.classrooms.assignments.update', { classroom: classroomId, assignment: id }), data, {
      onSuccess: () => callbacks.onSuccess?.(),
      onError: (errors) => callbacks.onError?.(errors)
    })
  },

  // Delete
  delete(classroomId, id, callbacks = {}) {
    router.delete(route('manager.classrooms.assignments.destroy', { classroom: classroomId, assignment: id }), {
      preserveScroll: true,
      onSuccess: () => callbacks.onSuccess?.(),
      onError: (errors) => callbacks.onError?.(errors)
    })
  }
})
