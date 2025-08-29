import { router } from '@inertiajs/vue3'

/**
 * StudentService (prefix: manager.students.*)
 * - Không hiển thị toast ở FE (BE lo flash)
 * - Có callbacks onSuccess / onError nếu cần hook
 */
export const createStudentService = () => ({

  /**
   * Danh sách học viên (kèm filter/sort/pagination)
   * @param {Object} params { q, per_page, page, sort, order }
   */
  getList(params = {}) {
    router.visit(route('manager.students.index', params), {
      preserveScroll: true,
      preserveState: true,
      onError: (errors) => {
        // giữ trống để BE flash lỗi
      }
    })
  },

  /**
   * Tạo học viên
   */
  create(data, callbacks = {}) {
    router.post(route('manager.students.store'), data, {
      onSuccess: () => { callbacks.onSuccess?.() },
      onError:   (errors) => { callbacks.onError?.(errors) }
    })
  },

  /**
   * Cập nhật học viên
   */
  update(id, data, callbacks = {}) {
    router.put(route('manager.students.update', id), data, {
      preserveScroll: true,
      onSuccess: () => { callbacks.onSuccess?.() },
      onError:   (errors) => { callbacks.onError?.(errors) }
    })
  },

  /**
   * Xoá học viên
   */
  delete(id, callbacks = {}) {
    if (!confirm('Xác nhận xoá học viên này?')) return
    router.delete(route('manager.students.destroy', id), {
      preserveScroll: true,
      onSuccess: () => { callbacks.onSuccess?.() },
      onError:   (errors) => { callbacks.onError?.(errors) }
    })
  },

  /**
   * (Tuỳ chọn) Gọi API search để gợi ý AutoComplete (trả JSON)
   * Bạn có thể dùng fetch/axios tại chỗ gọi component,
   * service để sẵn hàm build URL cho tiện.
   */
  searchUrl(q = '') {
    const url = route('manager.students.search', q ? { q } : {})
    return url
  },

})
