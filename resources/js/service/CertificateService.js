import { router } from '@inertiajs/vue3'

/**
 * API helper cho Certificates (Inertia)
 * Tất cả đều dùng router.* để giữ SPA navigation.
 */
const CertificateService = {
  // List với keyword (debounce ở caller nếu cần)
  index(params = {}) {
    return router.get(route('manager.certificates.index'), params, {
      preserveState: true,
      replace: true
    })
  },

  // Điều hướng tới trang create
  goCreate() {
    return router.visit(route('manager.certificates.create'))
  },

  // POST create
  store(form) {
    return form.post(route('manager.certificates.store'))
  },

  // Điều hướng tới trang edit
  goEdit(id) {
    return router.visit(route('manager.certificates.edit', id))
  },

  // PUT update
  update(id, form) {
    return form.put(route('manager.certificates.update', id))
  },

  // DELETE
  destroy(id) {
    return router.delete(route('manager.certificates.destroy', id))
  }
}

export default CertificateService
