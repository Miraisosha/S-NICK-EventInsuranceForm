<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import { adminApi, apiMessage } from '../api'

const eventId = String(useRoute().params.eventId)
const event = ref(null)
const members = ref([])
const selected = ref(null)
const errorMessage = ref('')
const loading = ref(true)
const sortKey = ref('submitted_at')
const direction = ref('desc')
const sortedMembers = computed(() => [...members.value].sort((a, b) => {
  const result = String(a[sortKey.value] ?? '').localeCompare(String(b[sortKey.value] ?? ''), 'ja')
  return direction.value === 'asc' ? result : -result
}))
function sortBy(key) { if (sortKey.value === key) direction.value = direction.value === 'asc' ? 'desc' : 'asc'; else { sortKey.value = key; direction.value = 'asc' } }
function marker(key) { return sortKey.value === key ? (direction.value === 'asc' ? ' ▲' : ' ▼') : '' }

onMounted(async () => {
  try {
    const [eventResponse, membersResponse] = await Promise.all([adminApi.getEvent(eventId), adminApi.listCompletedMembers(eventId)])
    event.value = eventResponse.data.event
    members.value = membersResponse.data.members || []
  } catch (error) { errorMessage.value = apiMessage(error, '加入済みユーザーを取得できませんでした。') }
  finally { loading.value = false }
})

async function showDetail(member) {
  try { selected.value = (await adminApi.getCompletedMember(eventId, member.id)).data.member }
  catch (error) { errorMessage.value = apiMessage(error, 'ユーザー詳細を取得できませんでした。') }
}
</script>

<template>
  <div class="card content-card"><div class="card-body">
    <div class="admin-page-header d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4"><div><p class="brand-kicker mb-1">COMPLETED MEMBERS</p><h2 class="h3 fw-bold mb-0">{{ event?.event_name || '加入済みユーザー' }}</h2></div><div class="admin-page-actions"><RouterLink class="btn btn-outline-secondary" :to="{ name: 'admin-event-detail', params: { eventId } }">イベント詳細へ</RouterLink></div></div>
    <div v-if="errorMessage" class="alert alert-danger">{{ errorMessage }}</div><p v-if="loading">読み込み中…</p>
    <template v-else-if="members.length"><div class="admin-mobile-sort d-md-none mb-3"><label for="member_sort" class="form-label">並び順</label><div class="d-flex gap-2"><select id="member_sort" v-model="sortKey" class="form-select"><option value="full_name">氏名</option><option value="full_name_kana">フリガナ</option><option value="email">メール</option><option value="submitted_at">登録日時</option></select><button class="btn btn-outline-secondary flex-shrink-0" type="button" @click="direction = direction === 'asc' ? 'desc' : 'asc'">{{ direction === 'asc' ? '昇順' : '降順' }}</button></div></div>
    <div class="table-responsive"><table class="table table-hover align-middle admin-list-table"><thead><tr>
      <th><button class="btn btn-link p-0 fw-bold text-decoration-none" @click="sortBy('full_name')">氏名{{ marker('full_name') }}</button></th>
      <th><button class="btn btn-link p-0 fw-bold text-decoration-none" @click="sortBy('full_name_kana')">フリガナ{{ marker('full_name_kana') }}</button></th>
      <th><button class="btn btn-link p-0 fw-bold text-decoration-none" @click="sortBy('email')">メール{{ marker('email') }}</button></th>
      <th><button class="btn btn-link p-0 fw-bold text-decoration-none" @click="sortBy('submitted_at')">登録日時{{ marker('submitted_at') }}</button></th>
    </tr></thead><tbody><tr v-for="member in sortedMembers" :key="member.id" role="button" tabindex="0" @click="showDetail(member)" @keydown.enter="showDetail(member)"><td data-label="氏名"><button class="btn btn-link p-0 text-start">{{ member.full_name }}</button></td><td data-label="フリガナ">{{ member.full_name_kana }}</td><td data-label="メール">{{ member.email }}</td><td data-label="登録日時">{{ member.submitted_at }}</td></tr></tbody></table></div></template>
    <p v-else class="text-secondary">加入済みユーザーはいません。</p>
  </div></div>
  <div v-if="selected" class="modal d-block" role="dialog" aria-modal="true"><div class="modal-dialog modal-lg modal-dialog-centered"><div class="modal-content"><div class="modal-header"><h2 class="modal-title h5">加入済みユーザー詳細</h2><button class="btn-close" @click="selected = null"></button></div><div class="modal-body"><dl class="row mb-0"><template v-for="([label, value]) in [['氏名', selected.full_name], ['フリガナ', selected.full_name_kana], ['生年月日', selected.birth_date], ['電話番号', selected.phone], ['メールアドレス', selected.email], ['郵便番号', selected.postal_code], ['住所', `${selected.prefecture || ''}${selected.city || ''}${selected.street_address || ''} ${selected.building || ''}`], ['登録日時', selected.submitted_at]]" :key="label"><dt class="col-sm-4">{{ label }}</dt><dd class="col-sm-8">{{ value }}</dd></template></dl></div><div class="modal-footer"><button class="btn btn-secondary" @click="selected = null">閉じる</button></div></div></div></div><div v-if="selected" class="modal-backdrop show"></div>
</template>
