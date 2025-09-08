import { createUniversalClassroomService } from './UniversalClassroomService'

/**
 * Service cho quản lý lớp học (Classrooms) - Manager version
 */
export const createManagerClassroomService = (toastService) => createUniversalClassroomService('manager', toastService)
