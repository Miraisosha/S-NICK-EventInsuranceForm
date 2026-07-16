import axios from 'axios'

const client = axios.create({
  baseURL: import.meta.env.VITE_API_BASE_URL || '/api',
  headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
  timeout: 15000,
  withCredentials: true,
})

let csrfToken = ''

async function ensureCsrfToken() {
  if (!csrfToken) {
    const { data } = await client.get('/admin/auth/csrf')
    csrfToken = data.csrfToken
  }
  return csrfToken
}

async function adminPost(url, data = {}) {
  const token = await ensureCsrfToken()
  return client.post(url, data, { headers: { 'X-CSRF-Token': token } })
}

async function adminWrite(method, url, data = {}) {
  const token = await ensureCsrfToken()
  return client.request({ method, url, data, headers: { 'X-CSRF-Token': token } })
}

export const invitationApi = {
  get: (token) => client.get(`/invitations/${encodeURIComponent(token)}`),
  validate: (token, data) => client.post(`/invitations/${encodeURIComponent(token)}/validate`, data),
  submit: (token, data) => client.post(`/invitations/${encodeURIComponent(token)}/submit`, data),
}

export const adminApi = {
  session: () => client.get('/admin/auth/session'),
  login: (credentials) => adminPost('/admin/auth/login', credentials),
  verify: (code) => adminPost('/admin/auth/verify', { code }),
  logout: () => adminPost('/admin/auth/logout'),
  downloadRegistrations: () => client.get('/admin/registrations.csv', {
    responseType: 'blob',
  }),
  listEvents: () => client.get('/admin/events'),
  createEvent: (data) => adminPost('/admin/events', data),
  getEvent: (eventId) => client.get(`/admin/events/${eventId}`),
  updateEvent: (eventId, data) => adminWrite('put', `/admin/events/${eventId}`, data),
  deleteEvent: (eventId) => adminWrite('delete', `/admin/events/${eventId}`),
  listPendingMembers: (eventId) => client.get(`/admin/events/${eventId}/pending`),
  issueMemberUrl: (eventId, data) => adminPost(`/admin/events/${eventId}/pending`, data),
  reissueMemberUrl: (eventId, memberId, data) => adminPost(`/admin/events/${eventId}/pending/${memberId}/reissue`, data),
  listCompletedMembers: (eventId) => client.get(`/admin/events/${eventId}/members`),
  getCompletedMember: (eventId, memberId) => client.get(`/admin/events/${eventId}/members/${memberId}`),
  downloadEventRegistrations: (eventId) => client.get(`/admin/events/${eventId}/registrations.zip`, {
    responseType: 'blob',
  }),
}

export function apiMessage(error, fallback = '通信に失敗しました。時間をおいて再度お試しください。') {
  return error?.response?.data?.message || fallback
}
