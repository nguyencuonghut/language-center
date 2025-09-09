import axios from 'axios'

export const HolidayServices = {
  fetchBranches() {
    return axios.get('/api/branches')
  },
  fetchClassrooms(branchId = null) {
    return axios.get('/api/classrooms', { params: { branch_id: branchId } })
  },
  fetchHolidays(params = {}) {
    return axios.get('/api/holidays', { params })
  },
  createHoliday(data) {
    return axios.post('/api/holidays', data)
  },
  updateHoliday(id, data) {
    return axios.put(`/api/holidays/${id}`, data)
  },
  deleteHoliday(id) {
    return axios.delete(`/api/holidays/${id}`)
  }
}
