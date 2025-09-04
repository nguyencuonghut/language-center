import { router } from '@inertiajs/vue3'

/**
 * TransferService - JavaScript service for Transfer management
 * Pattern: createTransferService() - Follow standard pattern, no toast injection
 * Routes: manager.transfers.*
 * Note: Toast handled by AppLayout via flash messages from backend
 */
export const createTransferService = () => ({

  /**
   * Get list of transfers with pagination and filters
   * @param {Object} params { q, status, from_date, to_date, page, per_page }
   */
  getList(params = {}) {
    router.visit(route('manager.transfers.index', params), {
      preserveScroll: true,
      preserveState: true,
      replace: true,
      onError: (errors) => {
        // Backend handles flash error messages
      }
    })
  },

  /**
   * Navigate to create transfer page
   * @param {Object} params { student_id? }
   */
  navigateToCreate(params = {}) {
    router.visit(route('manager.transfers.create', params))
  },  /**
   * Create a new transfer
   * @param {Object} data Transfer data
   * @param {Object} callbacks { onSuccess, onError }
   */
  create(data, callbacks = {}) {
    router.post(route('manager.transfers.store'), data, {
      onSuccess: () => {
        callbacks.onSuccess?.()
      },
      onError: (errors) => {
        callbacks.onError?.(errors)
      }
    })
  },

  /**
   * Create transfer for specific student (legacy support)
   * @param {Number} studentId
   * @param {Object} data Transfer data
   * @param {Object} callbacks { onSuccess, onError }
   */
  createForStudent(studentId, data, callbacks = {}) {
    router.post(route('manager.students.transfer', studentId), data, {
      preserveScroll: true,
      onSuccess: () => {
        callbacks.onSuccess?.()
      },
      onError: (errors) => {
        callbacks.onError?.(errors)
      }
    })
  },

  /**
   * View transfer details
   * @param {Number} transferId
   */
  show(transferId) {
    router.visit(route('manager.transfers.show', transferId))
  },

  /**
   * Revert a transfer
   * @param {Object} transferData { student_id, from_class_id, to_class_id }
   * @param {Object} callbacks { onSuccess, onError }
   */
  revert(transferData, callbacks = {}) {
    if (!confirm('Bạn có chắc chắn muốn hoàn tác transfer này?')) {
      return
    }

    router.post(route('manager.transfers.revert'), transferData, {
      preserveScroll: true,
      onSuccess: () => {
        callbacks.onSuccess?.()
      },
      onError: (errors) => {
        callbacks.onError?.(errors)
      }
    })
  },

  /**
   * Retarget a transfer
   * @param {Object} retargetData { student_id, from_class_id, old_to_class_id, new_to_class_id, ... }
   * @param {Object} callbacks { onSuccess, onError }
   */
  retarget(retargetData, callbacks = {}) {
    router.post(route('manager.transfers.retarget'), retargetData, {
      preserveScroll: true,
      onSuccess: () => {
        callbacks.onSuccess?.()
      },
      onError: (errors) => {
        callbacks.onError?.(errors)
      }
    })
  },

  /**
   * Search students for autocomplete
   * @param {String} query Search query
   * @returns {Promise<Array>} Students array
   */
  async searchStudents(query) {
    try {
      const response = await fetch(route('manager.students.search') + `?q=${encodeURIComponent(query)}`)
      if (response.ok) {
        const data = await response.json()
        return data.map(student => ({
          ...student,
          label: `${student.code} - ${student.name}`
        }))
      }
      return []
    } catch (error) {
      console.error('Failed to search students:', error)
      return []
    }
  },

  /**
   * Get transfer statistics
   * @param {Object} filters { from_date, to_date }
   * @returns {Promise<Object>} Stats object
   */
  async getStats(filters = {}) {
    try {
      const params = new URLSearchParams(filters)
      const response = await fetch(route('manager.transfers.index') + `?stats_only=1&${params}`)
      if (response.ok) {
        const data = await response.json()
        return data.stats
      }
      return null
    } catch (error) {
      console.error('Failed to get transfer stats:', error)
      return null
    }
  },

  // Utility functions
  utils: {
    /**
     * Get status label
     * @param {String} status
     * @returns {String}
     */
    getStatusLabel(status) {
      const map = {
        active: 'Đang hoạt động',
        reverted: 'Đã hoàn tác',
        retargeted: 'Đã đổi hướng'
      }
      return map[status] || status
    },

    /**
     * Get status severity for PrimeVue Tag
     * @param {String} status
     * @returns {String}
     */
    getStatusSeverity(status) {
      const map = {
        active: 'success',
        reverted: 'warn',
        retargeted: 'info'
      }
      return map[status] || 'secondary'
    },

    /**
     * Format date to Vietnamese locale
     * @param {String|Date} date
     * @returns {String}
     */
    formatDate(date) {
      return new Date(date).toLocaleDateString('vi-VN')
    },

    /**
     * Format datetime to Vietnamese locale
     * @param {String|Date} datetime
     * @returns {String}
     */
    formatDateTime(datetime) {
      return new Date(datetime).toLocaleString('vi-VN')
    },

    /**
     * Format currency to Vietnamese format
     * @param {Number} amount
     * @returns {String}
     */
    formatCurrency(amount) {
      return new Intl.NumberFormat('vi-VN', {
        style: 'currency',
        currency: 'VND'
      }).format(amount)
    },

    /**
     * Get effective target class (considering retargeted transfers)
     * @param {Object} transfer
     * @returns {Object}
     */
    getEffectiveTargetClass(transfer) {
      if (transfer.status === 'retargeted' && transfer.retargeted_to_class) {
        return transfer.retargeted_to_class
      }
      return transfer.to_class
    },

    /**
     * Status options for Select component
     */
    statusOptions: [
      { label: 'Tất cả', value: '' },
      { label: 'Đang hoạt động', value: 'active' },
      { label: 'Đã hoàn tác', value: 'reverted' },
      { label: 'Đã đổi hướng', value: 'retargeted' }
    ]
  }
})
