import { router } from '@inertiajs/vue3'

export const createClassroomService = ({ showSuccess, showError }) => ({
    /**
     * Get list of classrooms with pagination and filters
     */
    getList(params = {}) {
        router.visit(route('admin.classrooms.index', params), {
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
        router.post(route('admin.classrooms.store'), formattedData, {
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
        router.put(route('admin.classrooms.update', id), formattedData, {
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

        router.delete(route('admin.classrooms.destroy', id), {
            preserveScroll: true,
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
     * Archive a classroom
                showSuccess('Thành công', 'Đã xoá lớp học')
            },
            onError: (errors) => {
                showError('Lỗi', 'Không thể xoá lớp học')
            }
        })
    },

    /**
     * Archive a classroom
     */
    archive(id) {
        if (!confirm('Xác nhận lưu trữ lớp học này?')) return

        router.put(route('admin.classrooms.archive', id), {}, {
            preserveScroll: true,
            onSuccess: () => {
                showSuccess('Thành công', 'Đã chuyển lớp học vào kho lưu trữ')
            },
            onError: (errors) => {
                showError('Lỗi', 'Không thể lưu trữ lớp học')
            }
        })
    },

    /**
     * Restore an archived classroom
     */
    restore(id) {
        router.put(route('admin.classrooms.restore', id), {}, {
            preserveScroll: true,
            onSuccess: () => {
                showSuccess('Thành công', 'Đã khôi phục lớp học')
            },
            onError: (errors) => {
                showError('Lỗi', 'Không thể khôi phục lớp học')
            }
        })
    }
})
