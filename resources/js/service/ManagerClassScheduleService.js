import { createUniversalClassScheduleService } from './UniversalClassScheduleService'

/**
 * Service cho Lịch học (Class Schedules) - Manager version
 */
export const createManagerClassScheduleService = (toastService) => createUniversalClassScheduleService('manager', toastService)
