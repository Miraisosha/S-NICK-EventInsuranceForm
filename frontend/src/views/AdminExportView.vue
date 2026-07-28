<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { adminApi } from '../api'
import { logoutAdmin } from '../adminAuth'

const router = useRouter()
const downloading = ref(false)
const errorMessage = ref('')
const successMessage = ref('')
const zipPassword = ref('')

async function downloadZip() {
  errorMessage.value = ''
  successMessage.value = ''
  zipPassword.value = ''

  downloading.value = true
  try {
    const response = await adminApi.downloadRegistrations()
    const url = URL.createObjectURL(response.data)
    const link = document.createElement('a')
    const timestamp = new Date().toISOString().replace(/[-:]/g, '').slice(0, 15)
    link.href = url
    link.download = `snick-insurance-members-${timestamp}.zip`
    document.body.appendChild(link)
    link.click()
    link.remove()
    URL.revokeObjectURL(url)
    zipPassword.value = response.headers['x-zip-password'] || ''
    successMessage.value = '登録済みデータのパスワード付きZIPをダウンロードしました。'
  } catch (error) {
    let message = 'パスワード付きZIPをダウンロードできませんでした。'
    if (error?.response?.data instanceof Blob) {
      try {
        const payload = JSON.parse(await error.response.data.text())
        message = payload.message || message
      } catch {
        // JSON以外のエラーでは既定メッセージを使用します。
      }
    }
    errorMessage.value = message
  } finally {
    downloading.value = false
  }
}

async function logout() {
  await logoutAdmin()
  await router.replace('/admin/login')
}
</script>

<template>
  <div class="card content-card admin-export-card">
    <div class="card-body">
      <div class="d-flex flex-wrap justify-content-between align-items-start gap-2">
        <p class="brand-kicker mb-2">ADMINISTRATION</p>
        <div class="d-flex gap-2">
          <RouterLink class="btn btn-outline-secondary" to="/admin/events">イベント管理へ</RouterLink>
          <button class="btn btn-outline-danger" type="button" @click="logout">ログアウト</button>
        </div>
      </div>
      <h2 class="h3 fw-bold mb-2">登録データのCSV出力</h2>
      <p class="text-secondary mb-4">登録が完了している加入者情報を、パスワード付きZIP形式でダウンロードします。</p>

      <div class="alert alert-warning" role="note">
        個人情報を含みます。ZIPの解凍パスワードは管理者から別途受け取り、ファイルと分けて管理してください。
      </div>

      <div v-if="errorMessage" class="alert alert-danger fw-bold" role="alert">{{ errorMessage }}</div>
      <div v-if="successMessage" class="alert alert-success fw-bold" role="status">{{ successMessage }}</div>
      <div v-if="zipPassword" class="alert alert-warning" role="status">
        <p class="fw-bold mb-2">ZIP解凍パスワード（一度だけ表示）</p>
        <code class="fs-5">{{ zipPassword }}</code>
        <button class="btn btn-sm btn-outline-dark ms-3" type="button" @click="navigator.clipboard.writeText(zipPassword)">コピー</button>
        <p class="small mt-2 mb-0">ZIPとは別の経路で共有してください。この画面を離れると再表示できません。</p>
      </div>

      <form class="admin-key-panel" @submit.prevent="downloadZip">
        <p class="mb-0">ログイン中の管理者アカウントでCSVを出力します。</p>
        <div class="d-grid mt-4">
          <button class="btn btn-primary btn-lg" type="submit" :disabled="downloading">
            <span v-if="downloading" class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>
            パスワード付きZIPをダウンロード
          </button>
        </div>
      </form>
    </div>
  </div>
</template>
