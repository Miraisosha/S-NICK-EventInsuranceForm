(() => {
  const satisfaction = ['とても満足', '満足', '普通', 'やや不満', '不満']
  const form = document.querySelector('#survey-form')
  const eventSelect = document.querySelector('#event_id')
  const message = document.querySelector('#form-message')
  const missionCards = [...document.querySelectorAll('[data-mission]')]
  const missionTitles = [
    'イベントを振り返ろう',
    'イベントをレビューしよう',
    '次回への気持ちを教えてね',
    '次のイベントをもっと楽しくしよう',
  ]
  const missionProgress = document.querySelector('.mission-progress')
  const missionProgressLabel = document.querySelector('#mission-progress-label')
  const missionProgressTitle = document.querySelector('#mission-progress-title')
  const missionProgressTrack = document.querySelector('.mission-progress-track')
  const missionProgressBar = document.querySelector('#mission-progress-bar')
  const backButton = document.querySelector('#back-button')
  const nextButton = document.querySelector('#next-button')
  const submitButton = document.querySelector('#submit-button')
  const bonusMission = document.querySelector('#bonus-mission')
  let currentMission = 0
  const escapeHtml = (value) => String(value ?? '').replace(/[&<>"']/g, (character) => ({
    '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;',
  })[character])

  function renderChoices(container, options) {
    const name = container.dataset.name
    container.innerHTML = options.map((option, index) => `
      <label class="choice">
        <input type="radio" name="${name}" value="${option}" ${index === 0 ? 'required' : ''}>
        <span>${option}</span>
      </label>
    `).join('')
  }

  document.querySelectorAll('[data-satisfaction]').forEach((element) => renderChoices(element, satisfaction))
  document.querySelectorAll('[data-options]').forEach((element) => {
    renderChoices(element, element.dataset.options.split('|'))
  })

  function showMission(index, scroll = true) {
    currentMission = Math.max(0, Math.min(index, missionCards.length - 1))
    missionCards.forEach((card, cardIndex) => {
      card.hidden = cardIndex !== currentMission
    })

    const missionNumber = currentMission + 1
    missionProgressLabel.textContent = `MISSION ${missionNumber} / ${missionCards.length}`
    missionProgressTitle.textContent = missionTitles[currentMission]
    missionProgressTrack.setAttribute('aria-valuenow', String(missionNumber))
    missionProgressBar.style.width = `${(missionNumber / missionCards.length) * 100}%`
    backButton.hidden = currentMission === 0
    nextButton.hidden = currentMission === missionCards.length - 1
    submitButton.hidden = currentMission !== missionCards.length - 1
    showMessage('')

    if (scroll) {
      missionProgress.scrollIntoView({ behavior: 'smooth', block: 'start' })
    }
  }

  function findInvalidControl(card) {
    const requiredControls = [...card.querySelectorAll('[required]')]
    return requiredControls.find((control) => {
      if (control.type === 'radio') {
        return !form.elements[control.name]?.value
      }
      return !control.checkValidity()
    })
  }

  function showValidationError(missionIndex, invalidControl) {
    showMission(missionIndex, false)
    const errorTarget = invalidControl.closest('.choice-grid, .field, .question')
    errorTarget?.classList.add('has-error')
    invalidControl.setAttribute('aria-invalid', 'true')
    showMessage(`MISSION ${missionIndex + 1}に、まだ回答していない必須項目があります。あと少しでクリア！`)
    errorTarget?.scrollIntoView({ behavior: 'smooth', block: 'center' })
    invalidControl.focus({ preventScroll: true })
  }

  function validateMission(missionIndex) {
    const invalidControl = findInvalidControl(missionCards[missionIndex])
    if (!invalidControl) return true
    showValidationError(missionIndex, invalidControl)
    return false
  }

  function updateBonusMission() {
    const attendance = form.elements.attendance_days?.value
    const isFirstDayParticipant = attendance === '1日目のみ' || attendance === '両日参加'
    bonusMission.hidden = !isFirstDayParticipant
    if (!isFirstDayParticipant) {
      bonusMission.querySelector('textarea').value = ''
    }
  }

  async function request(url, options = {}) {
    const response = await fetch(url, {
      credentials: 'same-origin',
      headers: { Accept: 'application/json', ...(options.body ? { 'Content-Type': 'application/json' } : {}) },
      ...options,
    })
    const data = await response.json().catch(() => ({}))
    if (!response.ok) throw new Error(data.message || '通信に失敗しました。時間をおいて再度お試しください。')
    return data
  }

  function showMessage(text) {
    message.textContent = text
    message.classList.toggle('show', Boolean(text))
    if (text) {
      message.focus()
      message.scrollIntoView({ behavior: 'smooth', block: 'center' })
    }
  }

  async function loadEvents() {
    try {
      const { events = [] } = await request('/api/surveys/options')
      if (events.length === 0) {
        eventSelect.innerHTML = '<option value="">選択できるイベントがありません</option>'
        return
      }
      eventSelect.innerHTML = events.map((event) => {
        const details = [event.date, event.location].filter(Boolean).join(' / ')
        return `<option value="${event.id}">${escapeHtml(event.name)}${details ? `（${escapeHtml(details)}）` : ''}</option>`
      }).join('')
    } catch (error) {
      showMessage(error.message)
    }
  }

  form.addEventListener('change', (event) => {
    event.target.removeAttribute('aria-invalid')
    event.target.closest('.has-error')?.classList.remove('has-error')
    if (event.target.name === 'attendance_days') updateBonusMission()
    if (message.classList.contains('show')) showMessage('')
  })

  form.addEventListener('input', (event) => {
    event.target.removeAttribute('aria-invalid')
    event.target.closest('.has-error')?.classList.remove('has-error')
  })

  nextButton.addEventListener('click', () => {
    if (validateMission(currentMission)) showMission(currentMission + 1)
  })

  backButton.addEventListener('click', () => {
    showMission(currentMission - 1)
  })

  form.addEventListener('submit', async (event) => {
    event.preventDefault()
    showMessage('')

    const invalidMission = missionCards.findIndex((card) => findInvalidControl(card))
    if (invalidMission >= 0) {
      showValidationError(invalidMission, findInvalidControl(missionCards[invalidMission]))
      return
    }

    const payload = Object.fromEntries(new FormData(form).entries())
    submitButton.disabled = true
    submitButton.textContent = 'ミッションを送信中…'
    try {
      await request('/api/surveys/responses', {
        method: 'POST',
        body: JSON.stringify(payload),
      })
      document.querySelector('#main').innerHTML = `
        <section class="card complete">
          <div>
            <div class="complete-mark" aria-hidden="true">✓</div>
            <img class="complete-character" src="/brand/snick-character-thank-you.png" alt="S-NICKキャラクター" width="600" height="295">
            <p class="eyebrow" style="color:#1766a8">MISSION COMPLETE!</p>
            <h1>ミッションへのご協力<br>ありがとうございました！</h1>
            <p>いただいた声を、次回のイベントづくりに活かします。</p>
          </div>
        </section>
      `
      window.scrollTo({ top: 0, behavior: 'smooth' })
    } catch (error) {
      showMessage(error.message)
      submitButton.disabled = false
      submitButton.textContent = 'ミッションを完了する'
    }
  })

  showMission(0, false)
  updateBonusMission()
  loadEvents()
})()
