(() => {
  const filter = document.querySelector('#event-filter')
  const responsesElement = document.querySelector('#responses')
  const summary = document.querySelector('#summary')
  const message = document.querySelector('#admin-message')
  const downloadButton = document.querySelector('#download-button')

  const answerLabels = {
    attendance_days: '参加した日程',
    event_enjoyment: 'イベントは楽しかったですか。',
    overall_satisfaction: 'イベント全体の満足度',
    lesson_satisfaction: '練習・チーム戦の内容',
    staff_satisfaction: 'スタッフの対応',
    special_guest_satisfaction: 'スペシャルゲストの満足度',
    difficulty: '練習・ゲームの難易度',
    training_amount: '運動量',
    participation_intent: 'また参加したいと思いますか',
    participation_reason: 'その理由',
    best_training: '特に良かった練習',
    improvements: '改善してほしいこと',
    future_training: '今後やってほしい練習',
    referee_workshop_feedback: '審判勉強会の満足度（1日目参加の方）',
    other_comments: 'その他ご意見・ご感想',
  }

  const escapeHtml = (value) => String(value ?? '').replace(/[&<>"']/g, (character) => ({
    '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;',
  })[character])

  async function request(url, options = {}) {
    const response = await fetch(url, { credentials: 'same-origin', headers: { Accept: 'application/json' }, ...options })
    if (response.status === 401) {
      location.href = `/admin/login?redirect=${encodeURIComponent(location.pathname)}`
      throw new Error('管理者ログインが必要です。')
    }
    const data = await response.json().catch(() => ({}))
    if (!response.ok) throw new Error(data.message || 'データを読み込めませんでした。')
    return data
  }

  function showMessage(text) {
    message.textContent = text
    message.classList.toggle('show', Boolean(text))
  }

  async function checkSession() {
    await request('/api/admin/auth/session')
  }

  async function loadEvents() {
    const { events = [] } = await request('/api/admin/events')
    filter.innerHTML = '<option value="">すべてのイベント</option>' + events.map((event) =>
      `<option value="${event.id}">${escapeHtml(event.event_name)}（${escapeHtml(event.event_date)}）</option>`
    ).join('')
  }

  function renderResponses(responses) {
    summary.textContent = `${responses.length}件の回答`
    if (!responses.length) {
      responsesElement.innerHTML = '<div class="card empty">該当するアンケート回答はありません。</div>'
      return
    }

    responsesElement.innerHTML = responses.map((response) => `
      <details class="card response-card">
        <summary>
          <time>${escapeHtml(response.submitted_at)}</time>
          <strong>${escapeHtml(response.attendee_name) || '（名前未入力）'}</strong>
          <span>${escapeHtml(response.event_name)}（${escapeHtml(response.event_date)}）</span>
          <span class="pill">${escapeHtml(response.overall_satisfaction)}</span>
        </summary>
        <dl class="answer-grid">
          ${Object.entries(answerLabels).map(([key, label]) => `
            <div class="answer">
              <dt>${label}</dt>
              <dd>${escapeHtml(response[key]) || '—'}</dd>
            </div>
          `).join('')}
        </dl>
      </details>
    `).join('')
  }

  async function loadResponses() {
    showMessage('')
    summary.textContent = '回答を読み込んでいます…'
    try {
      const query = filter.value ? `?event_id=${encodeURIComponent(filter.value)}` : ''
      const { responses = [] } = await request(`/api/admin/surveys${query}`)
      renderResponses(responses)
    } catch (error) {
      showMessage(error.message)
      summary.textContent = ''
    }
  }

  filter.addEventListener('change', loadResponses)
  downloadButton.addEventListener('click', () => {
    const query = filter.value ? `?event_id=${encodeURIComponent(filter.value)}` : ''
    location.href = `/api/admin/surveys.csv${query}`
  })

  ;(async () => {
    try {
      await checkSession()
      await loadEvents()
      await loadResponses()
    } catch (error) {
      showMessage(error.message)
    }
  })()
})()
