/**
 * dashboard-utils.js
 * Shared utilities for the user dashboard:
 *   - Sidebar mobile toggle
 *   - Toast notification system
 *   - HTML escaping helper
 *   - PHP currency formatter
 *
 * Used by: dashboard/user.php
 */

/* ── Sidebar ─────────────────────────────────────────── */
function toggleSidebar() {
    document.getElementById('sidebar').classList.toggle('show');
}

/* ── Toast Notifications ─────────────────────────────── */

/**
 * Show a self-dismissing toast message.
 *
 * @param {string} message  - Text to display.
 * @param {'success'|'error'|'warning'} type - Visual style.
 */
function showToast(message, type = 'success') {
    const container = document.getElementById('toast-container');
    const id        = 'toast-' + Date.now();

    const bgColor = type === 'success' ? 'bg-success'
                  : type === 'error'   ? 'bg-danger'
                  :                      'bg-warning';

    const icon = type === 'success' ? 'fa-check-circle'
               : type === 'error'   ? 'fa-exclamation-circle'
               :                      'fa-info-circle';

    container.insertAdjacentHTML('beforeend', `
        <div id="${id}" class="toast show" role="alert"
             aria-live="assertive" aria-atomic="true"
             data-bs-autohide="true" data-bs-delay="5000">
            <div class="toast-header ${bgColor} text-white">
                <i class="fas ${icon} me-2"></i>
                <strong class="me-auto">UMDC Notification</strong>
                <button type="button" class="btn-close btn-close-white"
                        data-bs-dismiss="toast"></button>
            </div>
            <div class="toast-body">
                ${escapeHtml(message)}
            </div>
        </div>
    `);

    const el = document.getElementById(id);
    setTimeout(() => el && el.remove(), 5000);
}

/* ── HTML Escaping ───────────────────────────────────── */

/**
 * Safely escape a string for insertion into HTML.
 *
 * @param {string} text
 * @returns {string}
 */
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

/* ── Currency Formatting ─────────────────────────────── */

/**
 * Format a number as Philippine Peso (₱).
 *
 * @param {number} amount
 * @returns {string}
 */
function formatCurrency(amount) {
    return new Intl.NumberFormat('en-PH', {
        style:    'currency',
        currency: 'PHP',
    }).format(amount);
}
