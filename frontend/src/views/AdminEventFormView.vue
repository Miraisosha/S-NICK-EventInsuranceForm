<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { adminApi, apiMessage } from '../api'

const route = useRoute()
const router = useRouter()
const eventId = computed(() => route.params.eventId)
const editing = computed(() => Boolean(eventId.value))
const form = reactive({ event_name: '', event_date: '', location: '' })
const errors = reactive({})
const loading = ref(editing.value)
const saving = ref(false)
const errorMessage = ref('')

onMounted(async () => {
  if (!editing.value) return
  try {
    const { data } = await adminApi.getEvent(eventId.value)
    Object.assign(form, data.event)
  } catch (error) {
    errorMessage.value = apiMessage(error, 'イベントを取得できませんでした。')
  } finally {
    loading.value = false
  }
})

async function save() {
  saving.value = true
  errorMessage.value = ''
  Object.keys(errors).forEach((key) => delete errors[key])
  try {
    const response = editing.value
      ? await adminApi.updateEvent(eventId.value, form)
      : await adminApi.createEvent(form)
    await router.replace({ name: 'admin-event-detail', params: { eventId: response.data.event.id } })
  } catch (error) {
    Object.assign(errors, error?.response?.data?.errors || {})
    errorMessage.value = apiMessage(error, 'イベントを保存できませんでした。')
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <div class="card content-card">
    <div class="card-body">
      <div class="admin-page-header d-flex justify-content-between align-items-center gap-3 mb-4">
        <h2 class="h3 fw-bold mb-0">イベント{{ editing ? '修正' : '新規登録' }}</h2>
        <div class="admin-page-actions"><RouterLink class="btn btn-outline-secondary" :to="editing ? { name: 'admin-event-detail', params: { eventId } } : { name: 'admin-events' }">戻る</RouterLink></div>
      </div>
      <p v-if="loading">読み込み中…</p>
      <form v-else @submit.prevent="save">
        <div v-if="errorMessage" class="alert alert-danger">{{ errorMessage }}</div>
        <div class="mb-3"><label class="form-label" for="event_name">イベント名</label><input id="event_name" v-model.trim="form.event_name" class="form-control" :class="{ 'is-invalid': errors.event_name }" maxlength="150" required><div class="invalid-feedback">{{ errors.event_name }}</div></div>
        <div class="mb-3"><label class="form-label" for="event_date">開催日</label><input id="event_date" v-model="form.event_date" class="form-control" :class="{ 'is-invalid': errors.event_date }" type="date" required><div class="invalid-feedback">{{ errors.event_date }}</div></div>
        <div class="mb-4"><label class="form-label" for="location">場所</label><input id="location" v-model.trim="form.location" class="form-control" :class="{ 'is-invalid': errors.location }" maxlength="255" required><div class="invalid-feedback">{{ errors.location }}</div></div>
        <div class="d-flex justify-content-end"><button class="btn btn-primary mobile-full" type="submit" :disabled="saving">{{ saving ? '保存中…' : '保存' }}</button></div>
      </form>
    </div>
  </div>
</template>
