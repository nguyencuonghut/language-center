import { createUniversalInvoiceService } from './UniversalInvoiceService'

/**
 * Service cho hóa đơn học phí (Invoices) - Manager version
 * Lưu ý: KHÔNG gọi toast ở FE. BE trả flash -> AppLayout.vue sẽ hiển thị.
 */
export const createManagerInvoiceService = () => createUniversalInvoiceService('manager')
