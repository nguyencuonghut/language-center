import { router } from '@inertiajs/vue3'

export const createRoomService = ({ showSuccess, showError }) => ({
    /**
     * Get list of rooms with pagination and filters
     */
    getList(params = {}) {
        router.visit(route('manager.rooms.index', params), {
            preserveScroll: true,
            preserveState: true,
            onError: (errors) => {
                showError('Lỗi tải dữ liệu', 'Không thể tải danh sách phòng học')
            }
        })
    },

    /**
     * Create a new room
     */
    create(data, callbacks = {}) {
        router.post(route('manager.rooms.store'), data, {
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
     * Update a room
     */
    update(id, data, callbacks = {}) {
        router.put(route('manager.rooms.update', id), data, {
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
     * Delete a room
     */
    delete(id, callbacks = {}) {
        if (!confirm('Xác nhận xoá phòng học này?')) return

        router.delete(route('manager.rooms.destroy', id), {
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
    }
})
