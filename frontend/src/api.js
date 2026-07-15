import axios from 'axios'

const client = axios.create({
  baseURL: import.meta.env.VITE_API_BASE_URL || '/api',
  headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
  timeout: 15000,
})

export const invitationApi = {
  get: (token) => client.get(`/invitations/${encodeURIComponent(token)}`),
  validate: (token, data) => client.post(`/invitations/${encodeURIComponent(token)}/validate`, data),
  submit: (token, data) => client.post(`/invitations/${encodeURIComponent(token)}/submit`, data),
}

export const adminApi = {
  downloadRegistrations: (key) => client.get('/admin/registrations.csv', {
    headers: { Authorization: `Bearer ${key}` },
    responseType: 'blob',
  }),
  listEvents: (key) => client.get('/admin/events', {
    headers: { Authorization: `Bearer ${key}` },
  }),
  createEvent: (key, data) => client.post('/admin/events', data, {
    headers: { Authorization: `Bearer ${key}` },
  }),
}

export function apiMessage(error, fallback = '通信に失敗しました。時間をおいて再度お試しください。') {
  return error?.response?.data?.message || fallback
}
