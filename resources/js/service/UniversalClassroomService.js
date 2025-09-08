import { router } from '@inertiajs/vue3'

/**
 * Universal Service cho quản lý lớp học (Classrooms)
 * Hỗ trợ cả Admin và Manager với cùng 1 codebase
 * 
 * @param {string} rolePrefix - 'admin' hoặc 'manager'
 * @param {Object} toastService - { showSuccess, showError }
 * @returns {Object} Service object với các methods
 */
export const createUniversalClassroomService = (rolePrefix = 'admin', { showSuccess, showError } = {}) => ({
    /**
     * Get list of classrooms with pagination and filters
     */
    getList(params = {}) {
        router.visit(route(`${rolePrefix}.classrooms.index`, params), {
            preserveScroll: true,
            preserveState: true,
            onError: (errors) => {
                showError?.('Lỗi tải dữ liệu', 'Không thể tải danh sách lớp học')
            }
        })
    },

    create(data, callbacks = {}) {
        // Format date to MySQL datetime format
        const formattedData = {
            ...data,
            start_date: data.start_date ? new Date(data.start_date).toISOString().slice(0, 19).replace('T', ' ') : null
        };
        router.post(route(`${rolePrefix}.classrooms.store`), formattedData, {
            onSuccess: () => {
                callbacks.onSuccess?.()
            },
            onError: (errors) => {
                if (errors.message) {
                    showError?.('Lỗi', errors.message)
                }
                callbacks.onError?.(errors)
            }
        })
    },

    /**
     * Update a classroom
     */
    update(id, data, callbacks = {}) {
        // Format date to MySQL datetime format
        const formattedData = {
            ...data,
            start_date: data.start_date ? new Date(data.start_date).toISOString().slice(0, 19).replace('T', ' ') : null
        };
        router.put(route(`${rolePrefix}.classrooms.update`, id), formattedData, {
            onSuccess: () => {
                callbacks.onSuccess?.()
            },
            onError: (errors) => {
                if (errors.message) {
                    showError?.('Lỗi', errors.message)
                }
                callbacks.onError?.(errors)
            }
        })
    },

    /**
     * Delete a classroom
     */
    delete(id, callbacks = {}) {
        if (!confirm('Xác nhận xoá lớp học này?')) return

        router.delete(route(`${rolePrefix}.classrooms.destroy`, id), {
            preserveScroll: true,
            onSuccess: () => {
                showSuccess?.('Thành công', 'Đã xoá lớp học')
                callbacks.onSuccess?.()
            },
            onError: (errors) => {
                showError?.('Lỗi', 'Không thể xoá lớp học')
                if (errors.message) {
                    showError?.('Lỗi', errors.message)
                }
                callbacks.onError?.(errors)
            }
        })
    },

    /**
     * Archive a classroom (chỉ có ở Admin)
     */
    archive(id) {
        if (rolePrefix !== 'admin') {
            console.warn('Archive function chỉ available cho Admin')
            return
        }
        
        if (!confirm('Xác nhận lưu trữ lớp học này?')) return

        router.put(route(`${rolePrefix}.classrooms.archive`, id), {}, {
            preserveScroll: true,
            onSuccess: () => {
                showSuccess?.('Thành công', 'Đã chuyển lớp học vào kho lưu trữ')
            },
            onError: (errors) => {
                showError?.('Lỗi', 'Không thể lưu trữ lớp học')
            }
        })
    },

    /**
     * Restore an archived classroom (chỉ có ở Admin)
     */
    restore(id) {
        if (rolePrefix !== 'admin') {
            console.warn('Restore function chỉ available cho Admin')
            return
        }
        
        router.put(route(`${rolePrefix}.classrooms.restore`, id), {}, {
            preserveScroll: true,
            onSuccess: () => {
                showSuccess?.('Thành công', 'Đã khôi phục lớp học')
            },
            onError: (errors) => {
                showError?.('Lỗi', 'Không thể khôi phục lớp học')
            }
        })
    }
})

// Convenience functions cho từng role
export const createClassroomService = (toastService) => createUniversalClassroomService('admin', toastService)
export const createManagerClassroomService = (toastService) => createUniversalClassroomService('manager', toastService)
