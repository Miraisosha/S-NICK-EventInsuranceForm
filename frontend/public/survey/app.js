(() => {
  const satisfaction = ['とても満足', '満足', '普通', 'やや不満', '不満']
  const form = document.querySelector('#survey-form')
  const eventSelect = document.querySelector('#event_id')
  const message = document.querySelector('#form-message')
  const submitButton = document.querySelector('#submit-button')
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

  form.addEventListener('submit', async (event) => {
    event.preventDefault()
    showMessage('')
    if (!form.reportValidity()) {
      showMessage('必須項目をすべて選択してください。')
      return
    }

    const payload = Object.fromEntries(new FormData(form).entries())
    submitButton.disabled = true
    submitButton.textContent = '送信しています…'
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
            <p class="eyebrow" style="color:#1766a8">THANK YOU</p>
            <h1>アンケートにお答えいただき<br>ありがとうございます。</h1>
            <p>いただいたご意見は、今後のイベント運営に活かしてまいります。</p>
          </div>
        </section>
      `
      window.scrollTo({ top: 0, behavior: 'smooth' })
    } catch (error) {
      showMessage(error.message)
      submitButton.disabled = false
      submitButton.textContent = '回答を送信する'
    }
  })

  loadEvents()
})()
