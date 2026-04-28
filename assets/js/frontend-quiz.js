/* EFU SJT Assessment — Frontend Quiz (Vanilla JS, no jQuery) */
(function () {
  'use strict';

  const cfg        = window.efuSJT || {};
  const assessment = cfg.assessment || {};
  const pillars    = assessment.pillars || [];
  const REST_URL   = cfg.restUrl || '';
  const REST_NONCE = cfg.nonce  || '';
  const LOGO_URL   = cfg.logoUrl || '';

  // Total steps: 0=Welcome, 1=Info, 2..N+1=Pillars, N+2=Review
  const TOTAL_STEPS = 2 + pillars.length; // 0-indexed last step = TOTAL_STEPS

  let currentStep = 0;
  let responses   = {};
  let userData    = {};

  const root     = document.getElementById('efu-sjt-root');
  const app      = document.getElementById('efu-sjt-app');
  const progressBar = document.getElementById('efu-sjt-progress-bar');
  const dotsWrap    = document.getElementById('efu-sjt-step-dots');
  const DRAFT_URL   = cfg.draftUrl || '';
  const DRAFT_KEY   = 'efu_sjt_draft';

  if (!root || !app) return;

  // ── Draft: localStorage + server sync ────────────────
  let _saveTimer = null;

  function draftGet() {
    try { return JSON.parse(localStorage.getItem(DRAFT_KEY)) || null; }
    catch(e) { return null; }
  }

  function draftToken() {
    const d = draftGet();
    if (d && d.token) return d.token;
    const buf = new Uint8Array(16);
    crypto.getRandomValues(buf);
    return Array.from(buf, b => b.toString(16).padStart(2, '0')).join('');
  }

  function draftSave() {
    if (currentStep < 1) return;
    const draft = {
      token:     draftToken(),
      step:      currentStep,
      userData:  { ...userData },
      responses: { ...responses },
      savedAt:   new Date().toISOString(),
    };
    try { localStorage.setItem(DRAFT_KEY, JSON.stringify(draft)); } catch(e) {}
    clearTimeout(_saveTimer);
    _saveTimer = setTimeout(() => _draftPush(draft), 2000);
  }

  function draftClear() {
    const d = draftGet();
    localStorage.removeItem(DRAFT_KEY);
    if (d && d.token && DRAFT_URL) {
      fetch(DRAFT_URL + '/' + encodeURIComponent(d.token), {
        method: 'DELETE',
        headers: { 'X-WP-Nonce': REST_NONCE },
      }).catch(() => {});
    }
  }

  function _draftPush(draft) {
    if (!DRAFT_URL) return;
    fetch(DRAFT_URL, {
      method:  'POST',
      headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': REST_NONCE },
      body:    JSON.stringify(draft),
    }).then(r => r.ok && _showSaved()).catch(() => {});
  }

  function _showSaved() {
    const el = document.getElementById('efu-autosave-msg');
    if (!el) return;
    el.textContent = '✓ Progress saved';
    el.classList.add('visible');
    clearTimeout(el._t);
    el._t = setTimeout(() => el.classList.remove('visible'), 2500);
  }

  // Inject autosave indicator
  const _autoEl = document.createElement('div');
  _autoEl.id = 'efu-autosave-msg';
  _autoEl.className = 'efu-autosave-msg';
  root.appendChild(_autoEl);

  // ── Scoring (mirrors class-scorer.php) ──────────────
  function scoreCompetency(comp) {
    let weighted = 0;
    let maxPts   = 0;
    comp.items.forEach(item => {
      maxPts += 10;
      item.options.forEach(opt => {
        const key = item.id + '_' + opt.letter;
        const pts = parseInt(responses[key] || 0, 10);
        weighted += pts * opt.level;
      });
    });
    return maxPts > 0 ? weighted / maxPts : 0;
  }

  function levelLabel(score) {
    if (score >= 3.4) return 'Role Model';
    if (score >= 2.7) return 'Advanced';
    if (score >= 2.0) return 'Proficient';
    return 'Developing';
  }

  // ── Dot navigation ───────────────────────────────────
  function buildDots() {
    dotsWrap.innerHTML = '';
    const count = TOTAL_STEPS + 1;
    for (let i = 0; i <= TOTAL_STEPS; i++) {
      const d = document.createElement('div');
      d.className = 'efu-dot';
      if (i < currentStep)  d.classList.add('done');
      if (i === currentStep) d.classList.add('active');
      dotsWrap.appendChild(d);
    }
  }

  function updateProgress() {
    const pct = TOTAL_STEPS > 0 ? (currentStep / TOTAL_STEPS) * 100 : 0;
    progressBar.style.width = pct + '%';
    buildDots();
  }

  // ── Navigation ───────────────────────────────────────
  function goTo(step) {
    currentStep = step;
    updateProgress();
    renderStep();
    draftSave();
  }

  function next() { if (currentStep < TOTAL_STEPS) goTo(currentStep + 1); }
  function prev() { if (currentStep > 0) goTo(currentStep - 1); }

  // ── Render dispatcher ────────────────────────────────
  function renderStep() {
    app.innerHTML = '';
    if (currentStep === 0) return renderWelcome();
    if (currentStep === 1) return renderInfo();
    const pillarIndex = currentStep - 2;
    if (pillarIndex >= 0 && pillarIndex < pillars.length) return renderPillar(pillarIndex);
    if (currentStep === TOTAL_STEPS) return renderReview();
  }

  // ── Step 0: Welcome ──────────────────────────────────
  function renderWelcome() {
    const el = document.createElement('div');
    el.className = 'efu-step-welcome';

    const logoHtml = LOGO_URL
      ? `<div class="efu-welcome-logo"><img src="${escHtml(LOGO_URL)}" alt="EFU Life" onerror="this.parentNode.innerHTML='<div class=\\'efu-logo-fallback\\'>EFU</div>'"></div>`
      : `<div class="efu-welcome-logo"><div class="efu-logo-fallback">EFU</div></div>`;

    el.innerHTML = `
      ${logoHtml}
      <h1>HOD Leadership Assessment</h1>
      <p>This Situational Judgment Assessment evaluates your leadership effectiveness across five key pillars. Each scenario presents a real-world situation — allocate 10 points across four response options to indicate how likely you are to take each action.</p>
      <ul class="efu-instruction-list">
        <li><span class="efu-instr-icon">1</span>Complete all five sections — one pillar at a time.</li>
        <li><span class="efu-instr-icon">2</span>For each scenario, distribute exactly 10 points across the four options.</li>
        <li><span class="efu-instr-icon">3</span>There are no right or wrong answers — reflect your genuine leadership approach and priorities.</li>
        <li><span class="efu-instr-icon">4</span>The assessment takes approximately 25–35 minutes to complete.</li>
      </ul>
      <button class="efu-btn-primary efu-begin-btn">Begin Assessment &rarr;</button>
    `;

    el.querySelector('.efu-begin-btn').addEventListener('click', next);
    app.appendChild(el);
  }

  // ── Step 1: Basic info ───────────────────────────────
  function renderInfo() {
    const el = document.createElement('div');
    el.className = 'efu-step-info';
    el.innerHTML = `
      <h2>Your Details</h2>
      <div class="efu-form-grid">
        <div class="efu-form-field">
          <label for="efu-name">Full Name <span style="color:var(--efu-accent)">*</span></label>
          <input type="text" id="efu-name" name="name" autocomplete="name" value="${escHtml(userData.name || '')}" placeholder="Enter your full name">
          <div class="efu-field-error" id="err-name"></div>
        </div>
        <div class="efu-form-field">
          <label for="efu-email">Email Address <span style="color:var(--efu-accent)">*</span></label>
          <input type="email" id="efu-email" name="email" autocomplete="email" value="${escHtml(userData.email || '')}" placeholder="you@efulife.com">
          <div class="efu-field-error" id="err-email"></div>
        </div>
        <div class="efu-form-field">
          <label for="efu-age">Age <span style="color:var(--efu-accent)">*</span></label>
          <input type="number" id="efu-age" name="age" min="18" max="70" value="${escHtml(userData.age || '')}" placeholder="e.g. 38">
          <div class="efu-field-error" id="err-age"></div>
        </div>
        <div class="efu-form-field">
          <label for="efu-gender">Gender <span style="color:var(--efu-accent)">*</span></label>
          <select id="efu-gender" name="gender">
            <option value="">— Select —</option>
            <option value="Male" ${userData.gender === 'Male' ? 'selected' : ''}>Male</option>
            <option value="Female" ${userData.gender === 'Female' ? 'selected' : ''}>Female</option>
            <option value="Prefer not to say" ${userData.gender === 'Prefer not to say' ? 'selected' : ''}>Prefer not to say</option>
          </select>
          <div class="efu-field-error" id="err-gender"></div>
        </div>
        <div class="efu-form-field full-width">
          <label for="efu-dept">Department <span style="color:var(--efu-accent)">*</span></label>
          <input type="text" id="efu-dept" name="department" value="${escHtml(userData.department || '')}" placeholder="e.g. Group Life & Health">
          <div class="efu-field-error" id="err-dept"></div>
        </div>
      </div>
      <div class="efu-step-nav">
        <button class="efu-btn-secondary efu-back-btn">&larr; Back</button>
        <button class="efu-btn-primary efu-next-btn efu-step-nav-right">Continue &rarr;</button>
      </div>
    `;

    el.querySelector('.efu-back-btn').addEventListener('click', prev);
    el.querySelector('.efu-next-btn').addEventListener('click', () => {
      if (validateInfo(el)) next();
    });

    app.appendChild(el);
  }

  function validateInfo(el) {
    let valid = true;
    const setErr = (id, msg) => {
      const errEl = el.querySelector('#err-' + id);
      const input = el.querySelector('#efu-' + id);
      if (errEl) errEl.textContent = msg;
      if (input) input.classList.toggle('invalid', !!msg);
      if (msg) valid = false;
    };

    const name   = el.querySelector('#efu-name').value.trim();
    const email  = el.querySelector('#efu-email').value.trim();
    const age    = parseInt(el.querySelector('#efu-age').value, 10);
    const gender = el.querySelector('#efu-gender').value;
    const dept   = el.querySelector('#efu-dept').value.trim();

    setErr('name',   !name ? 'Full name is required.' : name.length > 150 ? 'Name too long.' : '');
    setErr('email',  !email ? 'Email is required.' : !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email) ? 'Enter a valid email address.' : '');
    setErr('age',    isNaN(age) ? 'Age is required.' : age < 18 || age > 70 ? 'Age must be between 18 and 70.' : '');
    setErr('gender', !gender ? 'Please select a gender.' : '');
    setErr('dept',   !dept ? 'Department is required.' : '');

    if (valid) {
      userData = { name, email, age, gender, department: dept };
    }
    return valid;
  }

  // ── Steps 2–N+1: Pillar ──────────────────────────────
  function renderPillar(pillarIndex) {
    const pillar = pillars[pillarIndex];
    const el     = document.createElement('div');
    el.className = 'efu-step-pillar';

    const pillarNum   = pillarIndex + 1;
    const totalPillar = pillars.length;

    let html = `
      <div class="efu-pillar-step-header">
        <h2>${escHtml(pillar.label)}</h2>
        <p>Pillar ${pillarNum} of ${totalPillar} &bull; Distribute exactly 10 points per scenario &bull; Minimum 1 point per option</p>
      </div>
    `;

    pillar.competencies.forEach(comp => {
      html += `<div class="efu-competency-header"><h3>${escHtml(comp.label)}</h3></div>`;

      comp.items.forEach((item, itemIdx) => {
        const scenarioNum = itemIdx + 1;
        const totalPts    = getScenarioTotal(item.id);
        const isValid     = isScenarioComplete(item);

        html += `
          <div class="efu-scenario-card" data-scenario="${escHtml(item.id)}">
            <div class="efu-scenario-header">
              <span class="efu-scenario-label">Scenario ${scenarioNum}</span>
              <span class="efu-point-counter ${isValid ? 'valid' : totalPts > 0 ? 'invalid' : ''}" id="counter-${escHtml(item.id)}">
                <span class="efu-counter-icon">${isValid ? '✓' : '⊘'}</span>
                <span id="counter-val-${escHtml(item.id)}">${totalPts}</span>/10 pts
              </span>
            </div>
            <div class="efu-scenario-text">${escHtml(item.scenario)}</div>
            <div class="efu-options-list">
        `;

        item.options.forEach(opt => {
          const key    = item.id + '_' + opt.letter;
          const curVal = responses[key] !== undefined ? responses[key] : '';
          const atMin  = curVal !== '' && parseInt(curVal, 10) <= 1;
          const atMax  = curVal !== '' && parseInt(curVal, 10) >= 10;
          html += `
            <div class="efu-option-row" data-scenario="${escHtml(item.id)}">
              <div class="efu-option-badge">${escHtml(opt.letter)}</div>
              <div class="efu-option-text">${escHtml(opt.text)}</div>
              <div class="efu-option-input-wrap">
                <label>Points</label>
                <div class="efu-pts-stepper">
                  <button class="efu-pts-btn efu-pts-minus" type="button"
                    aria-label="Decrease points" ${atMin ? 'disabled' : ''}>&#8722;</button>
                  <input type="number"
                    class="efu-option-pts"
                    min="1" max="10"
                    data-key="${escHtml(key)}"
                    data-scenario="${escHtml(item.id)}"
                    value="${curVal}"
                    placeholder="0">
                  <button class="efu-pts-btn efu-pts-plus" type="button"
                    aria-label="Increase points" ${atMax ? 'disabled' : ''}>&#43;</button>
                </div>
              </div>
            </div>
          `;
        });

        html += `</div></div>`;
      });
    });

    html += `
      <div class="efu-validation-summary" id="efu-pillar-err" style="display:none;">
        Please ensure every scenario totals exactly 10 points and each option has at least 1 point before continuing.
      </div>
      <div class="efu-step-nav">
        <button class="efu-btn-secondary efu-back-btn">&larr; Back</button>
        <button class="efu-btn-primary efu-next-btn efu-step-nav-right">
          ${pillarIndex < pillars.length - 1 ? 'Next Pillar &rarr;' : 'Review &amp; Submit &rarr;'}
        </button>
      </div>
    `;

    el.innerHTML = html;

    // ── Stepper +/− delegated click ─────────────────
    el.addEventListener('click', e => {
      const btn = e.target.closest('.efu-pts-btn');
      if (!btn || btn.disabled) return;
      const stepper  = btn.closest('.efu-pts-stepper');
      const input    = stepper.querySelector('.efu-option-pts');
      const key      = input.dataset.key;
      const scenario = input.dataset.scenario;
      let val = parseInt(input.value, 10) || 0;
      if (btn.classList.contains('efu-pts-minus')) val = Math.max(1, val - 1);
      if (btn.classList.contains('efu-pts-plus'))  val = Math.min(10, val + 1);
      input.value    = val;
      responses[key] = val;
      updateCounter(el, scenario);
      refreshStepperButtons(input);
      draftSave();
    });

    // ── Direct typing in the input ───────────────────
    el.querySelectorAll('.efu-option-pts').forEach(input => {
      input.addEventListener('input', () => {
        const key      = input.dataset.key;
        const scenario = input.dataset.scenario;
        const raw      = parseInt(input.value, 10);
        const val      = isNaN(raw) ? 0 : Math.max(0, Math.min(10, raw));
        input.value    = input.value === '' ? '' : val;
        responses[key] = val;
        updateCounter(el, scenario);
        refreshStepperButtons(input);
        draftSave();
      });

      input.addEventListener('blur', () => {
        const key      = input.dataset.key;
        const scenario = input.dataset.scenario;
        if (input.value === '' || parseInt(input.value, 10) < 1) {
          input.value    = 1;
          responses[key] = 1;
          updateCounter(el, scenario);
          refreshStepperButtons(input);
        }
      });

      // Initialise button states for pre-filled values (back navigation)
      refreshStepperButtons(input);
    });

    el.querySelector('.efu-back-btn').addEventListener('click', prev);
    el.querySelector('.efu-next-btn').addEventListener('click', () => {
      if (validatePillar(pillar, el)) next();
      window.scrollTo({ top: 0, behavior: 'smooth' });

    });

    app.appendChild(el);
  }

  function getScenarioTotal(scenarioId) {
    const item = findItem(scenarioId);
    if (!item) return 0;
    return item.options.reduce((sum, opt) => sum + (parseInt(responses[scenarioId + '_' + opt.letter], 10) || 0), 0);
  }

  // Single source of truth for "is this scenario fully and correctly filled?"
  function isScenarioComplete(item) {
    const total   = item.options.reduce((s, o) => s + (parseInt(responses[item.id + '_' + o.letter], 10) || 0), 0);
    const allMin1 = item.options.every(o => (parseInt(responses[item.id + '_' + o.letter], 10) || 0) >= 1);
    return total === 10 && allMin1;
  }

  function updateCounter(el, scenarioId) {
    const item = findItem(scenarioId);
    if (!item) return;
    const total    = item.options.reduce((s, o) => s + (parseInt(responses[scenarioId + '_' + o.letter], 10) || 0), 0);
    const complete = isScenarioComplete(item);
    const counter  = el.querySelector('#counter-' + scenarioId);
    const valEl    = el.querySelector('#counter-val-' + scenarioId);
    const icon     = counter ? counter.querySelector('.efu-counter-icon') : null;
    if (counter) {
      counter.className = 'efu-point-counter ' + (complete ? 'valid' : total > 0 ? 'invalid' : '');
    }
    if (valEl) valEl.textContent = total;
    if (icon)  icon.textContent  = complete ? '✓' : '⊘';
  }

  function refreshStepperButtons(input) {
    const stepper = input.closest('.efu-pts-stepper');
    if (!stepper) return;
    const val   = parseInt(input.value, 10) || 0;
    const minus = stepper.querySelector('.efu-pts-minus');
    const plus  = stepper.querySelector('.efu-pts-plus');
    if (minus) minus.disabled = val <= 1;
    if (plus)  plus.disabled  = val >= 10;
  }

  function findItem(scenarioId) {
    for (const p of pillars) {
      for (const c of p.competencies) {
        for (const i of c.items) {
          if (i.id === scenarioId) return i;
        }
      }
    }
    return null;
  }

  function validatePillar(pillar, el) {
    let allValid = true;
    pillar.competencies.forEach(comp => {
      comp.items.forEach(item => {
        if (!isScenarioComplete(item)) allValid = false;
        updateCounter(el, item.id);
      });
    });
    const errEl = el.querySelector('#efu-pillar-err');
    if (errEl) errEl.style.display = allValid ? 'none' : 'block';
    return allValid;
  }

  // ── Review step ──────────────────────────────────────
  function renderReview() {
    const el = document.createElement('div');
    el.className = 'efu-step-review';

    const pillarChecks = pillars.map(p => {
      const allDone = p.competencies.every(c => c.items.every(i => isScenarioComplete(i)));
      return { label: p.label, done: allDone };
    });

    const allComplete = pillarChecks.every(pc => pc.done);

    const checksHtml = pillarChecks.map(pc => `
      <li class="efu-pillar-check-item">
        <span class="efu-check-icon ${pc.done ? 'ok' : 'err'}">${pc.done ? '✓' : '!'}</span>
        ${escHtml(pc.label)}
      </li>
    `).join('');

    el.innerHTML = `
      <h2>Review &amp; Submit</h2>
      <table class="efu-review-table">
        <tr><td>Full Name</td><td>${escHtml(userData.name || '')}</td></tr>
        <tr><td>Email</td><td>${escHtml(userData.email || '')}</td></tr>
        <tr><td>Age</td><td>${escHtml(String(userData.age || ''))}</td></tr>
        <tr><td>Gender</td><td>${escHtml(userData.gender || '')}</td></tr>
        <tr><td>Department</td><td>${escHtml(userData.department || '')}</td></tr>
      </table>

      <h3 style="color:var(--efu-dark);font-size:1rem;margin:0 0 12px;">Pillar Completion</h3>
      <ul class="efu-pillar-checklist">${checksHtml}</ul>

      ${!allComplete ? `<div class="efu-error-banner">Some scenarios are incomplete. Please go back and ensure every scenario totals exactly 10 points with at least 1 point on each option.</div>` : ''}

      <div id="efu-submit-error" class="efu-error-banner" style="display:none;"></div>

      <div class="efu-step-nav">
        <button class="efu-btn-secondary efu-back-btn">&larr; Back</button>
        <button class="efu-btn-primary efu-submit-btn efu-step-nav-right" ${!allComplete ? 'disabled' : ''}>
          Submit Assessment
        </button>
      </div>
    `;

    el.querySelector('.efu-back-btn').addEventListener('click', prev);

    const submitBtn = el.querySelector('.efu-submit-btn');
    if (submitBtn && allComplete) {
      submitBtn.addEventListener('click', () => handleSubmit(submitBtn, el));
    }

    app.appendChild(el);
  }

  // ── Submit ───────────────────────────────────────────
  async function handleSubmit(btn, el) {
    btn.disabled   = true;
    btn.innerHTML  = '<span class="efu-spinner"></span> Submitting…';

    const payload = {
      name:       userData.name,
      email:      userData.email,
      age:        userData.age,
      gender:     userData.gender,
      department: userData.department,
      responses:  JSON.stringify(responses),
    };

    try {
      const res  = await fetch(REST_URL, {
        method:  'POST',
        headers: {
          'Content-Type':     'application/json',
          'X-WP-Nonce':       REST_NONCE,
        },
        body: JSON.stringify(payload),
      });

      const data = await res.json();

      if (res.ok && data.success) {
        draftClear();
        renderThankYou(data.name || userData.name, data);
      } else {
        const errEl = el.querySelector('#efu-submit-error');
        if (errEl) {
          errEl.textContent = data.message || 'Submission failed. Please try again.';
          errEl.style.display = 'block';
        }
        btn.disabled  = false;
        btn.textContent = 'Submit Assessment';
      }
    } catch (err) {
      const errEl = el.querySelector('#efu-submit-error');
      if (errEl) {
        errEl.textContent = 'A network error occurred. Please check your connection and try again.';
        errEl.style.display = 'block';
      }
      btn.disabled  = false;
      btn.textContent = 'Submit Assessment';
    }
  }

  // ── Thank you screen ─────────────────────────────────
  function renderThankYou(name, data) {
    dotsWrap.innerHTML      = '';
    progressBar.style.width = '100%';
    app.innerHTML           = '';

    const PILLAR_LABELS = Object.fromEntries(pillars.map(p => [p.id, p.label]));
    const LEVEL_COLORS = {
      'Developing': '#d90e78',
      'Proficient': '#144864',
      'Advanced':   '#1dab6e',
      'Role Model': '#e09030',
    };

    const score       = data && data.overall_score != null ? parseFloat(data.overall_score) : null;
    const level       = data && data.overall_level ? data.overall_level : null;
    const pillarData  = data && data.pillar_scores ? data.pillar_scores : null;
    const levelColor  = level ? (LEVEL_COLORS[level] || '#144864') : '#144864';

    const el = document.createElement('div');
    el.className = 'efu-thankyou';

    const logoHtml = LOGO_URL
      ? `<div class="efu-thankyou-logo"><img src="${escHtml(LOGO_URL)}" alt="EFU Life" onerror="this.style.display='none'"></div>`
      : '';

    let scoreHtml = '';
    if (score !== null && level) {
      scoreHtml = `
        <div class="efu-result-card">
          <div class="efu-result-label">Your Overall Level</div>
          <div class="efu-result-level" style="color:${levelColor}">${escHtml(level)}</div>
          <div class="efu-result-score">${score.toFixed(2)} / 4.00</div>
        </div>`;
    }

    let pillarHtml = '';
    if (pillarData && Object.keys(pillarData).length) {
      const rows = Object.entries(pillarData).map(([pid, val]) => {
        const pScore = parseFloat(val);
        const pLabel = PILLAR_LABELS[pid] || pid;
        const pct    = Math.round((pScore / 4) * 100);
        const pLvl   = levelLabel(pScore);
        const pColor = LEVEL_COLORS[pLvl] || '#144864';
        return `<tr>
          <td class="efu-ty-pillar-name">${escHtml(pLabel)}</td>
          <td class="efu-ty-pillar-bar-cell">
            <div class="efu-ty-bar-wrap"><div class="efu-ty-bar" style="width:${pct}%;background:${pColor}"></div></div>
          </td>
          <td class="efu-ty-pillar-score">${pScore.toFixed(2)}</td>
        </tr>`;
      }).join('');
      pillarHtml = `
        <div class="efu-ty-pillars">
          <h3 class="efu-ty-section-title">Pillar Breakdown</h3>
          <table class="efu-ty-pillar-table">${rows}</table>
        </div>`;
    }

    el.innerHTML = `
      ${logoHtml}
      <div class="efu-thankyou-icon">&#10003;</div>
      <h2 class="efu-thankyou-heading">Thank you, ${escHtml(name)}!</h2>
      ${scoreHtml}
      ${pillarHtml}
      <p class="efu-thankyou-msg">
        A results summary has been sent to <strong>${escHtml(userData.email || '')}</strong>.<br>
        Our team will be in touch with your detailed development report.
      </p>
    `;

    app.appendChild(el);
    window.scrollTo({ top: root.getBoundingClientRect().top + window.scrollY - 40, behavior: 'smooth' });
  }

  // ── Resume page ──────────────────────────────────────
  function renderResumePage(draft) {
    dotsWrap.innerHTML      = '';
    progressBar.style.width = '0%';
    app.innerHTML           = '';

    const when = (() => {
      if (!draft.savedAt) return 'previously';
      const diff = Date.now() - new Date(draft.savedAt).getTime();
      const mins = Math.round(diff / 60000);
      if (mins < 2)  return 'just now';
      if (mins < 60) return mins + ' minutes ago';
      const hrs = Math.round(mins / 60);
      if (hrs < 24)  return hrs + ' hour' + (hrs > 1 ? 's' : '') + ' ago';
      return new Date(draft.savedAt).toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' });
    })();

    const stepLabel = (() => {
      const s = draft.step || 0;
      if (s === 1) return 'Your Details';
      if (s >= 2 && s <= pillars.length + 1) return 'Pillar ' + (s - 1) + ' of ' + pillars.length;
      if (s > pillars.length + 1) return 'Review & Submit';
      return 'Step ' + s;
    })();

    const logoHtml = LOGO_URL
      ? `<div class="efu-welcome-logo"><img src="${escHtml(LOGO_URL)}" alt="EFU Life" onerror="this.parentNode.innerHTML='<div class=efu-logo-fallback>EFU</div>'"></div>`
      : '';

    const el = document.createElement('div');
    el.className = 'efu-resume-page';
    el.innerHTML = `
      ${logoHtml}
      <div class="efu-resume-icon">&#8617;</div>
      <h2 class="efu-resume-heading">Continue Your Assessment</h2>
      ${draft.userData && draft.userData.name ? `<p class="efu-resume-name">${escHtml(draft.userData.name)}</p>` : ''}
      <div class="efu-resume-meta">
        <span><strong>Last saved:</strong> ${escHtml(when)}</span>
        <span><strong>Progress:</strong> ${escHtml(stepLabel)}</span>
      </div>
      <div class="efu-resume-actions">
        <button class="efu-btn-primary efu-resume-continue">Continue Assessment &rarr;</button>
        <button class="efu-btn-secondary efu-resume-restart">Start Fresh</button>
      </div>
    `;

    el.querySelector('.efu-resume-continue').addEventListener('click', () => {
      responses   = { ...(draft.responses || {}) };
      userData    = { ...(draft.userData  || {}) };
      currentStep = draft.step || 0;
      app.innerHTML = '';
      updateProgress();
      renderStep();
    });

    el.querySelector('.efu-resume-restart').addEventListener('click', () => {
      if (!confirm('Start fresh? Your saved progress will be deleted.')) return;
      draftClear();
      responses   = {};
      userData    = {};
      currentStep = 0;
      app.innerHTML = '';
      updateProgress();
      renderStep();
    });

    app.appendChild(el);
  }

  // ── Utilities ────────────────────────────────────────
  function escHtml(str) {
    return String(str)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  }

  // ── Boot ─────────────────────────────────────────────
  const _savedDraft = draftGet();
  if (_savedDraft && _savedDraft.step > 0) {
    renderResumePage(_savedDraft);
  } else {
    updateProgress();
    renderStep();
  }

})();
