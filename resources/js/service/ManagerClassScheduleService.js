import { router } from '@inertiajs/vue3'

/**
 * Service cho Lịch học (Class Schedules) theo nested route:
 * /manager/classrooms/{classroom}/schedules/...
 *
 * Giao diện API và xử lý callbacks/toasts theo đúng format của ManagerClassroomService.js
 */
export const createManagerClassScheduleService = ({ showSuccess, showError }) => ({

    /**
     * Get list of schedules of a classroom with pagination/sort
     * @param {Number|String} classroomId
     * @param {Object} params  (page, per_page, sort, order, ...)
     */
    getList(classroomId, params = {}) {
        router.visit(route('manager.classrooms.schedules.index', { classroom: classroomId }), {
            method: 'get',
            data: params,
            preserveScroll: true,
            preserveState: true,
            replace: true,
            onError: () => {
                showError?.('Lỗi tải dữ liệu', 'Không thể tải danh sách lịch học')
            }
        })
    },

    /**
     * Create a new schedule
     * @param {Number|String} classroomId
     * @param {Object} data { weekday, start_time, end_time, ... }
     * @param {Object} callbacks { onSuccess, onError }
     */
    create(classroomId, data, callbacks = {}) {
        router.post(route('manager.classrooms.schedules.store', { classroom: classroomId }), data, {
            onSuccess: () => {
                callbacks.onSuccess?.()
            },
            onError: (errors) => {
                if (errors?.message) {
                    showError?.('Lỗi', errors.message)
                }
                callbacks.onError?.(errors)
            }
        })
    },

    /**
     * Update a schedule
     * @param {Number|String} classroomId
     * @param {Number|String} scheduleId
     * @param {Object} data
     * @param {Object} callbacks { onSuccess, onError }
     */
    update(classroomId, scheduleId, data, callbacks = {}) {
        router.put(
            route('manager.classrooms.schedules.update', { classroom: classroomId, schedule: scheduleId }),
            data,
            {
                onSuccess: () => {
                    callbacks.onSuccess?.()
                },
                onError: (errors) => {
                    if (errors?.message) {
                        showError?.('Lỗi', errors.message)
                    }
                    callbacks.onError?.(errors)
                }
            }
        )
    },

    /**
     * Delete a schedule
     * @param {Number|String} classroomId
     * @param {Number|String} scheduleId
     * @param {Object} callbacks { onSuccess, onError }
     */
    delete(classroomId, scheduleId, callbacks = {}) {
        if (!confirm('Xác nhận xoá lịch học này?')) return

        router.delete(
            route('manager.classrooms.schedules.destroy', { classroom: classroomId, schedule: scheduleId }),
            {
                preserveScroll: true,
                onSuccess: () => {
                    callbacks.onSuccess?.()
                },
                onError: (errors) => {
                    showError?.('Lỗi', 'Không thể xoá lịch học')
                    callbacks.onError?.(errors)
                }
            }
        )
    },
})
