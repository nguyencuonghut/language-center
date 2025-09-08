import { createUniversalClassroomService } from './UniversalClassroomService'

/**
 * Service cho quản lý lớp học (Classrooms) - Admin version
 */
export const createClassroomService = (toastService) => createUniversalClassroomService('admin', toastService)
