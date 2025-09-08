import { createUniversalEnrollmentService } from './UniversalEnrollmentService'

/**
 * Service cho ghi danh học viên (Enrollments) - Admin version
 */
export const createEnrollmentService = (toastService) => createUniversalEnrollmentService('admin', toastService)
