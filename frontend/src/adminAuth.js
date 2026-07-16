import { reactive } from 'vue'
import { adminApi } from './api'

export const adminState = reactive({
  checked: false,
  user: null,
})

export async function ensureAdminSession() {
  if (adminState.checked) return Boolean(adminState.user)

  try {
    const { data } = await adminApi.session()
    adminState.user = data.user || null
  } catch {
    adminState.user = null
  } finally {
    adminState.checked = true
  }

  return Boolean(adminState.user)
}

export function setAdminUser(user) {
  adminState.user = user
  adminState.checked = true
}

export async function logoutAdmin() {
  try {
    await adminApi.logout()
  } finally {
    adminState.user = null
    adminState.checked = true
  }
}
