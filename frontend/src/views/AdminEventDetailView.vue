<script setup>
import { onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { adminApi, apiMessage } from '../api'
import AdminQrModal from '../components/AdminQrModal.vue'

const route = useRoute()
const router = useRouter()
const eventId = String(route.params.eventId)
const event = ref(null)
const loading = ref(true)
const busy = ref(false)
const errorMessage = ref('')
const qrUrl = ref('')
const zipPassword = ref('')

onMounted(loadEvent)

async function loadEvent() {
  try {
    const { data } = await adminApi.getEvent(eventId)
    event.value = data.event
  } catch (error) {
    errorMessage.value = apiMessage(error, 'イベントを取得できませんでした。')
  } finally {
    loading.value = false
  }
}

async function removeEvent() {
  if (!window.confirm('このイベントを削除しますか？登録済みユーザー情報は保持されます。')) return
  try {
    await adminApi.deleteEvent(eventId)
    await router.replace({ name: 'admin-events' })
  } catch (error) {
    errorMessage.value = apiMessage(error, 'イベントを削除できませんでした。')
  }
}

async function issueAnonymousUrl() {
  busy.value = true
  try {
    const { data } = await adminApi.issueMemberUrl(eventId, { name: '', days: 30 })
    qrUrl.value = data.url
    event.value.pending_count += 1
  } catch (error) {
    errorMessage.value = apiMessage(error, '新規登録URLを発行できませんでした。')
  } finally {
    busy.value = false
  }
}

async function downloadZip() {
  busy.value = true
  zipPassword.value = ''
  try {
    const response = await adminApi.downloadEventRegistrations(eventId)
    const url = URL.createObjectURL(response.data)
    const link = document.createElement('a')
    link.href = url
    link.download = `snick-event-${eventId}-members.zip`
    document.body.appendChild(link)
    link.click()
    link.remove()
    URL.revokeObjectURL(url)
    zipPassword.value = response.headers['x-zip-password'] || ''
  } catch (error) {
    errorMessage.value = apiMessage(error, 'CSVを出力できませんでした。')
  } finally {
    busy.value = false
  }
}

async function copyPassword() {
  await navigator.clipboard.writeText(zipPassword.value)
}
</script>

<template>
  <div class="card content-card"><div class="card-body">
    <p v-if="loading">読み込み中…</p>
    <template v-else-if="event">
      <div class="admin-page-header d-flex flex-wrap justify-content-between gap-2 mb-4">
        <div><p class="brand-kicker mb-1">EVENT DETAIL</p><h2 class="h3 fw-bold mb-0">{{ event.event_name }}</h2></div>
        <div class="admin-page-actions"><RouterLink class="btn btn-outline-secondary" :to="{ name: 'admin-events' }">一覧へ</RouterLink><RouterLink class="btn btn-outline-primary" :to="{ name: 'admin-event-edit', params: { eventId } }">修正</RouterLink><button class="btn btn-outline-danger" @click="removeEvent">削除</button></div>
      </div>
      <div v-if="errorMessage" class="alert alert-danger">{{ errorMessage }}</div>
      <dl class="row border rounded p-3"><dt class="col-4 col-sm-3">開催日</dt><dd class="col-8 col-sm-9">{{ event.event_date }}</dd><dt class="col-4 col-sm-3">場所</dt><dd class="col-8 col-sm-9">{{ event.location }}</dd></dl><div class="admin-stat-grid"><div class="admin-stat"><div class="admin-stat-label">加入前</div><div class="admin-stat-value">{{ event.pending_count }}名</div></div><div class="admin-stat"><div class="admin-stat-label">加入済み</div><div class="admin-stat-value">{{ event.completed_count }}名</div></div></div>
      <div class="row g-3 mt-2">
        <div class="col-md-6"><RouterLink class="btn btn-outline-primary w-100" :to="{ name: 'admin-event-pending', params: { eventId } }">加入前ユーザー</RouterLink></div>
        <div class="col-md-6"><RouterLink class="btn btn-outline-primary w-100" :to="{ name: 'admin-event-members', params: { eventId } }">加入済みユーザー</RouterLink></div>
        <div class="col-md-6"><button class="btn btn-primary w-100" :disabled="busy" @click="issueAnonymousUrl">新規ユーザーURL発行</button></div>
        <div class="col-md-6"><button class="btn btn-primary w-100" :disabled="busy || event.completed_count === 0" @click="downloadZip">CSV（暗号化ZIP）出力</button></div>
      </div>
      <div v-if="zipPassword" class="alert alert-warning mt-4">
        <p class="fw-bold mb-2">ZIP解凍パスワード（一度だけ表示）</p><code class="fs-5">{{ zipPassword }}</code>
        <button class="btn btn-sm btn-outline-dark ms-3" @click="copyPassword">コピー</button>
        <p class="small mb-0 mt-2">ZIPとは別の経路で受取人へ伝えてください。閉じた後は再表示できません。</p>
      </div>
    </template>
    <div v-else class="alert alert-danger">{{ errorMessage }}</div>
  </div></div>
  <AdminQrModal v-if="qrUrl" :url="qrUrl" @close="qrUrl = ''" />
</template>
