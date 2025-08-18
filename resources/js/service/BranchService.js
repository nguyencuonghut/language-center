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
                showSuccess('Thành công', 'Đã cập nhật chi nhánh')
                callbacks.onSuccess?.()
            },
            onError: (errors) => {
                showError('Lỗi', 'Không thể cập nhật chi nhánh')
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
                showSuccess('Thành công', 'Đã xoá chi nhánh')
                callbacks.onSuccess?.()
            },
            onError: (errors) => {
                showError('Lỗi', 'Không thể xoá chi nhánh')
                callbacks.onError?.(errors)
            }
        })
    }
})
