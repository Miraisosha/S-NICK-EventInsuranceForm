<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { apiMessage, invitationApi } from '../api'
import { registrationStore as store } from '../registrationStore'

const route = useRoute()
const router = useRouter()
const submitting = ref(false)
const errorMessage = ref('')
const address = computed(() => `〒${store.form.postal_code}\n${store.form.prefecture}${store.form.city}${store.form.street_address}${store.form.building ? ` ${store.form.building}` : ''}`)
const selectedEvent = computed(() => store.events.find((event) => Number(event.id) === Number(store.form.event_id)))
const eventSummary = computed(() => selectedEvent.value
  ? `${selectedEvent.value.event_name}\n開催日：${selectedEvent.value.event_date}\n場所：${selectedEvent.value.location}`
  : '')

const rows = computed(() => [
  ['参加イベント', eventSummary.value],
  ['氏名', store.form.full_name],
  ['氏名（フリガナ）', store.form.full_name_kana],
  ['生年月日', store.form.birth_date],
  ['電話番号', store.form.phone],
  ['メールアドレス', store.form.email],
  ['住所', address.value],
])

async function submitRegistration() {
  submitting.value = true
  errorMessage.value = ''
  try {
    await invitationApi.submit(store.token, store.form)
    store.completed = true
    await router.replace({ name: 'complete', params: { token: store.token } })
  } catch (error) {
    if (error?.response?.status === 422) {
      store.validated = false
      await router.replace({ name: 'registration', params: { token: store.token } })
      return
    }
    errorMessage.value = apiMessage(error, '登録できませんでした。時間をおいて再度お試しください。')
  } finally {
    submitting.value = false
  }
}

onMounted(() => {
  if (!store.validated || store.token !== String(route.params.token || '')) {
    router.replace({ name: 'registration', params: { token: route.params.token } })
  }
})
</script>

<template>
  <div class="card content-card">
    <div class="card-body">
      <h2 class="h3 fw-bold mb-2">入力内容の確認</h2>
      <p class="text-secondary mb-4">内容をご確認のうえ、「登録する」を押してください。</p>

      <div v-if="errorMessage" class="alert alert-danger border-2 fw-bold" role="alert">{{ errorMessage }}</div>

      <dl class="summary-list border rounded-3 overflow-hidden mb-4">
        <div v-for="([label, value], index) in rows" :key="label" class="row g-0 p-3" :class="{ 'bg-light': index % 2 === 0 }">
          <dt class="col-md-4 mb-1 mb-md-0">{{ label }}</dt>
          <dd class="col-md-8 mb-0">{{ value }}</dd>
        </div>
      </dl>

      <div class="alert alert-info mb-4">
        「個人情報の取扱いについて」に同意済みです（規約版 {{ store.policyVersion }}）。
      </div>

      <div class="row g-3 justify-content-center">
        <div class="col-md-4 order-2 order-md-1 d-grid">
          <button class="btn btn-outline-secondary" type="button" :disabled="submitting" @click="router.push({ name: 'registration', params: { token: store.token } })">入力画面に戻る</button>
        </div>
        <div class="col-md-5 order-1 order-md-2 d-grid">
          <button class="btn btn-primary" type="button" :disabled="submitting" @click="submitRegistration">
            <span v-if="submitting" class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>
            登録する
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
