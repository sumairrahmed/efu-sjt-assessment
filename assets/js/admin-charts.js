/* EFU SJT — Admin Charts (ApexCharts) */
(function () {
  'use strict';

  const data   = window.efuChartData || {};
  const DARK   = '#144864';
  const ACCENT = '#d90e78';
  const GREEN  = '#1dab6e';
  const ORANGE = '#e09030';
  const FONT   = 'Poppins, -apple-system, sans-serif';

  const PILLAR_LABELS = {
    growth_strategy:        'Growth & Strategy',
    customer_market_focus:  'Customer & Market',
    people_culture:         'People & Culture',
    operational_excellence: 'Operational Excellence',
    innovation_change:      'Innovation & Change',
  };

  const LEVEL_COLORS = {
    'Developing': ACCENT,
    'Proficient': DARK,
    'Advanced':   GREEN,
    'Role Model': ORANGE,
  };

  // ── Pillar bar chart ─────────────────────────────────
  const pillarEl = document.getElementById('chartPillar');
  if (pillarEl) {
    const entries = Object.entries(data.pillar_averages || {});
    const labels  = entries.map(([id])    => PILLAR_LABELS[id] || id);
    const values  = entries.map(([, val]) => parseFloat(val) || 0);

    if (values.length) {
      new ApexCharts(pillarEl, {
        series: [{ name: 'Avg Score', data: values }],
        chart: {
          type: 'bar',
          height: 260,
          toolbar: { show: false },
          fontFamily: FONT,
          animations: { enabled: true, speed: 600 },
        },
        plotOptions: {
          bar: { borderRadius: 6, columnWidth: '50%', distributed: true },
        },
        colors: [DARK, '#1a5e82', '#20739e', '#2689bb', '#2c9ed8'],
        legend: { show: false },
        xaxis: {
          categories: labels,
          labels: {
            style: { fontSize: '11px', fontFamily: FONT },
            rotate: -20,
          },
          axisBorder: { show: false },
          axisTicks: { show: false },
        },
        yaxis: {
          min: 0,
          max: 4,
          tickAmount: 4,
          labels: {
            style: { fontSize: '10px', fontFamily: FONT },
            formatter: v => {
              const map = { 0: '0', 1: 'Developing', 2: 'Proficient', 3: 'Advanced', 4: 'Role Model' };
              return map[Math.round(v)] ?? v.toFixed(1);
            },
          },
        },
        tooltip: {
          y: {
            formatter: v => v.toFixed(2) + ' / 4.0',
            title: { formatter: () => 'Score' },
          },
        },
        grid: { borderColor: '#e8edf1', strokeDashArray: 4 },
        dataLabels: {
          enabled: true,
          formatter: v => v.toFixed(2),
          style: { fontSize: '11px', fontFamily: FONT, fontWeight: '600' },
          offsetY: -4,
        },
      }).render();
    } else {
      pillarEl.innerHTML = '<p style="text-align:center;color:#8aa0ae;padding:50px 0;font-family:Poppins,sans-serif;">No data yet</p>';
    }
  }

  // ── Level doughnut chart ─────────────────────────────
  const levelEl = document.getElementById('chartLevels');
  if (levelEl) {
    const levels = data.levels || [];

    if (levels.length) {
      const labels = levels.map(l => l.overall_level);
      const values = levels.map(l => parseInt(l.cnt, 10));
      const colors = labels.map(l => LEVEL_COLORS[l] || '#aaa');

      new ApexCharts(levelEl, {
        series: values,
        labels,
        chart: {
          type: 'donut',
          height: 260,
          fontFamily: FONT,
          animations: { enabled: true, speed: 600 },
        },
        colors,
        legend: {
          position: 'bottom',
          fontSize: '12px',
          fontFamily: FONT,
          fontWeight: '500',
          markers: { width: 10, height: 10, radius: 5 },
        },
        plotOptions: {
          pie: {
            donut: {
              size: '62%',
              labels: {
                show: true,
                total: {
                  show: true,
                  label: 'Total',
                  fontSize: '13px',
                  fontFamily: FONT,
                  fontWeight: '600',
                  color: DARK,
                  formatter: w => w.globals.seriesTotals.reduce((a, b) => a + b, 0),
                },
                value: {
                  fontSize: '20px',
                  fontFamily: FONT,
                  fontWeight: '700',
                  color: DARK,
                },
              },
            },
          },
        },
        dataLabels: { enabled: false },
        tooltip: { y: { formatter: v => v + ' submissions' } },
        stroke: { width: 2 },
      }).render();
    } else {
      levelEl.innerHTML = '<p style="text-align:center;color:#8aa0ae;padding:50px 0;font-family:Poppins,sans-serif;">No submissions yet</p>';
    }
  }

  // ── Daily submissions area chart ─────────────────────
  const dailyEl = document.getElementById('chartDaily');
  if (dailyEl) {
    const dayMap = {};
    (data.daily || []).forEach(d => { dayMap[d.day] = parseInt(d.cnt, 10); });

    const labels = [];
    const values = [];
    for (let i = 29; i >= 0; i--) {
      const date = new Date();
      date.setDate(date.getDate() - i);
      const key = date.toISOString().slice(0, 10);
      labels.push(date.toLocaleDateString('en-GB', { day: 'numeric', month: 'short' }));
      values.push(dayMap[key] || 0);
    }

    new ApexCharts(dailyEl, {
      series: [{ name: 'Submissions', data: values }],
      chart: {
        type: 'area',
        height: 220,
        toolbar: { show: false },
        fontFamily: FONT,
        zoom: { enabled: false },
        animations: { enabled: true, speed: 600 },
        sparkline: { enabled: false },
      },
      colors: [DARK],
      fill: {
        type: 'gradient',
        gradient: {
          shadeIntensity: 1,
          opacityFrom: 0.35,
          opacityTo: 0.02,
          stops: [0, 100],
        },
      },
      stroke: { curve: 'smooth', width: 2.5 },
      markers: {
        size: 4,
        colors: [ACCENT],
        strokeWidth: 0,
        hover: { size: 6 },
      },
      xaxis: {
        categories: labels,
        tickAmount: 9,
        labels: {
          rotate: -35,
          style: { fontSize: '10px', fontFamily: FONT },
        },
        axisBorder: { show: false },
        axisTicks: { show: false },
      },
      yaxis: {
        min: 0,
        tickAmount: 3,
        labels: {
          formatter: v => Math.round(v),
          style: { fontSize: '11px', fontFamily: FONT },
        },
      },
      tooltip: {
        x: { show: true },
        y: { formatter: v => v + (v === 1 ? ' submission' : ' submissions') },
      },
      grid: { borderColor: '#e8edf1', strokeDashArray: 4 },
      dataLabels: { enabled: false },
    }).render();
  }
})();
