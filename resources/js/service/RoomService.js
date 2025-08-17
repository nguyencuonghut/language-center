import { router } from '@inertiajs/vue3'

export const createRoomService = ({ showSuccess, showError }) => ({
    /**
     * Get list of rooms with pagination and filters
     */
    getList(params = {}) {
        router.visit(route('admin.rooms.index', params), {
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
    create(data) {
        router.post(route('admin.rooms.store'), data, {
            onSuccess: () => {
                showSuccess('Thành công', 'Đã tạo phòng học mới')
            },
            onError: (errors) => {
                showError('Lỗi', 'Không thể tạo phòng học')
            }
        })
    },

    /**
     * Update a room
     */
    update(id, data) {
        router.put(route('admin.rooms.update', id), data, {
            onSuccess: () => {
                showSuccess('Thành công', 'Đã cập nhật phòng học')
            },
            onError: (errors) => {
                showError('Lỗi', 'Không thể cập nhật phòng học')
            }
        })
    },

    /**
     * Delete a room
     */
    delete(id) {
        if (!confirm('Xác nhận xoá phòng học này?')) return

        router.delete(route('admin.rooms.destroy', id), {
            preserveScroll: true,
            onSuccess: () => {
                showSuccess('Thành công', 'Đã xoá phòng học')
            },
            onError: (errors) => {
                showError('Lỗi', 'Không thể xoá phòng học')
            }
        })
    }
})
