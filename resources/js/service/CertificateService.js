import { router } from '@inertiajs/vue3'

/**
 * API helper cho Certificates (Inertia)
 * Tất cả đều dùng router.* để giữ SPA navigation.
 * Đã chuẩn hóa callback như CourseService.js
 */
const CertificateService = {
  // List với keyword (debounce ở caller nếu cần)
  index(params = {}, callbacks = {}) {
    router.get(route('manager.certificates.index'), params, {
      preserveState: true,
      replace: true,
      onSuccess: () => callbacks.onSuccess?.(),
      onError: (errors) => callbacks.onError?.(errors),
      onFinish: () => callbacks.onFinish?.()
    })
  },

  // Điều hướng tới trang create
  goCreate(callbacks = {}) {
    router.visit(route('manager.certificates.create'), {
      onSuccess: () => callbacks.onSuccess?.(),
      onError: (errors) => callbacks.onError?.(errors),
      onFinish: () => callbacks.onFinish?.()
    })
  },

  // POST create
  store(form, callbacks = {}) {
    form.post(route('manager.certificates.store'), {
      onSuccess: () => callbacks.onSuccess?.(),
      onError: (errors) => callbacks.onError?.(errors),
      onFinish: () => callbacks.onFinish?.()
    })
  },

  // Điều hướng tới trang edit
  goEdit(id, callbacks = {}) {
    router.visit(route('manager.certificates.edit', id), {
      onSuccess: () => callbacks.onSuccess?.(),
      onError: (errors) => callbacks.onError?.(errors),
      onFinish: () => callbacks.onFinish?.()
    })
  },

  // PUT update
  update(id, form, callbacks = {}) {
    form.put(route('manager.certificates.update', id), {
      onSuccess: () => callbacks.onSuccess?.(),
      onError: (errors) => callbacks.onError?.(errors),
      onFinish: () => callbacks.onFinish?.()
    })
  },

  // DELETE
  destroy(id, callbacks = {}) {
    router.delete(route('manager.certificates.destroy', id), {
      onSuccess: () => callbacks.onSuccess?.(),
      onError: (errors) => callbacks.onError?.(errors),
      onFinish: () => callbacks.onFinish?.()
    })
  },

  // Gán chứng chỉ cho giáo viên
  attachTeacher(teacherId, form, callbacks = {}) {
    return form.post(route('manager.teachers.certificates.attach', teacherId), {
        forceFormData: true,
        onSuccess: () => callbacks.onSuccess?.(),
        onError: (errors) => callbacks.onError?.(errors),
        onFinish: () => callbacks.onFinish?.()
    })
  },

  // Bỏ gán chứng chỉ cho giáo viên
  detachTeacher(teacherId, certId, callbacks = {}) {
    router.delete(route('manager.teachers.certificates.detach', [teacherId, certId]), {
      onSuccess: () => callbacks.onSuccess?.(),
      onError: (errors) => callbacks.onError?.(errors),
      onFinish: () => callbacks.onFinish?.()
    })
  }
}

export default CertificateService
