<script setup>
import { computed, nextTick, onMounted, reactive, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { apiMessage, invitationApi } from '../api'
import { registrationStore as store } from '../registrationStore'

const route = useRoute()
const router = useRouter()
const formElement = ref(null)
const loading = ref(true)
const submitting = ref(false)
const loadError = ref('')
const generalError = ref('')
const errors = reactive({})
const prefectures = ['北海道','青森県','岩手県','宮城県','秋田県','山形県','福島県','茨城県','栃木県','群馬県','埼玉県','千葉県','東京都','神奈川県','新潟県','富山県','石川県','福井県','山梨県','長野県','岐阜県','静岡県','愛知県','三重県','滋賀県','京都府','大阪府','兵庫県','奈良県','和歌山県','鳥取県','島根県','岡山県','広島県','山口県','徳島県','香川県','愛媛県','高知県','福岡県','佐賀県','長崎県','熊本県','大分県','宮崎県','鹿児島県','沖縄県']
const selectedEvent = computed(() => store.events.find((event) => Number(event.id) === Number(store.form.event_id)))

function formatEventDate(date) {
  if (!date) return ''
  return new Intl.DateTimeFormat('ja-JP', { dateStyle: 'long', timeZone: 'Asia/Tokyo' })
    .format(new Date(`${date}T00:00:00+09:00`))
}

function clearErrors() {
  Object.keys(errors).forEach((key) => delete errors[key])
  generalError.value = ''
}

function applyClientErrors() {
  const form = formElement.value
  if (form.checkValidity()) return true
  form.querySelectorAll(':invalid').forEach((field) => {
    errors[field.name] = field.validity.valueMissing ? 'この項目を入力してください。' : '入力形式を確認してください。'
  })
  return false
}

async function focusFirstError() {
  await nextTick()
  document.querySelector('.is-invalid, .has-error input')?.focus()
}

async function confirmRegistration() {
  clearErrors()
  if (!applyClientErrors()) {
    generalError.value = '未入力または入力形式に誤りがある項目があります。'
    await focusFirstError()
    return
  }

  submitting.value = true
  try {
    const { data } = await invitationApi.validate(store.token, store.form)
    store.form = { ...store.form, ...data.data }
    store.validated = true
    await router.push({ name: 'confirmation', params: { token: store.token } })
  } catch (error) {
    Object.assign(errors, error?.response?.data?.errors || {})
    generalError.value = apiMessage(error, '入力内容を確認してください。')
    await focusFirstError()
  } finally {
    submitting.value = false
  }
}

onMounted(async () => {
  const token = String(route.params.token || '')
  if (store.token !== token) store.reset(token)
  try {
    const { data } = await invitationApi.get(token)
    if (data.submitted) {
      store.completed = true
      await router.replace({ name: 'complete', params: { token } })
      return
    }
    store.invitedName = data.invitedName
    store.policyVersion = data.policyVersion
    store.events = data.events || []
    if (!store.form.event_id && data.eventId) store.form.event_id = data.eventId
    if (!store.form.full_name) store.form.full_name = data.invitedName
  } catch (error) {
    loadError.value = apiMessage(error, 'このURLを確認できませんでした。')
  } finally {
    loading.value = false
  }
})
</script>

<template>
  <div v-if="loading" class="text-center py-5" role="status">
    <div class="spinner-border text-primary" aria-hidden="true"></div>
    <p class="mt-3 mb-0">登録ページを確認しています…</p>
  </div>

  <div v-else-if="loadError" class="card content-card">
    <div class="card-body text-center py-5">
      <h2 class="h4 fw-bold">ページを表示できません</h2>
      <p class="alert alert-danger mt-4 mb-0" role="alert">{{ loadError }}</p>
      <p class="text-secondary mt-3 mb-0">配布されたURLが途中で切れていないかご確認ください。</p>
    </div>
  </div>

  <form v-else ref="formElement" class="card content-card" novalidate @submit.prevent="confirmRegistration">
    <div class="card-body">
      <div class="mb-4">
        <p class="text-secondary mb-2">{{ store.invitedName }} 様</p>
        <h2 class="h3 fw-bold mb-2">加入者情報をご入力ください</h2>
        <p class="text-secondary mb-0">入力内容はイベント保険への加入手続きにのみ使用します。</p>
      </div>

      <div v-if="generalError" class="alert alert-danger border-2 fw-bold" role="alert" tabindex="-1">
        {{ generalError }}
      </div>

      <section aria-labelledby="event-heading">
        <h3 id="event-heading" class="h5 section-title mb-4">参加イベント</h3>
        <label for="event_id" class="form-label">イベント名 <span class="required-badge">必須</span></label>
        <select id="event_id" v-model="store.form.event_id" name="event_id" class="form-select" :class="{ 'is-invalid': errors.event_id }" required>
          <option value="">選択してください</option>
          <option v-for="event in store.events" :key="event.id" :value="event.id">
            {{ event.event_name }}（{{ event.event_date }}）
          </option>
        </select>
        <div v-if="errors.event_id" class="invalid-feedback">{{ errors.event_id }}</div>
        <div v-if="store.events.length === 0" class="alert alert-warning mt-3 mb-0" role="status">
          現在選択できるイベントがありません。管理者へお問い合わせください。
        </div>
        <div v-else-if="selectedEvent" class="card bg-light border-0 mt-3" aria-live="polite">
          <div class="card-body">
            <p class="fw-bold mb-2">{{ selectedEvent.event_name }}</p>
            <dl class="row mb-0">
              <dt class="col-sm-3">開催日</dt>
              <dd class="col-sm-9">{{ formatEventDate(selectedEvent.event_date) }}</dd>
              <dt class="col-sm-3">場所</dt>
              <dd class="col-sm-9 mb-0">{{ selectedEvent.location }}</dd>
            </dl>
          </div>
        </div>
      </section>

      <hr class="my-5">

      <section aria-labelledby="basic-heading">
        <h3 id="basic-heading" class="h5 section-title mb-4">基本情報</h3>
        <div class="row g-3 g-md-4">
          <div class="col-12">
            <label for="full_name" class="form-label">氏名 <span class="required-badge">必須</span></label>
            <input id="full_name" v-model.trim="store.form.full_name" name="full_name" class="form-control" :class="{ 'is-invalid': errors.full_name }" autocomplete="name" maxlength="100" required>
            <div v-if="errors.full_name" class="invalid-feedback">{{ errors.full_name }}</div>
            <div class="form-text">お名前に誤りがある場合は修正してください。</div>
          </div>
          <div class="col-12">
            <label for="full_name_kana" class="form-label">氏名（フリガナ） <span class="required-badge">必須</span></label>
            <input id="full_name_kana" v-model.trim="store.form.full_name_kana" name="full_name_kana" class="form-control" :class="{ 'is-invalid': errors.full_name_kana }" placeholder="ヤマダ タロウ" maxlength="100" required>
            <div v-if="errors.full_name_kana" class="invalid-feedback">{{ errors.full_name_kana }}</div>
          </div>
          <div class="col-md-6">
            <label for="birth_date" class="form-label">生年月日 <span class="required-badge">必須</span></label>
            <input id="birth_date" v-model="store.form.birth_date" name="birth_date" class="form-control" :class="{ 'is-invalid': errors.birth_date }" type="date" autocomplete="bday" required>
            <div v-if="errors.birth_date" class="invalid-feedback">{{ errors.birth_date }}</div>
          </div>
          <div class="col-md-6">
            <label for="phone" class="form-label">電話番号 <span class="required-badge">必須</span></label>
            <input id="phone" v-model.trim="store.form.phone" name="phone" class="form-control" :class="{ 'is-invalid': errors.phone }" type="tel" inputmode="tel" autocomplete="tel" placeholder="090-1234-5678" pattern="[0-9+()\- ]{10,20}" required>
            <div v-if="errors.phone" class="invalid-feedback">{{ errors.phone }}</div>
          </div>
          <div class="col-12">
            <label for="email" class="form-label">メールアドレス <span class="required-badge">必須</span></label>
            <input id="email" v-model.trim="store.form.email" name="email" class="form-control" :class="{ 'is-invalid': errors.email }" type="email" inputmode="email" autocomplete="email" required>
            <div v-if="errors.email" class="invalid-feedback">{{ errors.email }}</div>
          </div>
        </div>
      </section>

      <hr class="my-5">

      <section aria-labelledby="address-heading">
        <h3 id="address-heading" class="h5 section-title mb-4">住所</h3>
        <div class="row g-3 g-md-4">
          <div class="col-md-5">
            <label for="postal_code" class="form-label">郵便番号 <span class="required-badge">必須</span></label>
            <input id="postal_code" v-model.trim="store.form.postal_code" name="postal_code" class="form-control" :class="{ 'is-invalid': errors.postal_code }" inputmode="numeric" autocomplete="postal-code" placeholder="123-4567" pattern="\d{3}-?\d{4}" required>
            <div v-if="errors.postal_code" class="invalid-feedback">{{ errors.postal_code }}</div>
          </div>
          <div class="col-md-7">
            <label for="prefecture" class="form-label">都道府県 <span class="required-badge">必須</span></label>
            <select id="prefecture" v-model="store.form.prefecture" name="prefecture" class="form-select" :class="{ 'is-invalid': errors.prefecture }" autocomplete="address-level1" required>
              <option value="">選択してください</option>
              <option v-for="prefecture in prefectures" :key="prefecture" :value="prefecture">{{ prefecture }}</option>
            </select>
            <div v-if="errors.prefecture" class="invalid-feedback">{{ errors.prefecture }}</div>
          </div>
          <div class="col-12">
            <label for="city" class="form-label">市区町村 <span class="required-badge">必須</span></label>
            <input id="city" v-model.trim="store.form.city" name="city" class="form-control" :class="{ 'is-invalid': errors.city }" autocomplete="address-level2" required>
            <div v-if="errors.city" class="invalid-feedback">{{ errors.city }}</div>
          </div>
          <div class="col-12">
            <label for="street_address" class="form-label">町名・番地 <span class="required-badge">必須</span></label>
            <input id="street_address" v-model.trim="store.form.street_address" name="street_address" class="form-control" :class="{ 'is-invalid': errors.street_address }" autocomplete="address-line1" required>
            <div v-if="errors.street_address" class="invalid-feedback">{{ errors.street_address }}</div>
          </div>
          <div class="col-12">
            <label for="building" class="form-label">建物名・部屋番号 <span class="text-secondary small">任意</span></label>
            <input id="building" v-model.trim="store.form.building" name="building" class="form-control" :class="{ 'is-invalid': errors.building }" autocomplete="address-line2">
            <div v-if="errors.building" class="invalid-feedback">{{ errors.building }}</div>
          </div>
        </div>
      </section>

      <hr class="my-5">

      <section aria-labelledby="privacy-heading">
        <h3 id="privacy-heading" class="h5 section-title mb-4">個人情報の取扱いについて</h3>
        <div class="privacy-panel p-3 p-md-4 mb-3">
          <p class="fw-bold">ご入力いただく個人情報は、次の方針で取り扱います。</p>
          <ul class="mb-2">
            <li>イベント保険への加入申込み、本人確認、加入内容の連絡にのみ使用します。</li>
            <li>加入手続きに必要な範囲で、保険会社および保険代理店へ提供する場合があります。</li>
            <li>法令に基づく場合を除き、上記以外の目的で利用または第三者提供しません。</li>
            <li>取得した情報は適切な安全管理措置を講じ、保管期間終了後に適切な方法で削除します。</li>
          </ul>
          <p class="small text-secondary mb-0">お問い合わせは、S-NICKイベント保険担当　三浦彩花（clara@s-nick.com）までメールでご連絡ください。</p>
        </div>
        <div class="consent-box" :class="{ 'has-error': errors.privacy_consent }">
          <div class="form-check">
            <input id="privacy_consent" v-model="store.form.privacy_consent" name="privacy_consent" class="form-check-input" :class="{ 'is-invalid': errors.privacy_consent }" type="checkbox" required>
            <label for="privacy_consent" class="form-check-label fw-bold">上記の個人情報の取扱いに同意します <span class="required-badge">必須</span></label>
            <div v-if="errors.privacy_consent" class="invalid-feedback d-block">{{ errors.privacy_consent }}</div>
          </div>
        </div>
      </section>

      <div class="d-grid col-md-7 mx-auto mt-5">
        <button class="btn btn-primary btn-lg" type="submit" :disabled="submitting">
          <span v-if="submitting" class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>
          入力内容を確認する
        </button>
      </div>
    </div>
  </form>
</template>
