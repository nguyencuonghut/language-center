import { router } from '@inertiajs/vue3'

export const createBranchService = ({ showSuccess, showError }) => ({
    /**
     * Get list of branches with pagination and filters
     */
    getList(params = {}) {
        router.visit(route('admin.branches.index', params), {
            preserveScroll: true,
            preserveState: true,
            onError: (errors) => {
                showError('Lỗi tải dữ liệu', 'Không thể tải danh sách chi nhánh')
            }
        })
    },

    /**
     * Create a new branch
     */
    create(data, callbacks = {}) {
        router.post(route('admin.branches.store'), data, {
            onSuccess: () => {
                showSuccess('Thành công', 'Đã tạo chi nhánh mới')
                callbacks.onSuccess?.()
            },
            onError: (errors) => {
                showError('Lỗi', 'Không thể tạo chi nhánh')
                callbacks.onError?.(errors)
            }
        })
    },

    /**
     * Update a branch
     */
    update(id, data, callbacks = {}) {
        router.put(route('admin.branches.update', id), data, {
            onSuccess: () => {
                callbacks.onSuccess?.()
            },
            onError: (errors) => {
                callbacks.onError?.(errors)
            }
        })
    },

    /**
     * Delete a branch
     */
    delete(id, callbacks = {}) {
        if (!confirm('Xác nhận xoá chi nhánh này?')) return

        router.delete(route('admin.branches.destroy', id), {
            preserveScroll: true,
            onSuccess: () => {
                // Không xử lý toast ở đây nữa vì đã có AppLayout xử lý
                callbacks.onSuccess?.()
            },
            onError: (errors) => {
                // Chỉ xử lý các lỗi không phải từ flash message
                if (errors?.message) {
                    showError('Lỗi', errors.message)
                }
                callbacks.onError?.(errors)
            },
            onError: (errors) => {
                // Xử lý validation hoặc server error
                if (errors.message) {
                    showError('Không thể xoá', errors.message)
                } else {
                    showError('Lỗi', 'Không thể xoá chi nhánh vì đang được sử dụng')
                }
                callbacks.onError?.(errors)
            }
        })
    }
})
