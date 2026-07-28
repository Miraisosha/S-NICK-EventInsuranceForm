<script setup>
import { onMounted, ref } from 'vue'
import QRCode from 'qrcode'

const props = defineProps({ url: { type: String, required: true } })
const emit = defineEmits(['close'])
const qrDataUrl = ref('')
const copied = ref(false)

onMounted(async () => {
  qrDataUrl.value = await QRCode.toDataURL(props.url, { width: 320, margin: 2 })
})

async function copyUrl() {
  await navigator.clipboard.writeText(props.url)
  copied.value = true
}
</script>

<template>
  <div class="modal d-block" tabindex="-1" role="dialog" aria-modal="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <h2 class="modal-title h5">登録URLのQRコード</h2>
          <button class="btn-close" type="button" aria-label="閉じる" @click="emit('close')"></button>
        </div>
        <div class="modal-body text-center">
          <img v-if="qrDataUrl" :src="qrDataUrl" class="img-fluid" alt="登録URLのQRコード">
          <p class="small text-break border rounded p-2 mt-3 mb-2">{{ url }}</p>
          <p class="small text-danger mb-0">このURLは画面を閉じると再表示できません。必要な場合は再発行してください。</p>
        </div>
        <div class="modal-footer">
          <button class="btn btn-outline-secondary" type="button" @click="emit('close')">閉じる</button>
          <button class="btn btn-primary" type="button" @click="copyUrl">{{ copied ? 'コピーしました' : 'URLをコピー' }}</button>
        </div>
      </div>
    </div>
  </div>
  <div class="modal-backdrop show"></div>
</template>
