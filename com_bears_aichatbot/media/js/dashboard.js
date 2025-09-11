(function(){
  function qs(sel, root){ return (root||document).querySelector(sel); }
  function qsa(sel, root){ return Array.prototype.slice.call((root||document).querySelectorAll(sel)); }
  function toParams(form){ const p = new URLSearchParams(); qsa('input,select', form).forEach(el=>{ if (el.name) p.set(el.name, el.value); }); return p; }
  function fetchJson(url){ return fetch(url, { headers: { 'Accept': 'application/json' } }).then(r=>r.json()); }
  function number(n){ return (n||0).toLocaleString(); }

  let chart, chartReq, chartCol, chartSpend, chartLatency, chartHist, chartOutcomes;

  function buildCsvUrl(params){
    const base = window.BAICHATBOT.base + 'index.php?option=' + window.BAICHATBOT.option + '&task=api.usageCsv&' + params.toString() + '&' + window.BAICHATBOT.token + '=1';
    return base;
  }

  function renderChart(rows){
    const ctx = qs('#chart-usage');
    const labels = rows.map(r=>r.period);
    const prompt = rows.map(r=>parseInt(r.prompt_tokens||0,10));
    const completion = rows.map(r=>parseInt(r.completion_tokens||0,10));
    if (chart) chart.destroy();
    chart = new Chart(ctx, {
      type: 'bar',
      data: {
        labels,
        datasets: [
          { label: 'Prompt', data: prompt, backgroundColor: 'rgba(54, 162, 235, 0.6)' },
          { label: 'Completion', data: completion, backgroundColor: 'rgba(255, 159, 64, 0.6)' }
        ]
      },
      options: {
        responsive: true,
        scales: { x: { stacked: true }, y: { stacked: true, beginAtZero: true } },
        plugins: { legend: { position: 'bottom' } }
      }
    });
  }

  function renderRequests(rows){
    const ctx = qs('#chart-requests');
    const labels = rows.map(r=>r.period);
    const reqs = rows.map(r=>parseInt(r.requests||0,10));
    const errs = rows.map(r=>parseInt(r.errors||0,10));
    if (chartReq) chartReq.destroy();
    chartReq = new Chart(ctx, {
      type: 'line',
      data: { labels, datasets: [
        { label: 'Requests', data: reqs, borderColor: 'rgba(54, 162, 235, 1)', backgroundColor: 'rgba(54,162,235,0.1)', tension: 0.2 },
        { label: 'Errors', data: errs, borderColor: 'rgba(255, 99, 132, 1)', backgroundColor: 'rgba(255,99,132,0.1)', tension: 0.2 }
      ] },
      options: { responsive: true, plugins: { legend: { position: 'bottom' } }, scales: { y: { beginAtZero: true } } }
    });
  }

  function renderCollection(rows){
    const ctx = qs('#chart-collection');
    const labels = rows.map(r=>r.stat_date);
    const vals = rows.map(r=>parseInt(r.docs_count||0,10));
    if (chartCol) chartCol.destroy();
    chartCol = new Chart(ctx, { type: 'line', data: { labels, datasets: [{ label: 'Docs', data: vals, borderColor: 'rgba(99, 132, 255, 1)', backgroundColor: 'rgba(99,132,255,0.1)', tension: 0.2 }] }, options: { responsive: true, plugins: { legend: { position: 'bottom' } }, scales: { y: { beginAtZero: true } } } });
  }

  function loadFilterOptions(){
    const base = window.BAICHATBOT.base + 'index.php?option=' + window.BAICHATBOT.option + '&task=';
    fetchJson(base + 'api.filtersJson').then(resp => {
      const data = resp && resp.data ? resp.data : {};
      const modSel = qs('#flt-module');
      const mdlSel = qs('#flt-model');
      const colSel = qs('#flt-collection');
      function opt(el, val, text){ const o = document.createElement('option'); o.value = val; o.textContent = text; el.appendChild(o); }
      [modSel, mdlSel, colSel].forEach(el => { el.innerHTML = ''; opt(el, '', '—'); });
      (data.modules||[]).forEach(v => opt(modSel, String(v), String(v)));
      (data.models||[]).forEach(v => opt(mdlSel, String(v), String(v)));
      (data.collections||[]).forEach(v => opt(colSel, String(v), String(v)));
    });
  }

  function renderSpend(rows){
    const ctx = qs('#chart-spend');
    const labels = rows.map(r=>r.period);
    const vals = rows.map(r=>parseFloat(r.cost||0));
    if (chartSpend) chartSpend.destroy();
    chartSpend = new Chart(ctx, { type: 'bar', data: { labels, datasets: [{ label: 'USD', data: vals, backgroundColor: 'rgba(75, 192, 192, 0.6)' }] }, options: { responsive: true, plugins: { legend: { position: 'bottom' } }, scales: { y: { beginAtZero: true } } } });
  }

  function renderLatency(rows){
    const ctx = qs('#chart-latency');
    const labels = rows.map(r=>r.period);
    const avg = rows.map(r=>parseInt(r.avg_ms||0,10));
    const max = rows.map(r=>parseInt(r.max_ms||0,10));
    if (chartLatency) chartLatency.destroy();
    chartLatency = new Chart(ctx, { type: 'line', data: { labels, datasets: [
      { label: 'Avg', data: avg, borderColor: 'rgba(153, 102, 255, 1)', backgroundColor: 'rgba(153,102,255,0.1)', tension: 0.2 },
      { label: 'Max', data: max, borderColor: 'rgba(201, 203, 207, 1)', backgroundColor: 'rgba(201,203,207,0.1)', tension: 0.2 }
    ] }, options: { responsive: true, plugins: { legend: { position: 'bottom' } } } });
  }

  function renderHist(rows){
    const ctx = qs('#chart-hist');
    const labels = rows.map(r=>r.bucket);
    const counts = rows.map(r=>parseInt(r.count||0,10));
    if (chartHist) chartHist.destroy();
    chartHist = new Chart(ctx, { type: 'bar', data: { labels, datasets: [{ label: 'Count', data: counts, backgroundColor: 'rgba(255, 205, 86, 0.6)' }] }, options: { responsive: true, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true } } } });
  }

  function renderOutcomes(rows){
    const ctx = qs('#chart-outcomes');
    const labels = rows.map(r=>r.period);
    const answered = rows.map(r=>parseInt(r.answered||0,10));
    const refused = rows.map(r=>parseInt(r.refused||0,10));
    const error = rows.map(r=>parseInt(r.error||0,10));
    if (chartOutcomes) chartOutcomes.destroy();
    chartOutcomes = new Chart(ctx, { type: 'bar', data: { labels, datasets: [
      { label: 'Answered', data: answered, backgroundColor: 'rgba(75, 192, 192, 0.6)' },
      { label: 'Refused', data: refused, backgroundColor: 'rgba(255, 206, 86, 0.6)' },
      { label: 'Error', data: error, backgroundColor: 'rgba(255, 99, 132, 0.6)' }
    ] }, options: { responsive: true, plugins: { legend: { position: 'bottom' } }, scales: { x: { stacked: true }, y: { stacked: true, beginAtZero: true } } } });
  }

  function refresh(){
    const form = qs('#baichatbot-filters');
    const params = toParams(form);
    // default 30-day window if none provided
    if (!params.get('from')) {
      const d = new Date(); d.setDate(d.getDate()-30);
      params.set('from', d.toISOString().slice(0,10));
    }
    if (!params.get('to')) {
      const d2 = new Date();
      params.set('to', d2.toISOString().slice(0,10));
    }

    const base = window.BAICHATBOT.base + 'index.php?option=' + window.BAICHATBOT.option + '&task=';

    fetchJson(base + 'api.kpisJson&' + params.toString()).then(resp=>{
      const k = resp && resp.data ? resp.data : {};
      qs('#kpi-requests').textContent = number(k.requests||0);
      qs('#kpi-total').textContent = number(k.total_tokens||0);
      qs('#kpi-prompt').textContent = number(k.prompt_tokens||0);
      qs('#kpi-completion').textContent = number(k.completion_tokens||0);
      qs('#kpi-retrieved').textContent = number(k.retrieved||0);
      qs('#kpi-docs').textContent = number(k.docs||0);
      const cost = parseFloat(k.total_cost||0);
      qs('#kpi-cost').textContent = '$' + (isFinite(cost) ? cost.toFixed(6) : '0.000000');
    });

    fetchJson(base + 'api.usageJson&' + params.toString()).then(resp=>{
      const rows = resp && resp.data ? resp.data : [];
      renderChart(rows);
    });
    fetchJson(base + 'api.seriesJson&' + params.toString()).then(resp=>{
      const rows = resp && resp.data ? resp.data : [];
      renderRequests(rows);
    });
    fetchJson(base + 'api.collectionJson&' + params.toString()).then(resp=>{
      const rows = resp && resp.data ? resp.data : [];
      renderCollection(rows);
    });
    fetchJson(base + 'api.spendJson&' + params.toString()).then(resp=>{
      const rows = resp && resp.data ? resp.data : [];
      renderSpend(rows);
    });
    fetchJson(base + 'api.latencyJson&' + params.toString()).then(resp=>{
      const rows = resp && resp.data ? resp.data : [];
      renderLatency(rows);
    });
    fetchJson(base + 'api.histTokensJson&' + params.toString()).then(resp=>{
      const rows = resp && resp.data ? resp.data : [];
      renderHist(rows);
    });
    fetchJson(base + 'api.outcomesJson&' + params.toString()).then(resp=>{
      const rows = resp && resp.data ? resp.data : [];
      renderOutcomes(rows);
    });

    // Collection metadata
    fetchJson(base + 'api.collectionMetaJson').then(resp=>{
      const metaDiv = qs('#collection-meta');
      const d = resp && resp.data ? resp.data : null;
      if (!metaDiv) return;
      if (!d) { metaDiv.textContent = 'No collection metadata available.'; return; }
      const lines = [];
      if (d.name) lines.push('Name: ' + d.name);
      if (d.description) lines.push('Description: ' + d.description);
      if (d.createdAt || d.created_at) lines.push('Created: ' + (d.createdAt || d.created_at));
      if (d.updatedAt || d.updated_at) lines.push('Updated: ' + (d.updatedAt || d.updated_at));
      if (typeof d.documentsCount !== 'undefined') lines.push('Documents: ' + d.documentsCount);
      metaDiv.innerHTML = lines.join('<br>');
    });

    // Update CSV export link
    qs('#baichatbot-export').href = buildCsvUrl(params);
  }

  function bind(){
    const form = qs('#baichatbot-filters');
    qs('#baichatbot-apply').addEventListener('click', refresh);
    qs('#baichatbot-reset').addEventListener('click', function(){ qsa('input,select', form).forEach(el=>{ if (el.type==='date'||el.type==='number'||el.type==='text') el.value=''; if(el.name==='group') el.value='day'; }); refresh(); });

    const btnRebuild = qs('#btn-rebuild-collection');
    if (btnRebuild) {
      btnRebuild.addEventListener('click', async function(){
        const status = qs('#rebuild-status');
        const proceed = window.confirm(
          'This will rebuild the existing document collection in place:\n' +
          '- All documents in the current collection will be deleted\n' +
          '- A full re-sync will be enqueued to repopulate it from Joomla content\n' +
          '- During reindexing, some answers may be incomplete\n' +
          'No new collection will be created. Continue?'
        );
        if (!proceed) {
          if (status) { status.textContent = 'Rebuild canceled.'; }
          return;
        }
        if (status) { status.textContent = 'Rebuilding (in-place)...'; }
        try {
          const res = await fetch(window.BAICHATBOT.base + 'index.php?option=' + window.BAICHATBOT.option + '&task=api.rebuildCollection', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8', 'Accept': 'application/json' },
            body: window.BAICHATBOT.token + '=1&recreate=0'
          });
          const json = await res.json();
          if (json && json.data) {
            if (status) { status.textContent = 'Collection rebuilt (in-place): ' + (json.data.collection_id || '') + ' | Enqueued: ' + (json.data.enqueued || 0); }
            // Refresh meta and collection size chart after short delay
            setTimeout(refresh, 1000);
          } else if (json && json.error) {
            if (status) { status.textContent = 'Error: ' + json.error; }
          } else {
            if (status) { status.textContent = 'Unexpected response.'; }
          }
        } catch (e) {
          if (status) { status.textContent = 'Error: ' + (e && e.message ? e.message : 'Network error'); }
        }
      });
    }
  }

  document.addEventListener('DOMContentLoaded', function(){
    bind();
    loadFilterOptions();
    refresh();
  });
})();
