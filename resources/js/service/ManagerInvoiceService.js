import { router } from '@inertiajs/vue3'

/**
 * Service cho hóa đơn học phí (Invoices) - Manager version
 * Lưu ý: KHÔNG gọi toast ở FE. BE trả flash -> AppLayout.vue sẽ hiển thị.
 */
export const createManagerInvoiceService = () => ({
  /**
   * Danh sách hóa đơn (có thể kèm query: {branch, student, status, q, per_page, page, sort, order})
   */
  getList(params = {}) {
    router.visit(route('manager.invoices.index', params), {
      preserveScroll: true,
      preserveState: true,
    })
  },

  /**
   * Xem chi tiết hóa đơn
   */
  show(id, params = {}) {
    router.visit(route('manager.invoices.show', id), {
      data: params,
      preserveScroll: true,
      preserveState: true,
    })
  },

  /**
   * Tạo mới hóa đơn
   * data ví dụ: { branch_id, student_id, class_id|null, total, due_date|null, status? }
   */
  create(data, callbacks = {}) {
    router.post(route('manager.invoices.store'), data, {
      onSuccess: () => callbacks.onSuccess?.(),
      onError:   (errors) => callbacks.onError?.(errors),
      onFinish:  () => callbacks.onFinish?.(),
    })
  },

  /**
   * Cập nhật hóa đơn
   */
  update(id, data, callbacks = {}) {
    router.put(route('manager.invoices.update', id), data, {
      preserveScroll: true,
      onSuccess: () => callbacks.onSuccess?.(),
      onError:   (errors) => callbacks.onError?.(errors),
      onFinish:  () => callbacks.onFinish?.(),
    })
  },

  /**
   * Xóa hóa đơn
   */
  delete(id, callbacks = {}) {
    if (!confirm('Xác nhận xoá hóa đơn này?')) return
    router.delete(route('manager.invoices.destroy', id), {
      preserveScroll: true,
      onSuccess: () => callbacks.onSuccess?.(),
      onError:   (errors) => callbacks.onError?.(errors),
      onFinish:  () => callbacks.onFinish?.(),
    })
  },

  // -------------------------
  // Invoice Items (chi tiết)
  // -------------------------

  /**
   * Thêm dòng chi tiết
   * data ví dụ: { type:'tuition', description, qty, unit_price, amount }
   */
  addItem(invoiceId, data, callbacks = {}) {
    router.post(route('manager.invoices.items.store', invoiceId), data, {
      preserveScroll: true,
      onSuccess: () => callbacks.onSuccess?.(),
      onError:   (errors) => callbacks.onError?.(errors),
      onFinish:  () => callbacks.onFinish?.(),
    })
  },

  /**
   * Cập nhật dòng chi tiết
   */
  updateItem(invoiceId, itemId, data, callbacks = {}) {
    router.put(route('manager.invoices.items.update', { invoice: invoiceId, item: itemId }), data, {
      preserveScroll: true,
      onSuccess: () => callbacks.onSuccess?.(),
      onError:   (errors) => callbacks.onError?.(errors),
      onFinish:  () => callbacks.onFinish?.(),
    })
  },

  /**
   * Xoá dòng chi tiết
   */
  deleteItem(invoiceId, itemId, callbacks = {}) {
    if (!confirm('Xác nhận xoá dòng chi tiết này?')) return
    router.delete(route('manager.invoices.items.destroy', { invoice: invoiceId, item: itemId }), {
      preserveScroll: true,
      onSuccess: () => callbacks.onSuccess?.(),
      onError:   (errors) => callbacks.onError?.(errors),
      onFinish:  () => callbacks.onFinish?.(),
    })
  },

  // -------------------------
  // Payments (thanh toán)
  // -------------------------

  /**
   * Ghi nhận thanh toán
   * data ví dụ: { method:'cash'|'bank'|'momo'|'zalopay', paid_at:'YYYY-MM-DD', amount, ref_no }
   */
  recordPayment(invoiceId, data, callbacks = {}) {
    router.post(route('manager.invoices.payments.store', invoiceId), data, {
      preserveScroll: true,
      onSuccess: () => callbacks.onSuccess?.(),
      onError:   (errors) => callbacks.onError?.(errors),
      onFinish:  () => callbacks.onFinish?.(),
    })
  },

  /**
   * Xoá thanh toán
   */
  deletePayment(invoiceId, paymentId, callbacks = {}) {
    if (!confirm('Xác nhận xoá khoản thanh toán này?')) return
    router.delete(route('manager.invoices.payments.destroy', { invoice: invoiceId, payment: paymentId }), {
      preserveScroll: true,
      onSuccess: () => callbacks.onSuccess?.(),
      onError:   (errors) => callbacks.onError?.(errors),
      onFinish:  () => callbacks.onFinish?.(),
    })
  },
})
