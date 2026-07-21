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
const showBulkForm = ref(false)
const errorMessage = ref('')
const successMessage = ref('')
const qrUrl = ref('')
const bulkResults = ref([])
const registeredMember = ref(null)
const form = reactive({ name: '', days: 30 })
const bulkForm = reactive({ text: '', days: 30 })
const sortKey = ref('created')
const direction = ref('desc')

const bulkNameCount = computed(() => bulkForm.text
  .split(/\r\n|\r|\n/)
  .map((name) => name.trim())
  .filter(Boolean).length)

const sortedMembers = computed(() => [...members.value].sort((a, b) => {
  const result = String(a[sortKey.value] ?? '').localeCompare(String(b[sortKey.value] ?? ''), 'ja')
  return direction.value === 'asc' ? result : -result
}))

function sortBy(key) {
  if (sortKey.value === key) direction.value = direction.value === 'asc' ? 'desc' : 'asc'
  else { sortKey.value = key; direction.value = 'asc' }
}
function marker(key) { return sortKey.value === key ? (direction.value === 'asc' ? ' ▲' : ' ▼') : '' }

function toggleSingleForm() {
  registeredMember.value = null
  showForm.value = !showForm.value
  showBulkForm.value = false
}

function toggleBulkForm() {
  registeredMember.value = null
  showBulkForm.value = !showBulkForm.value
  showForm.value = false
}

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
  successMessage.value = ''
  try {
    const { data } = await adminApi.issueMemberUrl(eventId, form)
    members.value.unshift(data.member)
    registeredMember.value = data.member
    form.name = ''
    form.days = 30
    showForm.value = false
  } catch (error) {
    errorMessage.value = apiMessage(error, '加入前ユーザーを登録できませんでした。')
  } finally { saving.value = false }
}

async function createMembersBulk() {
  saving.value = true
  errorMessage.value = ''
  successMessage.value = ''
  bulkResults.value = []
  try {
    const { data } = await adminApi.bulkIssueMemberUrls(eventId, bulkForm)
    members.value.unshift(...data.members.map(({ url, ...member }) => member))
    bulkResults.value = data.members
    successMessage.value = `${data.count}名の加入前ユーザーを登録しました。`
    bulkForm.text = ''
    bulkForm.days = 30
    showBulkForm.value = false
  } catch (error) {
    errorMessage.value = apiMessage(error, '加入前ユーザーを一括登録できませんでした。')
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
      <div class="admin-page-actions"><RouterLink class="btn btn-outline-secondary" :to="{ name: 'admin-event-detail', params: { eventId } }">イベント詳細へ</RouterLink><button class="btn btn-primary" @click="toggleSingleForm">1名登録</button><button class="btn btn-outline-primary" @click="toggleBulkForm">テキスト一括登録</button></div>
    </div>
    <div v-if="errorMessage" class="alert alert-danger">{{ errorMessage }}</div>
    <section v-if="registeredMember" class="border rounded p-4 text-center">
      <p class="brand-kicker mb-2">REGISTRATION COMPLETED</p>
      <h3 class="h4 fw-bold">加入前ユーザーの登録が完了しました</h3>
      <dl class="row text-start mx-auto my-4" style="max-width: 32rem">
        <dt class="col-sm-5">氏名</dt><dd class="col-sm-7">{{ registeredMember.invited_name }}</dd>
        <dt class="col-sm-5">URL有効期限</dt><dd class="col-sm-7">{{ registeredMember.token_expires_at }}</dd>
      </dl>
      <div class="d-flex flex-column flex-sm-row justify-content-center gap-2">
        <button type="button" class="btn btn-outline-secondary" @click="registeredMember = null">一覧へ戻る</button>
        <button type="button" class="btn btn-primary" @click="registeredMember = null; showForm = true">続けて登録</button>
      </div>
    </section>
    <template v-else>
    <form v-if="showForm" class="border rounded p-3 mb-4" @submit.prevent="createMember">
      <h3 class="h5">加入前ユーザー新規登録</h3>
      <div class="row g-3"><div class="col-md-8"><label class="form-label">氏名</label><input v-model.trim="form.name" class="form-control" maxlength="100" required></div><div class="col-md-4"><label class="form-label">URL有効日数</label><input v-model.number="form.days" class="form-control" type="number" min="1" max="365" required></div></div>
      <div class="text-end mt-3"><button class="btn btn-primary mobile-full" :disabled="saving">{{ saving ? '登録中…' : '登録する' }}</button></div>
    </form>
    <form v-if="showBulkForm" class="border rounded p-3 mb-4" @submit.prevent="createMembersBulk">
      <h3 class="h5">加入前ユーザーのテキスト一括登録</h3>
      <p class="small text-secondary">氏名を1人1行で入力してください。空行は無視されます。</p>
      <div class="row g-3">
        <div class="col-md-8"><label for="bulk_names" class="form-label">氏名一覧</label><textarea id="bulk_names" v-model="bulkForm.text" class="form-control" rows="10" maxlength="50500" placeholder="山田 太郎&#10;佐藤 花子" required></textarea><div class="form-text">登録対象：{{ bulkNameCount }}名（最大500名）</div></div>
        <div class="col-md-4"><label for="bulk_days" class="form-label">URL有効日数</label><input id="bulk_days" v-model.number="bulkForm.days" class="form-control" type="number" min="1" max="365" required></div>
      </div>
      <div class="text-end mt-3"><button class="btn btn-primary mobile-full" :disabled="saving || bulkNameCount === 0 || bulkNameCount > 500">{{ saving ? '登録中…' : `${bulkNameCount}名を登録` }}</button></div>
    </form>
    <div v-if="successMessage" class="alert alert-success">
      <p class="mb-2">{{ successMessage }}</p>
      <div v-if="bulkResults.length" class="d-flex flex-wrap gap-2">
        <button v-for="result in bulkResults" :key="result.id" type="button" class="btn btn-sm btn-outline-success" @click="qrUrl = result.url">{{ result.invited_name }}のQRを表示</button>
      </div>
    </div>
    <p v-if="loading">読み込み中…</p>
    <template v-else-if="members.length"><div class="admin-mobile-sort d-md-none mb-3"><label for="pending_sort" class="form-label">並び順</label><div class="d-flex gap-2"><select id="pending_sort" v-model="sortKey" class="form-select"><option value="invited_name">氏名</option><option value="token_status">URL状態</option><option value="token_expires_at">有効期限</option><option value="created">登録日時</option></select><button class="btn btn-outline-secondary flex-shrink-0" type="button" @click="direction = direction === 'asc' ? 'desc' : 'asc'">{{ direction === 'asc' ? '昇順' : '降順' }}</button></div></div>
    <div class="table-responsive"><table class="table table-hover align-middle admin-list-table"><thead><tr>
      <th><button class="btn btn-link p-0 fw-bold text-decoration-none" @click="sortBy('invited_name')">氏名{{ marker('invited_name') }}</button></th>
      <th><button class="btn btn-link p-0 fw-bold text-decoration-none" @click="sortBy('token_status')">URL状態{{ marker('token_status') }}</button></th>
      <th><button class="btn btn-link p-0 fw-bold text-decoration-none" @click="sortBy('token_expires_at')">有効期限{{ marker('token_expires_at') }}</button></th>
      <th><button class="btn btn-link p-0 fw-bold text-decoration-none" @click="sortBy('created')">登録日時{{ marker('created') }}</button></th><th>操作</th>
    </tr></thead><tbody><tr v-for="member in sortedMembers" :key="member.id"><td data-label="氏名">{{ member.invited_name || '（氏名未登録）' }}</td><td data-label="URL状態"><span class="badge" :class="member.token_status === 'active' ? 'text-bg-success' : 'text-bg-secondary'">{{ member.token_status === 'active' ? '有効' : '期限切れ' }}</span></td><td data-label="有効期限">{{ member.token_expires_at }}</td><td data-label="登録日時">{{ member.created }}</td><td class="admin-row-action"><button class="btn btn-sm btn-outline-primary" @click="reissue(member)">URL再発行</button></td></tr></tbody></table></div></template>
    <p v-else class="text-secondary">加入前ユーザーはいません。</p>
    </template>
  </div></div>
  <AdminQrModal v-if="qrUrl" :url="qrUrl" @close="qrUrl = ''" />
</template>
