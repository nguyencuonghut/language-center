// resources/js/service/ManagerEnrollmentService.js
import { router } from '@inertiajs/vue3'

export const createManagerEnrollmentService = ({ showSuccess, showError }) => ({
  /**
   * Lấy danh sách ghi danh của 1 lớp
   */
  getList(classroomId, params = {}) {
    router.visit(route('manager.classrooms.enrollments.index', { classroom: classroomId, ...params }), {
      preserveScroll: true,
      preserveState: true,
      onError: () => {
        showError?.('Lỗi tải dữ liệu', 'Không thể tải danh sách ghi danh')
      }
    })
  },

  /**
   * Ghi danh 1 học viên vào lớp
   */
  create(classroomId, data, callbacks = {}) {
    router.post(route('manager.classrooms.enrollments.store', { classroom: classroomId }), data, {
      onSuccess: () => {
        callbacks.onSuccess?.()
      },
      onError: (errors) => {
        if (errors?.message) {
          showError?.('Lỗi', errors.message)
        }
        callbacks.onError?.(errors)
      }
    })
  },

  /**
   * Huỷ ghi danh
   */
  delete(classroomId, enrollmentId, callbacks = {}) {
    if (!confirm('Xác nhận huỷ ghi danh học viên này?')) return

    router.delete(route('manager.classrooms.enrollments.destroy', { classroom: classroomId, enrollment: enrollmentId }), {
      preserveScroll: true,
      onSuccess: () => {
        callbacks.onSuccess?.()
      },
      onError: (errors) => {
        if (errors?.message) {
          showError?.('Lỗi', errors.message)
        }
        callbacks.onError?.(errors)
      }
    })
  },

  /**
   * Ghi danh nhiều học viên vào lớp
   */
  createMany(classroomId, data, callbacks = {}) {
    // data = { student_ids: number[], enrolled_at?: 'YYYY-MM-DD', start_session_no?: number, status?: string }
    router.post(route('manager.classrooms.enrollments.bulk-store', { classroom: classroomId }), data, {
      onSuccess: () => callbacks.onSuccess?.(),
      onError: (errors) => {
        if (errors?.message) showError?.('Lỗi', errors.message)
        callbacks.onError?.(errors)
      }
    })
  },
})
