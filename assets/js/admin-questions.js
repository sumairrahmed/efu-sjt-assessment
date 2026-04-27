/* EFU SJT — Admin Questions Inline Editor */
(function () {
  'use strict';

  const vars    = window.efuAdminVars || {};
  const ajaxUrl = vars.ajaxUrl || '';
  const nonce   = vars.nonce   || '';
  const root    = document.getElementById('efu-questions-root');
  const saveBtn = document.getElementById('efu-save-all');
  const status  = document.getElementById('efu-save-status');

  if (!root || !saveBtn) return;

  // Read the live assessment structure from the rendered DOM
  // and rebuild a clean JSON object to POST.
  function buildAssessmentFromDOM() {
    const assessment = { pillars: [] };

    root.querySelectorAll('.efu-pillar-block').forEach(pillarEl => {
      const pi     = pillarEl.dataset.pillar;
      const label  = pillarEl.querySelector('.efu-pillar-header h2').textContent.trim();
      const id     = pillarEl.querySelector('.efu-pillar-id').textContent.trim();
      const pillar = { id, label, competencies: [] };

      pillarEl.querySelectorAll('.efu-competency-block').forEach(compEl => {
        const compLabel = compEl.querySelector('.efu-comp-label').textContent.trim();
        const compId    = compEl.dataset.compId || generateId(compLabel);
        const comp      = { id: compId, label: compLabel, items: [] };

        compEl.querySelectorAll('.efu-scenario-card').forEach(scenarioEl => {
          const itemId   = scenarioEl.dataset.itemId || scenarioEl.querySelector('.efu-scenario-id')?.textContent.replace('ID: ', '').trim() || '';
          const scenario = scenarioEl.querySelector('.efu-scenario-text')?.textContent.trim() || '';
          const item     = { id: itemId, scenario, options: [] };

          scenarioEl.querySelectorAll('.efu-option-row').forEach(optEl => {
            const letter = optEl.querySelector('.efu-option-badge')?.textContent.trim() || '';
            const text   = optEl.querySelector('.efu-option-text')?.textContent.trim() || '';
            const level  = parseInt(optEl.querySelector('.efu-option-level')?.value || '1', 10);
            item.options.push({ letter, text, level });
          });

          comp.items.push(item);
        });

        pillar.competencies.push(comp);
      });

      assessment.pillars.push(pillar);
    });

    return assessment;
  }

  function generateId(label) {
    return label.toLowerCase().replace(/[^a-z0-9]+/g, '_').replace(/^_|_$/g, '');
  }

  function showStatus(msg, isError) {
    status.textContent  = msg;
    status.className    = 'efu-save-status ' + (isError ? 'err' : 'ok');
    status.style.display = 'block';
    setTimeout(() => { status.style.display = 'none'; }, 4000);
  }

  // ── Save all ──────────────────────────────────────────
  saveBtn.addEventListener('click', async () => {
    saveBtn.disabled    = true;
    saveBtn.textContent = 'Saving…';

    const assessment = buildAssessmentFromDOM();

    const body = new URLSearchParams({
      action:     'efu_sjt_save_questions',
      nonce,
      assessment: JSON.stringify(assessment),
    });

    try {
      const res  = await fetch(ajaxUrl, { method: 'POST', body });
      const data = await res.json();
      showStatus(data.success ? '✓ Questions saved successfully.' : '✗ ' + (data.data || 'Save failed.'), !data.success);
    } catch (e) {
      showStatus('✗ Network error. Please try again.', true);
    }

    saveBtn.disabled    = false;
    saveBtn.textContent = '✓ Save All Changes';
  });

  // ── Add scenario ──────────────────────────────────────
  root.querySelectorAll('.efu-add-scenario').forEach(btn => {
    btn.addEventListener('click', () => {
      const pillarIdx = btn.dataset.pillar;
      const compIdx   = btn.dataset.comp;
      const compEl    = root.querySelector(`.efu-pillar-block[data-pillar="${pillarIdx}"] .efu-competency-block[data-comp="${compIdx}"]`);
      if (!compEl) return;

      const itemCount = compEl.querySelectorAll('.efu-scenario-card').length;
      const newId     = `new_${Date.now()}`;

      const card = document.createElement('div');
      card.className = 'efu-scenario-card';
      card.dataset.itemId = newId;
      card.innerHTML = `
        <div class="efu-scenario-meta">
          <span class="efu-scenario-id">ID: ${newId}</span>
          <button class="efu-remove-scenario button button-small button-link-delete">× Remove Scenario</button>
        </div>
        <div class="efu-scenario-text-wrap">
          <label>Scenario</label>
          <div class="efu-editable efu-scenario-text" contenteditable="true">Enter scenario text here…</div>
        </div>
        <div class="efu-options-grid">
          ${['A','B','C','D'].map((letter, i) => `
            <div class="efu-option-row" data-option="${i}">
              <span class="efu-option-badge">${letter}</span>
              <div class="efu-editable efu-option-text" contenteditable="true">Option ${letter} text…</div>
              <select class="efu-option-level">
                <option value="1">L1 — Developing</option>
                <option value="2">L2 — Proficient</option>
                <option value="3" selected>L3 — Advanced</option>
                <option value="4">L4 — Role Model</option>
              </select>
            </div>
          `).join('')}
        </div>
      `;

      card.querySelector('.efu-remove-scenario').addEventListener('click', () => {
        const siblings = compEl.querySelectorAll('.efu-scenario-card').length;
        if (siblings <= 2) {
          alert('A minimum of 2 scenarios per competency is required.');
          return;
        }
        if (confirm('Remove this scenario?')) card.remove();
      });

      compEl.insertBefore(card, btn);
      updateRemoveButtons(compEl);
    });
  });

  // ── Remove scenario buttons from initial render ───────
  root.querySelectorAll('.efu-remove-scenario').forEach(btn => {
    btn.addEventListener('click', () => {
      const card    = btn.closest('.efu-scenario-card');
      const compEl  = btn.closest('.efu-competency-block');
      const siblings = compEl.querySelectorAll('.efu-scenario-card').length;
      if (siblings <= 2) {
        alert('A minimum of 2 scenarios per competency is required.');
        return;
      }
      if (confirm('Remove this scenario? This cannot be undone.')) {
        card.remove();
        updateRemoveButtons(compEl);
      }
    });
  });

  function updateRemoveButtons(compEl) {
    const cards = compEl.querySelectorAll('.efu-scenario-card');
    cards.forEach(card => {
      const btn = card.querySelector('.efu-remove-scenario');
      const warn = card.querySelector('.efu-min-warning');
      if (cards.length <= 2) {
        if (btn)  btn.style.display  = 'none';
        if (warn) warn.style.display = 'inline';
      } else {
        if (btn)  btn.style.display  = '';
        if (warn) warn.style.display = 'none';
      }
    });
  }

  // ── Store competency IDs in data attrs ────────────────
  root.querySelectorAll('.efu-competency-block').forEach(el => {
    const idEl = el.querySelector('.efu-comp-label');
    if (idEl) el.dataset.compId = generateId(idEl.textContent.trim());
  });

  root.querySelectorAll('.efu-scenario-card').forEach(el => {
    const idEl = el.querySelector('.efu-scenario-id');
    if (idEl) el.dataset.itemId = idEl.textContent.replace('ID: ', '').trim();
  });

})();
