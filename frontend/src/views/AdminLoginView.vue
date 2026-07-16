<script setup>
import { computed, nextTick, reactive, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import QRCode from 'qrcode'
import { adminApi, apiMessage } from '../api'
import { setAdminUser } from '../adminAuth'

const route = useRoute()
const router = useRouter()
const credentials = reactive({ username: '', password: '' })
const code = ref('')
const phase = ref('credentials')
const provisioningUri = ref('')
const manualKey = ref('')
const qrDataUrl = ref('')
const recoveryCodes = ref([])
const recoveryCodesSaved = ref(false)
const submitting = ref(false)
const errorMessage = ref('')

const isSetup = computed(() => phase.value === 'setup')

async function login() {
  errorMessage.value = ''
  submitting.value = true
  try {
    const { data } = await adminApi.login(credentials)
    credentials.password = ''
    if (data.requiresSetup) {
      provisioningUri.value = data.provisioningUri
      manualKey.value = data.manualKey
      phase.value = 'setup'
      await nextTick()
      qrDataUrl.value = await QRCode.toDataURL(provisioningUri.value, {
        width: 240,
        margin: 1,
        errorCorrectionLevel: 'M',
      })
    } else {
      phase.value = 'verify'
    }
  } catch (error) {
    errorMessage.value = apiMessage(error, 'ログインできませんでした。')
  } finally {
    submitting.value = false
  }
}

async function verify() {
  errorMessage.value = ''
  submitting.value = true
  try {
    const { data } = await adminApi.verify(code.value)
    code.value = ''
    setAdminUser(data.user)
    if (data.recoveryCodes?.length) {
      recoveryCodes.value = data.recoveryCodes
      phase.value = 'recovery'
      return
    }
    await enterAdmin()
  } catch (error) {
    errorMessage.value = apiMessage(error, '認証コードを確認できませんでした。')
  } finally {
    submitting.value = false
  }
}

async function enterAdmin() {
  const redirect = typeof route.query.redirect === 'string' && route.query.redirect.startsWith('/admin/')
    ? route.query.redirect
    : '/admin/events'
  await router.replace(redirect)
}

function copyRecoveryCodes() {
  navigator.clipboard.writeText(recoveryCodes.value.join('\n'))
}
</script>

<template>
  <div class="card content-card admin-export-card">
    <div class="card-body">
      <p class="brand-kicker mb-2">SECURE ADMINISTRATION</p>
      <h2 class="h3 fw-bold mb-2">管理者ログイン</h2>
      <p class="text-secondary mb-4">管理者ID・パスワードと認証アプリで本人確認します。</p>

      <div v-if="errorMessage" class="alert alert-danger fw-bold" role="alert">{{ errorMessage }}</div>

      <form v-if="phase === 'credentials'" class="admin-key-panel" @submit.prevent="login">
        <div class="mb-3">
          <label for="admin_username" class="form-label">管理者ID</label>
          <input id="admin_username" v-model.trim="credentials.username" class="form-control" autocomplete="username" required autofocus>
        </div>
        <div class="mb-4">
          <label for="admin_password" class="form-label">パスワード</label>
          <input id="admin_password" v-model="credentials.password" class="form-control" type="password" autocomplete="current-password" required>
        </div>
        <div class="d-grid">
          <button class="btn btn-primary btn-lg" type="submit" :disabled="submitting">
            {{ submitting ? '確認中…' : '次へ' }}
          </button>
        </div>
      </form>

      <form v-else-if="isSetup || phase === 'verify'" class="admin-key-panel" @submit.prevent="verify">
        <template v-if="isSetup">
          <div class="alert alert-info">
            初回設定です。認証アプリでQRコードを読み取り、表示された6桁コードを入力してください。
          </div>
          <div class="text-center mb-3">
            <img v-if="qrDataUrl" :src="qrDataUrl" width="240" height="240" alt="認証アプリ登録用QRコード">
          </div>
          <details class="mb-4">
            <summary>QRコードを読み取れない場合</summary>
            <p class="small text-secondary mt-2 mb-1">認証アプリへ次のキーを手動入力してください。</p>
            <code class="user-select-all text-break">{{ manualKey }}</code>
          </details>
        </template>
        <div v-else class="alert alert-secondary">
          認証アプリに表示されている6桁コードを入力してください。端末を紛失した場合はリカバリーコードも使用できます。
        </div>
        <label for="admin_code" class="form-label">認証コード</label>
        <input
          id="admin_code"
          v-model.trim="code"
          class="form-control form-control-lg text-center"
          inputmode="numeric"
          autocomplete="one-time-code"
          maxlength="19"
          placeholder="123456"
          required
          autofocus
        >
        <div class="d-grid mt-4">
          <button class="btn btn-primary btn-lg" type="submit" :disabled="submitting">
            {{ submitting ? '確認中…' : isSetup ? '認証アプリを登録' : 'ログイン' }}
          </button>
        </div>
      </form>

      <div v-else-if="phase === 'recovery'" class="admin-key-panel">
        <div class="alert alert-warning fw-bold">
          リカバリーコードは今だけ表示されます。スマートフォンを紛失した場合に備えて安全な場所へ保存してください。
        </div>
        <div class="row g-2 mb-3 font-monospace">
          <div v-for="item in recoveryCodes" :key="item" class="col-md-6">
            <div class="border rounded bg-white p-2 text-center user-select-all">{{ item }}</div>
          </div>
        </div>
        <button class="btn btn-outline-secondary w-100 mb-3" type="button" @click="copyRecoveryCodes">コードをコピー</button>
        <div class="form-check mb-4">
          <input id="codes_saved" v-model="recoveryCodesSaved" class="form-check-input" type="checkbox">
          <label class="form-check-label" for="codes_saved">リカバリーコードを安全な場所へ保存しました</label>
        </div>
        <div class="d-grid">
          <button class="btn btn-primary btn-lg" type="button" :disabled="!recoveryCodesSaved" @click="enterAdmin">管理画面へ進む</button>
        </div>
      </div>
    </div>
  </div>
</template>
