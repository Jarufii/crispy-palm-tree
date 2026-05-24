/**
 * payment-status-poller.js
 * Polls the payment-status API while a donation is in "pending" state,
 * then reloads the page once the payment is confirmed.
 *
 * Used by: donations/success.php  (pending payment state only)
 *
 * The PHP page must set window.DONATION_ID before loading this script:
 *   <script>window.DONATION_ID = <?php echo $donation_id; ?>;</script>
 */

(function () {
    'use strict';

    const MAX_AUTO_CHECKS = 12;   // 12 × 5 s = 60 s of auto-polling
    const POLL_INTERVAL   = 5000; // milliseconds

    let autoCheckCount = 0;
    let autoInterval   = null;

    /**
     * Manually check payment status (also called by the auto-interval).
     */
    function checkPaymentStatus() {
        const btn = document.getElementById('checkStatusBtn');
        if (btn) {
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Checking...';
        }

        fetch(`/api/check_payment_status.php?id=${window.DONATION_ID}`)
            .then(res => res.json())
            .then(data => {
                if (data.status === 'completed' || data.status === 'paid') {
                    location.reload();
                    return;
                }

                if (btn) {
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fas fa-sync-alt"></i> Check Again';
                }
            })
            .catch(() => {
                if (btn) {
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fas fa-sync-alt"></i> Check Status';
                }
            });
    }

    /** Start auto-polling on page load. */
    function startAutoPolling() {
        autoInterval = setInterval(() => {
            if (autoCheckCount < MAX_AUTO_CHECKS) {
                checkPaymentStatus();
                autoCheckCount++;
            } else {
                clearInterval(autoInterval);
            }
        }, POLL_INTERVAL);
    }

    /** Re-check when the user returns to this tab (e.g. after paying). */
    window.addEventListener('focus', checkPaymentStatus);

    // Expose manual trigger for the "Check Status" button
    window.checkPaymentStatus = checkPaymentStatus;

    startAutoPolling();
}());
