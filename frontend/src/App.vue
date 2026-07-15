<script setup>
import { computed } from 'vue'
import { useRoute } from 'vue-router'

const route = useRoute()
const currentStep = computed(() => Number(route.meta.step || 0))
const steps = ['入力', '確認', '完了']
</script>

<template>
  <div class="app-shell">
    <header class="site-header">
      <div class="container header-inner">
        <div class="header-brand">
          <img class="snick-logo" src="/brand/snick-logo.png" alt="S-NICK">
          <p class="brand-kicker mb-1">EVENT INSURANCE</p>
          <h1 class="header-title mb-0">イベント保険 加入情報登録</h1>
        </div>
        <img
          class="header-character"
          src="/brand/snick-character.png"
          alt="テニスラケットを持ったS-NICKのキャラクター"
        >
      </div>
    </header>

    <main class="container py-4 py-md-5">
      <nav v-if="currentStep" class="stepper mx-auto mb-4 mb-md-5" aria-label="登録の進行状況">
        <ol class="list-unstyled d-flex mb-0">
          <li
            v-for="(label, index) in steps"
            :key="label"
            class="stepper-item flex-fill text-center"
            :class="{ active: currentStep === index + 1, done: currentStep > index + 1 }"
            :aria-current="currentStep === index + 1 ? 'step' : undefined"
          >
            <span class="step-number">{{ index + 1 }}</span>
            <span class="step-label">{{ label }}</span>
          </li>
        </ol>
      </nav>
      <RouterView />
    </main>

    <footer class="container pb-4 text-center small text-secondary">
      通信環境の安全な場所からご入力ください。
    </footer>
  </div>
</template>
