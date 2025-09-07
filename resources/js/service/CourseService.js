import { router } from '@inertiajs/vue3'

export const createCourseService = () => ({
  // List + filters/pagination (nếu có)
  getList(params = {}) {
    router.visit(route('manager.courses.index', params), {
      preserveScroll: true,
      preserveState: true
    })
  },

  // Store
  create(data, callbacks = {}) {
    router.post(route('manager.courses.store'), data, {
      onSuccess: () => callbacks.onSuccess?.(),
      onError: (errors) => callbacks.onError?.(errors)
    })
  },

  // Update
  update(id, data, callbacks = {}) {
    router.put(route('manager.courses.update', id), data, {
      onSuccess: () => callbacks.onSuccess?.(),
      onError: (errors) => callbacks.onError?.(errors)
    })
  },

  // Delete
  delete(id, callbacks = {}) {
    if (!confirm('Xác nhận xoá khóa học này?')) return
    router.delete(route('manager.courses.destroy', id), {
      preserveScroll: true,
      onSuccess: () => callbacks.onSuccess?.(),
      onError: (errors) => callbacks.onError?.(errors)
    })
  }
})
