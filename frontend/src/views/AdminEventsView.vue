<script setup>
import { computed, onMounted, ref } from 'vue'
import { adminApi, apiMessage } from '../api'

const events = ref([])
const loading = ref(true)
const errorMessage = ref('')
const sortKey = ref('event_date')
const sortDirection = ref('desc')

const sortedEvents = computed(() => [...events.value].sort((a, b) => {
  const left = a[sortKey.value] ?? ''
  const right = b[sortKey.value] ?? ''
  const result = typeof left === 'number'
    ? left - right
    : String(left).localeCompare(String(right), 'ja')
  return sortDirection.value === 'asc' ? result : -result
}))

function sortBy(key) {
  if (sortKey.value === key) sortDirection.value = sortDirection.value === 'asc' ? 'desc' : 'asc'
  else {
    sortKey.value = key
    sortDirection.value = 'asc'
  }
}

function marker(key) {
  return sortKey.value === key ? (sortDirection.value === 'asc' ? ' ▲' : ' ▼') : ''
}

onMounted(async () => {
  try {
    const { data } = await adminApi.listEvents()
    events.value = data.events || []
  } catch (error) {
    errorMessage.value = apiMessage(error, 'イベント一覧を取得できませんでした。')
  } finally {
    loading.value = false
  }
})
</script>

<template>
  <div class="card content-card">
    <div class="card-body">
      <div class="admin-page-header d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div><p class="brand-kicker mb-1">ADMINISTRATION</p><h2 class="h3 fw-bold mb-0">イベント一覧</h2></div>
        <div class="admin-page-actions"><RouterLink class="btn btn-primary" :to="{ name: 'admin-event-new' }">新規登録</RouterLink></div>
      </div>
      <div v-if="errorMessage" class="alert alert-danger">{{ errorMessage }}</div>
      <p v-if="loading" class="text-secondary">読み込み中…</p>
      <template v-else-if="events.length">
        <div class="admin-mobile-sort d-md-none mb-3">
          <label for="event_sort" class="form-label">並び順</label>
          <div class="d-flex gap-2"><select id="event_sort" v-model="sortKey" class="form-select"><option value="event_name">イベント名</option><option value="event_date">開催日</option><option value="location">場所</option><option value="pending_count">加入前人数</option><option value="completed_count">加入済み人数</option></select><button class="btn btn-outline-secondary flex-shrink-0" type="button" @click="sortDirection = sortDirection === 'asc' ? 'desc' : 'asc'">{{ sortDirection === 'asc' ? '昇順' : '降順' }}</button></div>
        </div>
        <div class="table-responsive">
        <table class="table table-hover align-middle admin-list-table">
          <thead><tr>
            <th><button class="btn btn-link p-0 fw-bold text-decoration-none" @click="sortBy('event_name')">イベント名{{ marker('event_name') }}</button></th>
            <th><button class="btn btn-link p-0 fw-bold text-decoration-none" @click="sortBy('event_date')">開催日{{ marker('event_date') }}</button></th>
            <th><button class="btn btn-link p-0 fw-bold text-decoration-none" @click="sortBy('location')">場所{{ marker('location') }}</button></th>
            <th><button class="btn btn-link p-0 fw-bold text-decoration-none" @click="sortBy('pending_count')">加入前{{ marker('pending_count') }}</button></th>
            <th><button class="btn btn-link p-0 fw-bold text-decoration-none" @click="sortBy('completed_count')">加入済み{{ marker('completed_count') }}</button></th>
            <th><span class="visually-hidden">操作</span></th>
          </tr></thead>
          <tbody><tr v-for="event in sortedEvents" :key="event.id">
            <td data-label="イベント名"><RouterLink :to="{ name: 'admin-event-detail', params: { eventId: event.id } }">{{ event.event_name }}</RouterLink></td>
            <td data-label="開催日" class="text-nowrap">{{ event.event_date }}</td><td data-label="場所">{{ event.location }}</td>
            <td data-label="加入前">{{ event.pending_count }}名</td><td data-label="加入済み">{{ event.completed_count }}名</td>
            <td class="admin-row-action"><RouterLink class="btn btn-sm btn-outline-primary" :to="{ name: 'admin-event-detail', params: { eventId: event.id } }">詳細を見る</RouterLink></td>
          </tr></tbody>
        </table>
      </div>
      </template>
      <p v-else class="text-secondary mb-0">イベントは登録されていません。</p>
    </div>
  </div>
</template>
