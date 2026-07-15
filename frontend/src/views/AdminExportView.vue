<script setup>
import { ref } from 'vue'
import { adminApi } from '../api'

const exportKey = ref('')
const downloading = ref(false)
const errorMessage = ref('')
const successMessage = ref('')

async function downloadZip() {
  errorMessage.value = ''
  successMessage.value = ''

  if (!exportKey.value) {
    errorMessage.value = '出力キーを入力してください。'
    return
  }

  downloading.value = true
  try {
    const response = await adminApi.downloadRegistrations(exportKey.value)
    const url = URL.createObjectURL(response.data)
    const link = document.createElement('a')
    const timestamp = new Date().toISOString().replace(/[-:]/g, '').slice(0, 15)
    link.href = url
    link.download = `snick-insurance-members-${timestamp}.zip`
    document.body.appendChild(link)
    link.click()
    link.remove()
    URL.revokeObjectURL(url)
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
</script>

<template>
  <div class="card content-card admin-export-card">
    <div class="card-body">
      <div class="d-flex flex-wrap justify-content-between align-items-start gap-2">
        <p class="brand-kicker mb-2">ADMINISTRATION</p>
        <RouterLink class="btn btn-outline-secondary" to="/admin/events">イベント管理へ</RouterLink>
      </div>
      <h2 class="h3 fw-bold mb-2">登録データのCSV出力</h2>
      <p class="text-secondary mb-4">登録が完了している加入者情報を、パスワード付きZIP形式でダウンロードします。</p>

      <div class="alert alert-warning" role="note">
        個人情報を含みます。ZIPの解凍パスワードは管理者から別途受け取り、ファイルと分けて管理してください。
      </div>

      <div v-if="errorMessage" class="alert alert-danger fw-bold" role="alert">{{ errorMessage }}</div>
      <div v-if="successMessage" class="alert alert-success fw-bold" role="status">{{ successMessage }}</div>

      <form class="admin-key-panel" @submit.prevent="downloadZip">
        <label for="export_key" class="form-label">出力キー</label>
        <input
          id="export_key"
          v-model="exportKey"
          class="form-control"
          type="password"
          autocomplete="current-password"
          aria-describedby="export-key-help"
          required
        >
        <div id="export-key-help" class="form-text">管理者から共有された出力専用キーを入力してください。</div>

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
