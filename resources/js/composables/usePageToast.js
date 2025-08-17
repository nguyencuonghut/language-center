import { useToast } from 'primevue/usetoast'

export function usePageToast() {
    const toast = useToast()

    const showSuccess = (summary, detail = '') => {
        toast.add({
            severity: 'success',
            summary: summary,
            detail: detail,
            life: 3000
        })
    }

    const showError = (summary, detail = '') => {
        toast.add({
            severity: 'error',
            summary: summary,
            detail: detail,
            life: 5000
        })
    }

    const showInfo = (summary, detail = '') => {
        toast.add({
            severity: 'info',
            summary: summary,
            detail: detail,
            life: 3000
        })
    }

    const showWarn = (summary, detail = '') => {
        toast.add({
            severity: 'warn',
            summary: summary,
            detail: detail,
            life: 3000
        })
    }

    return {
        showSuccess,
        showError,
        showInfo,
        showWarn
    }
}
