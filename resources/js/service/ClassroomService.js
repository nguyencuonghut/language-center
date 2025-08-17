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

    /**
     * Create a new classroom
     */
    create(data) {
        router.post(route('admin.classrooms.store'), data, {
            onSuccess: () => {
                showSuccess('Thành công', 'Đã tạo lớp học mới')
            },
            onError: (errors) => {
                showError('Lỗi', 'Không thể tạo lớp học')
            }
        })
    },

    /**
     * Update a classroom
     */
    update(id, data) {
        router.put(route('admin.classrooms.update', id), data, {
            onSuccess: () => {
                showSuccess('Thành công', 'Đã cập nhật lớp học')
            },
            onError: (errors) => {
                showError('Lỗi', 'Không thể cập nhật lớp học')
            }
        })
    },

    /**
     * Delete a classroom
     */
    delete(id) {
        if (!confirm('Xác nhận xoá lớp học này?')) return

        router.delete(route('admin.classrooms.destroy', id), {
            preserveScroll: true,
            onSuccess: () => {
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
