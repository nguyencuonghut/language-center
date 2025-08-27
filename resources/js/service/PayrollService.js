import { router } from '@inertiajs/vue3'

// Chuẩn hoá ngày local → YYYY-MM-DD (tránh lệch timezone)
function toYmdLocal(d) {
  if (!d) return null
  const dt = new Date(d)
  const y = dt.getFullYear()
  const m = String(dt.getMonth() + 1).padStart(2, '0')
  const day = String(dt.getDate()).padStart(2, '0')
  return `${y}-${m}-${day}`
}

export const createPayrollService = ({ showError } = {}) => ({
  /**
   * Danh sách Payrolls
   */
  getList(params = {}) {
    router.visit(route('manager.payrolls.index', params), {
      preserveScroll: true,
      preserveState: true,
      onError: () => showError?.('Lỗi', 'Không thể tải danh sách bảng lương')
    })
  },

  /**
   * Tạo Payroll (StorePayrollRequest xử lý validate)
   * payload: { branch_id|null|'all', period_from(Date|str), period_to(Date|str), name?, notes?, teacher_ids?, lock_timesheets? }
   */
  create(payload = {}, callbacks = {}) {
    const data = { ...payload }
    if (data.period_from) data.period_from = toYmdLocal(data.period_from)
    if (data.period_to)   data.period_to   = toYmdLocal(data.period_to)

    router.post(route('manager.payrolls.store'), data, {
      onSuccess: () => callbacks.onSuccess?.(),
      onError: (errors) => callbacks.onError?.(errors)
    })
  },

  /**
   * Xem chi tiết
   */
  show(id, params = {}) {
    router.visit(route('manager.payrolls.show', id), {
      data: params,
      preserveScroll: true,
      preserveState: true
    })
  },

  /**
   * Duyệt (Approve) — chỉ cho status=draft
   */
  approve(id, payload = {}, callbacks = {}) {
    router.post(route('manager.payrolls.approve', id), payload, {
      preserveScroll: true,
      onSuccess: () => callbacks.onSuccess?.(),
      onError: (errors) => callbacks.onError?.(errors)
    })
  },

  /**
   * Khoá (Lock) — chỉ cho status=approved
   */
  lock(id, payload = {}, callbacks = {}) {
    router.post(route('manager.payrolls.lock', id), payload, {
      preserveScroll: true,
      onSuccess: () => callbacks.onSuccess?.(),
      onError: (errors) => callbacks.onError?.(errors)
    })
  },

  /**
   * Xoá — thường chỉ cho status=draft
   */
  destroy(id, callbacks = {}) {
    if (!confirm('Xác nhận xoá bảng lương này?')) return
    router.delete(route('manager.payrolls.destroy', id), {
      preserveScroll: true,
      onSuccess: () => callbacks.onSuccess?.(),
      onError: (errors) => callbacks.onError?.(errors)
    })
  }
})
