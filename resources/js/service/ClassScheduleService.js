import { createUniversalClassScheduleService } from './UniversalClassScheduleService'

/**
 * Service cho Lịch học (Class Schedules) - Admin version
 */
export const createClassScheduleService = (toastService) => createUniversalClassScheduleService('admin', toastService)
