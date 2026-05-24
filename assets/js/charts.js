/**
 * UMDC charts.js
 * Auto-initialises Chart.js charts based on canvas id slugs.
 * Runs after Chart.js is loaded.
 */
(function () {
  'use strict';

  const LABELS_MONTHLY = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'];

  // ── Colour palette (matches CSS vars) ──────────────────────────────────────
  const C = {
    accent:  '#f97316',
    green:   '#22c55e',
    blue:    '#3b82f6',
    yellow:  '#f59e0b',
    red:     '#ef4444',
    purple:  '#a855f7',
    pink:    '#ec4899',
    muted:   'rgba(255,255,255,0.12)',
    gridLine:'rgba(255,255,255,0.06)',
    text:    '#8b92a7',
  };

  // ── Shared defaults ─────────────────────────────────────────────────────────
  Chart.defaults.color            = C.text;
  Chart.defaults.font.family      = "'DM Sans', sans-serif";
  Chart.defaults.font.size        = 11;
  Chart.defaults.plugins.legend.display = false;

  function gridOpts() {
    return { color: C.gridLine, drawBorder: false };
  }
  function tickOpts() {
    return { color: C.text };
  }
  function tooltip() {
    return {
      backgroundColor: '#1a1f2e',
      borderColor: 'rgba(255,255,255,0.08)',
      borderWidth: 1,
      titleColor: '#e8eaf0',
      bodyColor: C.text,
      padding: 10,
    };
  }

  // ── Dataset helpers ─────────────────────────────────────────────────────────
  function lineDataset(label, data, color) {
    return {
      label,
      data,
      borderColor: color,
      backgroundColor: color + '22',
      fill: true,
      tension: 0.4,
      pointBackgroundColor: color,
      pointRadius: 3,
      pointHoverRadius: 5,
    };
  }
  function barDataset(label, data, color) {
    return {
      label,
      data,
      backgroundColor: color + '99',
      borderColor: color,
      borderWidth: 1,
      borderRadius: 4,
    };
  }

  // ── Chart definitions by id-slug keyword ────────────────────────────────────
  function buildChart(canvas) {
    const id = canvas.id.toLowerCase();

    // ── Donations overview (line) ──────────────────────────────────────────
    if (id.includes('overview') || id.includes('donations-overview') || id.includes('trend')) {
      return new Chart(canvas, {
        type: 'line',
        data: {
          labels: LABELS_MONTHLY,
          datasets: [lineDataset('Donations ($)', [5500, 8200, 11000, 14500, 18000, 20800], C.accent)],
        },
        options: {
          responsive: true, maintainAspectRatio: false,
          plugins: { tooltip: tooltip() },
          scales: { x: { grid: gridOpts(), ticks: tickOpts() }, y: { grid: gridOpts(), ticks: tickOpts() } },
        },
      });
    }

    // ── By month (bar) ─────────────────────────────────────────────────────
    if (id.includes('by-month') || id.includes('month')) {
      return new Chart(canvas, {
        type: 'bar',
        data: {
          labels: LABELS_MONTHLY,
          datasets: [barDataset('Count', [18, 32, 45, 52, 65, 72], C.blue)],
        },
        options: {
          responsive: true, maintainAspectRatio: false,
          plugins: { tooltip: tooltip() },
          scales: { x: { grid: gridOpts(), ticks: tickOpts() }, y: { grid: gridOpts(), ticks: tickOpts() } },
        },
      });
    }

    // ── User growth (line) ─────────────────────────────────────────────────
    if (id.includes('user-growth') || id.includes('growth')) {
      return new Chart(canvas, {
        type: 'line',
        data: {
          labels: LABELS_MONTHLY,
          datasets: [lineDataset('Users', [200, 350, 480, 600, 720, 850], C.green)],
        },
        options: {
          responsive: true, maintainAspectRatio: false,
          plugins: { tooltip: tooltip() },
          scales: { x: { grid: gridOpts(), ticks: tickOpts() }, y: { grid: gridOpts(), ticks: tickOpts() } },
        },
      });
    }

    // ── Approval activity (bar) ────────────────────────────────────────────
    if (id.includes('approval')) {
      return new Chart(canvas, {
        type: 'bar',
        data: {
          labels: LABELS_MONTHLY,
          datasets: [
            barDataset('Approved', [30, 45, 38, 52, 60, 65], C.green),
            barDataset('Rejected', [5, 8, 4, 9, 7, 11], C.red),
          ],
        },
        options: {
          responsive: true, maintainAspectRatio: false,
          plugins: { tooltip: tooltip(), legend: { display: true, labels: { color: C.text } } },
          scales: { x: { grid: gridOpts(), ticks: tickOpts() }, y: { grid: gridOpts(), ticks: tickOpts() } },
        },
      });
    }

    // ── Fraud detection (line) ─────────────────────────────────────────────
    if (id.includes('fraud') || id.includes('detection')) {
      return new Chart(canvas, {
        type: 'line',
        data: {
          labels: LABELS_MONTHLY,
          datasets: [lineDataset('Flagged', [3, 8, 5, 12, 7, 15], C.red)],
        },
        options: {
          responsive: true, maintainAspectRatio: false,
          plugins: { tooltip: tooltip() },
          scales: { x: { grid: gridOpts(), ticks: tickOpts() }, y: { grid: gridOpts(), ticks: tickOpts() } },
        },
      });
    }

    // ── Blocked / transactions (bar) ───────────────────────────────────────
    if (id.includes('blocked') || id.includes('transactions')) {
      return new Chart(canvas, {
        type: 'bar',
        data: {
          labels: LABELS_MONTHLY,
          datasets: [barDataset('Blocked', [2, 6, 3, 9, 5, 12], C.yellow)],
        },
        options: {
          responsive: true, maintainAspectRatio: false,
          plugins: { tooltip: tooltip() },
          scales: { x: { grid: gridOpts(), ticks: tickOpts() }, y: { grid: gridOpts(), ticks: tickOpts() } },
        },
      });
    }

    // ── Reviews overview (line) ────────────────────────────────────────────
    if (id.includes('reviews')) {
      return new Chart(canvas, {
        type: 'line',
        data: {
          labels: LABELS_MONTHLY,
          datasets: [lineDataset('Reviews', [80, 130, 160, 210, 250, 270], C.purple)],
        },
        options: {
          responsive: true, maintainAspectRatio: false,
          plugins: { tooltip: tooltip() },
          scales: { x: { grid: gridOpts(), ticks: tickOpts() }, y: { grid: gridOpts(), ticks: tickOpts() } },
        },
      });
    }

    // ── Donor type doughnut ────────────────────────────────────────────────
    if (id.includes('doughnut') || id.includes('annual-corporate') || id.includes('donor-type') || id.includes('distribution-1')) {
      return new Chart(canvas, {
        type: 'doughnut',
        data: {
          labels: ['Annual', 'Corporate', 'Monthly', 'One-time'],
          datasets: [{ data: [25, 30, 28, 17], backgroundColor: [C.yellow, C.red, C.green, C.blue], borderWidth: 0 }],
        },
        options: {
          responsive: true, maintainAspectRatio: false,
          plugins: { tooltip: tooltip(), legend: { display: true, position: 'bottom', labels: { color: C.text, padding: 12 } } },
          cutout: '65%',
        },
      });
    }

    // ── Geographic (horizontal bar) ────────────────────────────────────────
    if (id.includes('geographic')) {
      return new Chart(canvas, {
        type: 'bar',
        data: {
          labels: ['N. America', 'Europe', 'Asia', 'Australia', 'S. America'],
          datasets: [barDataset('Amount ($)', [58000, 42000, 31000, 18000, 12000], C.accent)],
        },
        options: {
          indexAxis: 'y',
          responsive: true, maintainAspectRatio: false,
          plugins: { tooltip: tooltip() },
          scales: { x: { grid: gridOpts(), ticks: tickOpts() }, y: { grid: { display: false }, ticks: tickOpts() } },
        },
      });
    }

    // ── Shipments / transport mode (doughnut) ──────────────────────────────
    if (id.includes('transport') || id.includes('shipment')) {
      return new Chart(canvas, {
        type: 'doughnut',
        data: {
          labels: ['Truck', 'Air', 'Sea', 'Courier'],
          datasets: [{ data: [18, 12, 9, 6], backgroundColor: [C.blue, C.accent, C.green, C.purple], borderWidth: 0 }],
        },
        options: {
          responsive: true, maintainAspectRatio: false,
          plugins: { tooltip: tooltip(), legend: { display: true, position: 'bottom', labels: { color: C.text, padding: 12 } } },
          cutout: '60%',
        },
      });
    }

    // ── Deliveries by month (bar) ──────────────────────────────────────────
    if (id.includes('deliveries') || id.includes('delivered')) {
      return new Chart(canvas, {
        type: 'bar',
        data: {
          labels: LABELS_MONTHLY,
          datasets: [barDataset('Deliveries', [12, 18, 9, 6, 15, 22], C.teal || C.green)],
        },
        options: {
          responsive: true, maintainAspectRatio: false,
          plugins: { tooltip: tooltip() },
          scales: { x: { grid: gridOpts(), ticks: tickOpts() }, y: { grid: gridOpts(), ticks: tickOpts() } },
        },
      });
    }

    // ── Orgs growth (line) ─────────────────────────────────────────────────
    if (id.includes('organizations') || id.includes('org')) {
      return new Chart(canvas, {
        type: 'line',
        data: {
          labels: LABELS_MONTHLY,
          datasets: [lineDataset('Organizations', [280, 295, 310, 325, 336, 348], C.blue)],
        },
        options: {
          responsive: true, maintainAspectRatio: false,
          plugins: { tooltip: tooltip() },
          scales: { x: { grid: gridOpts(), ticks: tickOpts() }, y: { grid: gridOpts(), ticks: tickOpts() } },
        },
      });
    }

    // ── Avg donation trend (line) ──────────────────────────────────────────
    if (id.includes('avg') || id.includes('average')) {
      return new Chart(canvas, {
        type: 'line',
        data: {
          labels: LABELS_MONTHLY,
          datasets: [lineDataset('Avg ($)', [220, 240, 260, 270, 275, 290], C.green)],
        },
        options: {
          responsive: true, maintainAspectRatio: false,
          plugins: { tooltip: tooltip() },
          scales: { x: { grid: gridOpts(), ticks: tickOpts() }, y: { grid: gridOpts(), ticks: tickOpts() } },
        },
      });
    }

    // ── Fallback: generic line chart ───────────────────────────────────────
    return new Chart(canvas, {
      type: 'line',
      data: {
        labels: LABELS_MONTHLY,
        datasets: [lineDataset('Value', [10, 25, 18, 40, 35, 55], C.accent)],
      },
      options: {
        responsive: true, maintainAspectRatio: false,
        plugins: { tooltip: tooltip() },
        scales: { x: { grid: gridOpts(), ticks: tickOpts() }, y: { grid: gridOpts(), ticks: tickOpts() } },
      },
    });
  }

  // ── Boot ────────────────────────────────────────────────────────────────────
  document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.chart-wrap canvas').forEach(buildChart);
  });
})();
