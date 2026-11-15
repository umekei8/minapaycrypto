/* ===== MinaPay Crypto Admin — app.js ===== */

'use strict';

// ── Toast ───────────────────────────────────────────────────────────────────
let toastWrap = null;

function showToast(message, type = 'success') {
  if (!toastWrap) {
    toastWrap = document.createElement('div');
    toastWrap.className = 'toast-wrap';
    document.body.appendChild(toastWrap);
  }
  const t = document.createElement('div');
  t.className = 'toast toast-' + type;
  t.textContent = message;
  toastWrap.appendChild(t);
  setTimeout(() => t.remove(), 3200);
}

// ── Modal helpers ─────────────────────────────────────────────────────────
function openModal(id) {
  const el = document.getElementById(id);
  if (el) el.hidden = false;
}

function closeModal(id) {
  const el = document.getElementById(id);
  if (el) el.hidden = true;
}

// Close modal on Escape key
document.addEventListener('keydown', function(e) {
  if (e.key === 'Escape') {
    document.querySelectorAll('.modal:not([hidden])').forEach(m => m.hidden = true);
  }
});

// ── Charts (lightweight canvas impl) ─────────────────────────────────────
function drawBarChart(canvasId, labels, values, color) {
  const canvas = document.getElementById(canvasId);
  if (!canvas) return;
  const ctx = canvas.getContext('2d');
  const W = canvas.offsetWidth || 400;
  const H = 200;
  canvas.width = W;
  canvas.height = H;

  const pad = { top: 10, right: 10, bottom: 28, left: 50 };
  const max = Math.max(...values, 1);
  const barW = (W - pad.left - pad.right) / labels.length;

  ctx.clearRect(0, 0, W, H);
  ctx.fillStyle = '#f9f9fb';
  ctx.fillRect(0, 0, W, H);

  // Grid lines (5 lines)
  ctx.strokeStyle = '#e5e7eb';
  ctx.lineWidth = 1;
  for (let i = 0; i <= 4; i++) {
    const y = pad.top + ((H - pad.top - pad.bottom) * i / 4);
    ctx.beginPath();
    ctx.moveTo(pad.left, y);
    ctx.lineTo(W - pad.right, y);
    ctx.stroke();
    // Y label
    ctx.fillStyle = '#9ca3af';
    ctx.font = '10px system-ui';
    ctx.textAlign = 'right';
    const val = max * (1 - i / 4);
    ctx.fillText(val >= 10000 ? (val/10000).toFixed(1)+'万' : val.toFixed(0), pad.left - 4, y + 3);
  }

  // Bars
  values.forEach((v, i) => {
    const bh = ((H - pad.top - pad.bottom) * v / max);
    const x  = pad.left + i * barW + barW * 0.15;
    const y  = H - pad.bottom - bh;
    ctx.fillStyle = color || '#c0392b';
    ctx.beginPath();
    ctx.roundRect ? ctx.roundRect(x, y, barW * 0.7, bh, [3, 3, 0, 0]) :
                    ctx.rect(x, y, barW * 0.7, bh);
    ctx.fill();

    // X label
    if (labels.length <= 24) {
      ctx.fillStyle = '#9ca3af';
      ctx.font = '9px system-ui';
      ctx.textAlign = 'center';
      if (i % Math.ceil(labels.length / 12) === 0) {
        ctx.fillText(labels[i], x + barW * 0.35, H - pad.bottom + 12);
      }
    }
  });
}

function drawDonutChart(canvasId, labels, values, colors) {
  const canvas = document.getElementById(canvasId);
  if (!canvas) return;
  const SIZE = Math.min(canvas.offsetWidth || 220, 220);
  canvas.width = SIZE;
  canvas.height = SIZE;
  const ctx = canvas.getContext('2d');
  const cx = SIZE / 2, cy = SIZE / 2, r = SIZE * 0.38, ir = SIZE * 0.22;

  ctx.clearRect(0, 0, SIZE, SIZE);
  const total = values.reduce((a, b) => a + b, 0) || 1;
  let start = -Math.PI / 2;

  values.forEach((v, i) => {
    const angle = (v / total) * 2 * Math.PI;
    ctx.beginPath();
    ctx.moveTo(cx, cy);
    ctx.arc(cx, cy, r, start, start + angle);
    ctx.closePath();
    ctx.fillStyle = colors[i % colors.length];
    ctx.fill();
    start += angle;
  });

  // Donut hole
  ctx.beginPath();
  ctx.arc(cx, cy, ir, 0, 2 * Math.PI);
  ctx.fillStyle = '#f9f9fb';
  ctx.fill();

  // Center text
  ctx.fillStyle = '#1a1a1a';
  ctx.font = `bold ${Math.round(SIZE * 0.1)}px system-ui`;
  ctx.textAlign = 'center';
  ctx.textBaseline = 'middle';
  ctx.fillText(values.length + '通貨', cx, cy);

  // Legend
  if (labels.length) {
    const lx = SIZE * 0.04;
    let ly = SIZE * 0.78;
    labels.forEach((label, i) => {
      ctx.fillStyle = colors[i % colors.length];
      ctx.fillRect(lx, ly - 7, 10, 10);
      ctx.fillStyle = '#6b7280';
      ctx.font = `${Math.round(SIZE * 0.07)}px system-ui`;
      ctx.textAlign = 'left';
      ctx.textBaseline = 'middle';
      ctx.fillText(label, lx + 14, ly - 1);
      ly += SIZE * 0.09;
    });
  }
}

// ── Sidebar hamburger ─────────────────────────────────────────────────────
document.addEventListener('click', function(e) {
  const sidebar = document.getElementById('sidebar');
  if (!sidebar) return;
  if (sidebar.classList.contains('open') &&
      !sidebar.contains(e.target) &&
      !e.target.closest('.menu-toggle')) {
    sidebar.classList.remove('open');
  }
});
