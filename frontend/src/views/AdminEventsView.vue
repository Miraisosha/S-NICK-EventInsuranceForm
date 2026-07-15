<script setup>
import { reactive, ref } from 'vue'
import { adminApi, apiMessage } from '../api'

const adminKey = ref('')
const loading = ref(false)
const saving = ref(false)
const events = ref([])
const errorMessage = ref('')
const successMessage = ref('')
const errors = reactive({})
const form = reactive({ event_name: '', event_date: '', location: '' })

function clearMessages() {
  errorMessage.value = ''
  successMessage.value = ''
  Object.keys(errors).forEach((key) => delete errors[key])
}

async function loadEvents() {
  clearMessages()
  if (!adminKey.value) {
    errorMessage.value = '管理キーを入力してください。'
    return
  }
  loading.value = true
  try {
    const { data } = await adminApi.listEvents(adminKey.value)
    events.value = data.events || []
  } catch (error) {
    errorMessage.value = apiMessage(error, 'イベント一覧を取得できませんでした。')
  } finally {
    loading.value = false
  }
}

async function createEvent() {
  clearMessages()
  if (!adminKey.value) {
    errorMessage.value = '管理キーを入力してください。'
    return
  }
  saving.value = true
  try {
    await adminApi.createEvent(adminKey.value, form)
    form.event_name = ''
    form.event_date = ''
    form.location = ''
    successMessage.value = 'イベントを登録しました。'
    const { data } = await adminApi.listEvents(adminKey.value)
    events.value = data.events || []
  } catch (error) {
    Object.assign(errors, error?.response?.data?.errors || {})
    errorMessage.value = apiMessage(error, 'イベントを登録できませんでした。')
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <div class="card content-card admin-export-card">
    <div class="card-body">
      <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-4">
        <div>
          <p class="brand-kicker mb-2">ADMINISTRATION</p>
          <h2 class="h3 fw-bold mb-1">イベントマスター</h2>
          <p class="text-secondary mb-0">入力フォームに表示するイベントを登録します。</p>
        </div>
        <RouterLink class="btn btn-outline-secondary" to="/admin/export">CSV出力へ</RouterLink>
      </div>

      <div v-if="errorMessage" class="alert alert-danger fw-bold" role="alert">{{ errorMessage }}</div>
      <div v-if="successMessage" class="alert alert-success fw-bold" role="status">{{ successMessage }}</div>

      <div class="admin-key-panel mb-4">
        <label for="admin_key" class="form-label">管理キー</label>
        <div class="input-group">
          <input id="admin_key" v-model="adminKey" class="form-control" type="password" autocomplete="current-password" required>
          <button class="btn btn-outline-primary" type="button" :disabled="loading" @click="loadEvents">{{ loading ? '読込中…' : '一覧を表示' }}</button>
        </div>
      </div>

      <form class="border rounded-3 p-3 p-md-4 mb-5" @submit.prevent="createEvent">
        <h3 class="h5 fw-bold mb-3">新しいイベントを登録</h3>
        <div class="row g-3">
          <div class="col-12">
            <label for="event_name" class="form-label">イベント名 <span class="required-badge">必須</span></label>
            <input id="event_name" v-model.trim="form.event_name" class="form-control" :class="{ 'is-invalid': errors.event_name }" maxlength="150" required>
            <div v-if="errors.event_name" class="invalid-feedback">{{ errors.event_name }}</div>
          </div>
          <div class="col-md-5">
            <label for="event_date" class="form-label">開催日 <span class="required-badge">必須</span></label>
            <input id="event_date" v-model="form.event_date" class="form-control" :class="{ 'is-invalid': errors.event_date }" type="date" required>
            <div v-if="errors.event_date" class="invalid-feedback">{{ errors.event_date }}</div>
          </div>
          <div class="col-md-7">
            <label for="location" class="form-label">場所 <span class="required-badge">必須</span></label>
            <input id="location" v-model.trim="form.location" class="form-control" :class="{ 'is-invalid': errors.location }" maxlength="255" required>
            <div v-if="errors.location" class="invalid-feedback">{{ errors.location }}</div>
          </div>
        </div>
        <div class="d-grid d-md-flex justify-content-md-end mt-4">
          <button class="btn btn-primary" type="submit" :disabled="saving">{{ saving ? '登録中…' : 'イベントを登録' }}</button>
        </div>
      </form>

      <h3 class="h5 fw-bold mb-3">登録済みイベント</h3>
      <div v-if="events.length" class="table-responsive">
        <table class="table table-striped align-middle mb-0">
          <thead><tr><th>イベント名</th><th>開催日</th><th>場所</th></tr></thead>
          <tbody>
            <tr v-for="event in events" :key="event.id">
              <td>{{ event.event_name }}</td>
              <td class="text-nowrap">{{ event.event_date }}</td>
              <td>{{ event.location }}</td>
            </tr>
          </tbody>
        </table>
      </div>
      <p v-else class="text-secondary mb-0">管理キーを入力して「一覧を表示」を押してください。</p>
    </div>
  </div>
</template>
