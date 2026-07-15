<script setup>
import { onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import { invitationApi } from '../api'
import { registrationStore as store } from '../registrationStore'

const route = useRoute()
const verified = ref(store.completed)
const checking = ref(!store.completed)

onMounted(async () => {
  if (!verified.value) {
    try {
      const { data } = await invitationApi.get(String(route.params.token || ''))
      verified.value = Boolean(data.submitted)
    } catch {
      verified.value = false
    } finally {
      checking.value = false
    }
  }
})
</script>

<template>
  <div class="card content-card">
    <div class="card-body text-center py-5">
      <div v-if="checking" class="spinner-border text-primary" role="status"><span class="visually-hidden">確認中</span></div>
      <template v-else-if="verified">
        <div class="success-mark" aria-hidden="true">✓</div>
        <h2 class="h3 fw-bold mt-4">登録が完了しました</h2>
        <p class="text-secondary mt-3 mb-0">イベント保険の加入情報を受け付けました。<br>このページは閉じていただいて構いません。</p>
      </template>
      <template v-else>
        <h2 class="h4 fw-bold">登録完了を確認できませんでした</h2>
        <p class="alert alert-warning mt-4 mb-0">配布されたURLから登録状況をご確認ください。</p>
      </template>
    </div>
  </div>
</template>
