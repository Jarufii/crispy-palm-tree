/**
 * auth-pages.js
 * Shared JS for login.html and register.html.
 *
 *  Login page features:
 *    - Show error message from ?error= query param
 *    - Disable submit button while the form is posting
 *
 *  Register page features:
 *    - Role toggle (user / organization) that updates the visual panel theme
 *    - Password strength indicator
 *    - Confirm-password match validation before submit
 *
 * Used by: public/login.html, public/register.html
 */

/* ── Login ───────────────────────────────────────────── */

(function initLogin() {
    const loginForm = document.getElementById('loginForm');
    if (!loginForm) return; // Not on the login page

    // Show inline error from query string (?error=invalid)
    const errorParam = new URLSearchParams(window.location.search).get('error');
    if (errorParam === 'invalid') {
        const msg = document.getElementById('errorMessage');
        if (msg) {
            msg.classList.add('show');
            setTimeout(() => msg.classList.remove('show'), 5000);
        }
    }

    // Disable button while posting
    loginForm.addEventListener('submit', function () {
        const btn = this.querySelector('.login-btn');
        if (btn) {
            btn.innerHTML  = 'Signing in...';
            btn.disabled   = true;
        }
    });
}());

/* ── Register ────────────────────────────────────────── */

(function initRegister() {
    const registerForm = document.getElementById('registerForm');
    if (!registerForm) return; // Not on the register page

    /* -- Role toggle -- */
    const roleBtns   = document.querySelectorAll('.role-option');
    const roleInput  = document.getElementById('roleInput');
    const visualPanel = document.querySelector('.visual-panel');

    roleBtns.forEach(btn => {
        btn.setAttribute('data-role', btn.dataset.role || btn.id.replace('Toggle','').toLowerCase());
        btn.addEventListener('click', function () {
            roleBtns.forEach(b => b.classList.remove('active'));
            this.classList.add('active');

            const role = this.dataset.role;
            if (roleInput) roleInput.value = role;

            if (visualPanel) {
                visualPanel.classList.remove('user-theme', 'org-theme');
                visualPanel.classList.add(role === 'organization' ? 'org-theme' : 'user-theme');
            }

            // Update visual panel text if elements exist
            const icon  = document.getElementById('panelIcon');
            const title = document.getElementById('panelTitle');
            const desc  = document.getElementById('panelDescription');

            if (role === 'organization') {
                if (icon)  icon.textContent  = '🏢';
                if (title) title.textContent = 'For Organizations';
                if (desc)  desc.textContent  = 'Create and manage fundraising campaigns for your cause.';
            } else {
                if (icon)  icon.textContent  = '🤝';
                if (title) title.textContent = 'Join & Give';
                if (desc)  desc.textContent  = 'Support causes that matter and track your impact.';
            }
        });
    });

    /* -- Password strength indicator -- */
    const passwordInput   = document.getElementById('password');
    const strengthBar     = document.getElementById('strengthBar');
    const strengthText    = document.getElementById('strengthText');

    if (passwordInput && strengthBar) {
        passwordInput.addEventListener('input', function () {
            const val      = this.value;
            const strength = _calculateStrength(val);

            const colors = ['#e0e0e0', '#dc3545', '#ffc107', '#17a2b8', '#28a745'];
            const labels = ['',        'Weak',    'Fair',    'Good',    'Strong'];
            const widths = ['0%',      '25%',     '50%',     '75%',     '100%'];

            strengthBar.style.width      = widths[strength];
            strengthBar.style.background = colors[strength];
            if (strengthText) {
                strengthText.textContent  = val ? labels[strength] : '';
                strengthText.style.color  = colors[strength];
            }
        });
    }

    /* -- Confirm-password match -- */
    registerForm.addEventListener('submit', function (e) {
        const pw      = document.getElementById('password');
        const confirm = document.getElementById('confirmPassword');

        if (pw && confirm && pw.value !== confirm.value) {
            e.preventDefault();
            alert('Passwords do not match. Please try again.');
            confirm.focus();
            return;
        }

        const btn = this.querySelector('.register-btn');
        if (btn) {
            btn.innerHTML = 'Creating account...';
            btn.disabled  = true;
        }
    });

    /**
     * Score password strength 0-4.
     * @param {string} pw
     * @returns {number}
     */
    function _calculateStrength(pw) {
        if (!pw) return 0;
        let score = 0;
        if (pw.length >= 8)              score++;
        if (/[A-Z]/.test(pw))           score++;
        if (/[0-9]/.test(pw))           score++;
        if (/[^A-Za-z0-9]/.test(pw))    score++;
        return score;
    }
}());
