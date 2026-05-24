/**
 * donation-modal.js
 * Handles the "Make a Donation" modal:
 *   - Open modal and populate campaign info
 *   - Toggle field groups by donation type (cash / item / service)
 *   - Validate and submit the donation form
 *
 * Depends on: dashboard-utils.js  (escapeHtml, showToast)
 *             Bootstrap 5 (bootstrap.Modal)
 * Used by: dashboard/user.php
 */

let _currentModal = null;

/**
 * Open the donation modal pre-filled with campaign details.
 *
 * @param {number} campaignId
 * @param {string} campaignTitle
 */
function openDonationModal(campaignId, campaignTitle) {
    document.getElementById('campaign_id').value = campaignId;
    document.getElementById('donation-campaign-info').innerHTML = `
        <div class="alert alert-info">
            <strong>${escapeHtml(campaignTitle)}</strong><br>
            Your donation will directly support this campaign.
        </div>`;

    // Reset form to defaults
    document.getElementById('donationForm').reset();
    _setDonationFieldVisibility('cash');

    // Re-wire type selector
    document.getElementById('donation_type').onchange = function () {
        _setDonationFieldVisibility(this.value);
    };

    _currentModal = new bootstrap.Modal(document.getElementById('donationModal'));
    _currentModal.show();
}

/**
 * Show only the fields relevant to the selected donation type.
 *
 * @param {'cash'|'item'|'service'} type
 */
function _setDonationFieldVisibility(type) {
    document.getElementById('cash-fields').style.display    = type === 'cash'    ? 'block' : 'none';
    document.getElementById('item-fields').style.display    = type === 'item'    ? 'block' : 'none';
    document.getElementById('service-fields').style.display = type === 'service' ? 'block' : 'none';

    document.getElementById('amount').required              = (type === 'cash');
    document.getElementById('item_name').required           = (type === 'item');
    document.getElementById('service_description').required = (type === 'service');
}

/**
 * Validate and POST the donation form to the API.
 * Called from the modal's "Confirm Donation" button.
 */
function submitDonation() {
    const form     = document.getElementById('donationForm');
    const formData = new FormData(form);
    const type     = formData.get('donation_type');

    // Client-side validation
    if (type === 'cash') {
        const amount = parseFloat(formData.get('amount'));
        if (isNaN(amount) || amount < 10) {
            showToast('Please enter a valid amount (minimum ₱10)', 'error');
            return;
        }
    } else if (type === 'item' && !formData.get('item_name')) {
        showToast('Please enter an item name', 'error');
        return;
    } else if (type === 'service' && !formData.get('service_description')) {
        showToast('Please enter a service description', 'error');
        return;
    }

    const submitBtn = event.target;
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';

    fetch('../api/donate.php', { method: 'POST', body: formData })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                showToast(data.message || 'Donation submitted successfully!');
                _currentModal.hide();
                loadMyDonations(); // Refresh donations list

                if (data.redirect) {
                    setTimeout(() => { window.location.href = data.redirect; }, 1500);
                }
            } else {
                showToast(data.message || 'Donation failed', 'error');
            }
        })
        .catch(err => {
            console.error('Donation submit error:', err);
            showToast('Network error. Please try again.', 'error');
        })
        .finally(() => {
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="fas fa-check"></i> Confirm Donation';
        });
}
