import { createUniversalInvoiceService } from './UniversalInvoiceService'
import { createUniversalClassroomService } from './UniversalClassroomService'
import { createUniversalEnrollmentService } from './UniversalEnrollmentService'
import { createUniversalClassScheduleService } from './UniversalClassScheduleService'

/**
 * Factory để tạo tất cả services cho một role cụ thể
 * Giúp giảm thiểu code duplication và dễ maintain
 * 
 * @param {string} role - 'admin' hoặc 'manager'
 * @param {Object} toastService - { showSuccess, showError }
 * @returns {Object} Object chứa tất cả services
 */
export const createServicesForRole = (role, toastService = {}) => ({
  invoice: createUniversalInvoiceService(role),
  classroom: createUniversalClassroomService(role, toastService),
  enrollment: createUniversalEnrollmentService(role, toastService),
  classSchedule: createUniversalClassScheduleService(role, toastService),
})

/**
 * Helper functions cho từng role
 */
export const createAdminServices = (toastService) => createServicesForRole('admin', toastService)
export const createManagerServices = (toastService) => createServicesForRole('manager', toastService)

/**
 * Backward compatibility - các functions cũ vẫn hoạt động
 */
export { createUniversalInvoiceService, createUniversalClassroomService, createUniversalEnrollmentService, createUniversalClassScheduleService }
