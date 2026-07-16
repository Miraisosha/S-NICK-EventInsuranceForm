<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import { useRoute } from 'vue-router'
import { adminApi, apiMessage } from '../api'
import AdminQrModal from '../components/AdminQrModal.vue'

const eventId = String(useRoute().params.eventId)
const event = ref(null)
const members = ref([])
const loading = ref(true)
const saving = ref(false)
const showForm = ref(false)
const errorMessage = ref('')
const qrUrl = ref('')
const form = reactive({ name: '', days: 30 })
const sortKey = ref('created')
const direction = ref('desc')

const sortedMembers = computed(() => [...members.value].sort((a, b) => {
  const result = String(a[sortKey.value] ?? '').localeCompare(String(b[sortKey.value] ?? ''), 'ja')
  return direction.value === 'asc' ? result : -result
}))

function sortBy(key) {
  if (sortKey.value === key) direction.value = direction.value === 'asc' ? 'desc' : 'asc'
  else { sortKey.value = key; direction.value = 'asc' }
}
function marker(key) { return sortKey.value === key ? (direction.value === 'asc' ? ' ▲' : ' ▼') : '' }

onMounted(load)
async function load() {
  try {
    const [eventResponse, membersResponse] = await Promise.all([
      adminApi.getEvent(eventId), adminApi.listPendingMembers(eventId),
    ])
    event.value = eventResponse.data.event
    members.value = membersResponse.data.members || []
  } catch (error) {
    errorMessage.value = apiMessage(error, '加入前ユーザーを取得できませんでした。')
  } finally { loading.value = false }
}

async function createMember() {
  saving.value = true
  errorMessage.value = ''
  try {
    const { data } = await adminApi.issueMemberUrl(eventId, form)
    members.value.unshift(data.member)
    qrUrl.value = data.url
    form.name = ''
    form.days = 30
    showForm.value = false
  } catch (error) {
    errorMessage.value = apiMessage(error, '加入前ユーザーを登録できませんでした。')
  } finally { saving.value = false }
}

async function reissue(member) {
  if (!window.confirm(`${member.invited_name || '氏名未登録ユーザー'}のURLを再発行しますか？以前のURLは無効になります。`)) return
  try {
    const { data } = await adminApi.reissueMemberUrl(eventId, member.id, { days: 30 })
    const index = members.value.findIndex((item) => item.id === member.id)
    if (index >= 0) members.value[index] = data.member
    qrUrl.value = data.url
  } catch (error) {
    errorMessage.value = apiMessage(error, 'URLを再発行できませんでした。')
  }
}
</script>

<template>
  <div class="card content-card"><div class="card-body">
    <div class="admin-page-header d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
      <div><p class="brand-kicker mb-1">PENDING MEMBERS</p><h2 class="h3 fw-bold mb-0">{{ event?.event_name || '加入前ユーザー' }}</h2></div>
      <div class="admin-page-actions"><RouterLink class="btn btn-outline-secondary" :to="{ name: 'admin-event-detail', params: { eventId } }">イベント詳細へ</RouterLink><button class="btn btn-primary" @click="showForm = !showForm">新規登録</button></div>
    </div>
    <div v-if="errorMessage" class="alert alert-danger">{{ errorMessage }}</div>
    <form v-if="showForm" class="border rounded p-3 mb-4" @submit.prevent="createMember">
      <h3 class="h5">加入前ユーザー新規登録</h3>
      <div class="row g-3"><div class="col-md-8"><label class="form-label">氏名</label><input v-model.trim="form.name" class="form-control" maxlength="100" required></div><div class="col-md-4"><label class="form-label">URL有効日数</label><input v-model.number="form.days" class="form-control" type="number" min="1" max="365" required></div></div>
      <div class="text-end mt-3"><button class="btn btn-primary mobile-full" :disabled="saving">登録してQRを表示</button></div>
    </form>
    <p v-if="loading">読み込み中…</p>
    <template v-else-if="members.length"><div class="admin-mobile-sort d-md-none mb-3"><label for="pending_sort" class="form-label">並び順</label><div class="d-flex gap-2"><select id="pending_sort" v-model="sortKey" class="form-select"><option value="invited_name">氏名</option><option value="token_status">URL状態</option><option value="token_expires_at">有効期限</option><option value="created">登録日時</option></select><button class="btn btn-outline-secondary flex-shrink-0" type="button" @click="direction = direction === 'asc' ? 'desc' : 'asc'">{{ direction === 'asc' ? '昇順' : '降順' }}</button></div></div>
    <div class="table-responsive"><table class="table table-hover align-middle admin-list-table"><thead><tr>
      <th><button class="btn btn-link p-0 fw-bold text-decoration-none" @click="sortBy('invited_name')">氏名{{ marker('invited_name') }}</button></th>
      <th><button class="btn btn-link p-0 fw-bold text-decoration-none" @click="sortBy('token_status')">URL状態{{ marker('token_status') }}</button></th>
      <th><button class="btn btn-link p-0 fw-bold text-decoration-none" @click="sortBy('token_expires_at')">有効期限{{ marker('token_expires_at') }}</button></th>
      <th><button class="btn btn-link p-0 fw-bold text-decoration-none" @click="sortBy('created')">登録日時{{ marker('created') }}</button></th><th>操作</th>
    </tr></thead><tbody><tr v-for="member in sortedMembers" :key="member.id"><td data-label="氏名">{{ member.invited_name || '（氏名未登録）' }}</td><td data-label="URL状態"><span class="badge" :class="member.token_status === 'active' ? 'text-bg-success' : 'text-bg-secondary'">{{ member.token_status === 'active' ? '有効' : '期限切れ' }}</span></td><td data-label="有効期限">{{ member.token_expires_at }}</td><td data-label="登録日時">{{ member.created }}</td><td class="admin-row-action"><button class="btn btn-sm btn-outline-primary" @click="reissue(member)">URL再発行</button></td></tr></tbody></table></div></template>
    <p v-else class="text-secondary">加入前ユーザーはいません。</p>
  </div></div>
  <AdminQrModal v-if="qrUrl" :url="qrUrl" @close="qrUrl = ''" />
</template>
