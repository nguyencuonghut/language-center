
import { router } from '@inertiajs/vue3'

export const createHolidayService = ({ showSuccess, showError }) => ({
  /**
   * Lấy danh sách ngày nghỉ (dùng Inertia visit để đồng bộ state)
   */
  getList(params = {}) {
    router.visit(route('admin.holidays.index', params), {
      preserveScroll: true,
      preserveState: true,
      onError: (errors) => {
        showError('Lỗi tải dữ liệu', 'Không thể tải danh sách ngày nghỉ')
      }
    })
  },

  /**
   * Tạo ngày nghỉ mới
   */
  create(data, callbacks = {}) {
    router.post(route('admin.holidays.store'), data, {
      onSuccess: () => {
        showSuccess('Thành công', 'Đã thêm ngày nghỉ')
        callbacks.onSuccess?.()
      },
      onError: (errors) => {
        if (errors.message) {
          showError('Lỗi', errors.message)
        }
        callbacks.onError?.(errors)
      }
    })
  },

  /**
   * Cập nhật ngày nghỉ
   */
  update(id, data, callbacks = {}) {
    router.put(route('admin.holidays.update', id), data, {
      onSuccess: () => {
        showSuccess('Thành công', 'Đã cập nhật ngày nghỉ')
        callbacks.onSuccess?.()
      },
      onError: (errors) => {
        if (errors.message) {
          showError('Lỗi', errors.message)
        }
        callbacks.onError?.(errors)
      }
    })
  },

  /**
   * Xoá ngày nghỉ
   */
  delete(id, callbacks = {}) {
    if (!confirm('Xác nhận xoá ngày nghỉ này?')) return

    router.delete(route('admin.holidays.destroy', id), {
      preserveScroll: true,
      onSuccess: () => {
        showSuccess('Đã xoá', 'Đã xoá ngày nghỉ')
        callbacks.onSuccess?.()
      },
      onError: (errors) => {
        if (errors.message) {
          showError('Lỗi', errors.message)
        }
        callbacks.onError?.(errors)
      }
    })
  }
})
