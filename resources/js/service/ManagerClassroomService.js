import { router } from '@inertiajs/vue3'

export const createManagerClassroomService = ({ showSuccess, showError }) => ({
    /**
     * Get list of classrooms with pagination and filters
     */
    getList(params = {}) {
        router.visit(route('manager.classrooms.index', params), {
            preserveScroll: true,
            preserveState: true,
            onError: (errors) => {
                showError('Lỗi tải dữ liệu', 'Không thể tải danh sách lớp học')
            }
        })
    },

    create(data, callbacks = {}) {
        // Format date to MySQL datetime format
        const formattedData = {
            ...data,
            start_date: data.start_date ? new Date(data.start_date).toISOString().slice(0, 19).replace('T', ' ') : null
        };
        router.post(route('manager.classrooms.store'), formattedData, {
            onSuccess: () => {
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
     * Update a classroom
     */
    update(id, data, callbacks = {}) {
        // Format date to MySQL datetime format
        const formattedData = {
            ...data,
            start_date: data.start_date ? new Date(data.start_date).toISOString().slice(0, 19).replace('T', ' ') : null
        };
        router.put(route('manager.classrooms.update', id), formattedData, {
            onSuccess: () => {
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
     * Delete a classroom
     */
    delete(id, callbacks = {}) {
        if (!confirm('Xác nhận xoá lớp học này?')) return

        router.delete(route('manager.classrooms.destroy', id), {
            preserveScroll: true,
            onSuccess: () => {
                showSuccess('Thành công', 'Đã xoá lớp học')
                callbacks.onSuccess?.()
            },
            onError: (errors) => {
                showError('Lỗi', 'Không thể xoá lớp học')
                if (errors.message) {
                    showError('Lỗi', errors.message)
                }
                callbacks.onError?.(errors)
            }
        })
    }
})
