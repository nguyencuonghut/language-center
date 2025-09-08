import { createUniversalEnrollmentService } from './UniversalEnrollmentService'

/**
 * Service cho ghi danh học viên (Enrollments) - Manager version
 */
export const createManagerEnrollmentService = (toastService) => createUniversalEnrollmentService('manager', toastService)
