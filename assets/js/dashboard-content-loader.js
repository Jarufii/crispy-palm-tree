/**
 * dashboard-content-loader.js
 * Handles dynamic section loading for the user dashboard.
 * Sections: campaigns, my-donations, transparency, profile.
 *
 * Depends on: dashboard-utils.js  (escapeHtml, formatCurrency, showToast)
 * Used by: dashboard/user.php
 */

/* ── Section Router ──────────────────────────────────── */

/**
 * Load a named section into #dynamic-content and mark
 * the clicked sidebar link as active.
 *
 * @param {string} page - 'campaigns' | 'my-donations' | 'transparency' | 'profile'
 */
function loadContent(page) {
    const contentDiv = document.getElementById('dynamic-content');
    contentDiv.innerHTML = `
        <div class="text-center loading-spinner">
            <div class="spinner-border text-primary"></div>
            <p>Loading...</p>
        </div>`;

    switch (page) {
        case 'campaigns':    loadCampaigns();    break;
        case 'my-donations': loadMyDonations();  break;
        case 'transparency': loadTransparency(); break;
        case 'profile':      loadProfile();      break;
    }

    document.querySelectorAll('.sidebar .nav-link').forEach(link => {
        link.classList.remove('active');
    });

    // `event` is available because this is called from an onclick attribute
    if (event && event.currentTarget) {
        event.currentTarget.classList.add('active');
    }
}

/* ── Campaigns ───────────────────────────────────────── */

function loadCampaigns() {
    fetch('../api/campaigns.php')
        .then(res => {
            if (!res.ok) throw new Error('Network response was not ok');
            return res.json();
        })
        .then(data => {
            if (!data.success) throw new Error(data.message);

            let html = '<h5 class="mb-4"><i class="fas fa-bullhorn"></i> Active Campaigns</h5>'
                     + '<div class="row" id="campaigns-grid">';

            if (data.campaigns.length === 0) {
                html += '<div class="col-12"><div class="alert alert-info">No active campaigns at the moment.</div></div>';
            } else {
                data.campaigns.forEach(c => {
                    const progress = (c.current_amount / c.target_amount) * 100;
                    html += `
                        <div class="col-md-4 mb-4">
                            <div class="card campaign-card h-100">
                                <img src="${escapeHtml(c.image || 'https://via.placeholder.com/400x200?text=Campaign')}"
                                     class="card-img-top" alt="${escapeHtml(c.title)}"
                                     onerror="this.src='https://via.placeholder.com/400x200?text=No+Image'">
                                <div class="card-body">
                                    <h6 class="card-title">${escapeHtml(c.title)}</h6>
                                    <p class="card-text text-muted small">
                                        ${escapeHtml(c.description.substring(0, 100))}${c.description.length > 100 ? '...' : ''}
                                    </p>
                                    <div class="mb-2">
                                        <div class="progress">
                                            <div class="progress-bar" style="width: ${Math.min(progress, 100)}%"></div>
                                        </div>
                                        <small class="text-muted">
                                            ${formatCurrency(c.current_amount)} raised of ${formatCurrency(c.target_amount)}
                                        </small>
                                    </div>
                                    <button class="btn btn-donate w-100 mt-2"
                                            onclick="openDonationModal(${c.campaign_id}, '${escapeHtml(c.title)}')">
                                        <i class="fas fa-donate"></i> Donate Now
                                    </button>
                                </div>
                            </div>
                        </div>`;
                });
            }

            html += '</div>';
            document.getElementById('dynamic-content').innerHTML = html;
        })
        .catch(err => {
            console.error('Error loading campaigns:', err);
            document.getElementById('dynamic-content').innerHTML = `
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle"></i>
                    Failed to load campaigns. Please try again later.
                </div>`;
            showToast('Failed to load campaigns', 'error');
        });
}

/* ── My Donations ────────────────────────────────────── */

function loadMyDonations() {
    fetch('../api/my_donations.php')
        .then(res => res.json())
        .then(data => {
            if (!data.success) throw new Error(data.message);

            let html = '<h5 class="mb-4"><i class="fas fa-donate"></i> My Donation History</h5>';

            if (data.donations.length === 0) {
                html += '<div class="alert alert-info">You haven\'t made any donations yet.</div>';
            } else {
                html += `
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th>Date</th>
                                    <th>Campaign</th>
                                    <th>Type</th>
                                    <th>Amount / Item</th>
                                    <th>Status</th>
                                    <th>Receipt</th>
                                </tr>
                            </thead>
                            <tbody>`;

                data.donations.forEach(d => {
                    const statusClass = d.status === 'completed' ? 'success'
                                      : d.status === 'pending'   ? 'warning'
                                      :                            'danger';

                    const amountCell = d.donation_type === 'cash'
                        ? formatCurrency(d.amount)
                        : (d.item_name || d.service_description || 'N/A');

                    const receiptCell = d.receipt_number
                        ? `<button class="btn btn-sm btn-outline-primary" onclick="viewReceipt(${d.donation_id})">
                               <i class="fas fa-receipt"></i> View
                           </button>`
                        : '<span class="text-muted">Processing</span>';

                    html += `
                        <tr>
                            <td>${new Date(d.created_at).toLocaleDateString()}</td>
                            <td>${escapeHtml(d.campaign_title)}</td>
                            <td><span class="badge bg-info">${d.donation_type}</span></td>
                            <td>${amountCell}</td>
                            <td><span class="badge bg-${statusClass}">${d.status}</span></td>
                            <td>${receiptCell}</td>
                        </tr>`;
                });

                html += '</tbody></table></div>';
            }

            document.getElementById('dynamic-content').innerHTML = html;
        })
        .catch(err => {
            console.error('Error loading donations:', err);
            showToast('Failed to load donation history', 'error');
        });
}

/* ── Transparency Ledger ─────────────────────────────── */

function loadTransparency() {
    fetch('../api/transparency.php')
        .then(res => res.json())
        .then(data => {
            if (!data.success) throw new Error(data.message);

            let html = `
                <h5 class="mb-4"><i class="fas fa-chart-line"></i> Platform Transparency</h5>
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card text-center">
                            <div class="card-body">
                                <h3>${formatCurrency(data.total_donations)}</h3>
                                <small class="text-muted">Total Donations</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-center">
                            <div class="card-body">
                                <h3>${data.total_donors}</h3>
                                <small class="text-muted">Total Donors</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-center">
                            <div class="card-body">
                                <h3>${data.active_campaigns}</h3>
                                <small class="text-muted">Active Campaigns</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-center">
                            <div class="card-body">
                                <h3>${data.successful_campaigns}</h3>
                                <small class="text-muted">Completed Campaigns</small>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header"><h6>Public Ledger – Recent Transactions</h6></div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Transaction Hash</th>
                                        <th>Campaign</th>
                                        <th>Amount</th>
                                        <th>Date</th>
                                        <th>Verify</th>
                                    </tr>
                                </thead>
                                <tbody>`;

            data.transactions.forEach(t => {
                const shortHash = t.public_hash.substring(0, 12) + '...';
                html += `
                    <tr>
                        <td><code>${shortHash}</code></td>
                        <td>${escapeHtml(t.campaign_title)}</td>
                        <td>${formatCurrency(t.amount)}</td>
                        <td>${new Date(t.created_at).toLocaleDateString()}</td>
                        <td>
                            <button class="btn btn-sm btn-link"
                                    onclick="verifyTransaction('${t.public_hash}')">
                                <i class="fas fa-check-circle"></i> Verify
                            </button>
                        </td>
                    </tr>`;
            });

            html += '</tbody></table></div></div></div>';
            document.getElementById('dynamic-content').innerHTML = html;
        })
        .catch(err => {
            console.error('Error loading transparency data:', err);
            showToast('Failed to load transparency data', 'error');
        });
}

/* ── Profile ─────────────────────────────────────────── */

function loadProfile() {
    fetch('../api/profile.php')
        .then(res => res.json())
        .then(data => {
            if (!data.success) throw new Error(data.message);

            const u = data.user;
            const s = data.stats;

            document.getElementById('dynamic-content').innerHTML = `
                <h5 class="mb-4"><i class="fas fa-user"></i> My Profile</h5>
                <div class="row">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-body">
                                <form id="profileForm">
                                    <input type="hidden" name="csrf_token"
                                           value="${escapeHtml(window.CSRF_TOKEN || '')}">
                                    <div class="mb-3">
                                        <label class="form-label">First Name</label>
                                        <input type="text" class="form-control" name="first_name"
                                               value="${escapeHtml(u.first_name)}" required>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Last Name</label>
                                        <input type="text" class="form-control" name="last_name"
                                               value="${escapeHtml(u.last_name)}" required>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Email</label>
                                        <input type="email" class="form-control" name="email"
                                               value="${escapeHtml(u.email)}" required>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Member Since</label>
                                        <input type="text" class="form-control"
                                               value="${new Date(u.created_at).toLocaleDateString()}" disabled>
                                    </div>
                                    <button type="submit" class="btn btn-primary">Update Profile</button>
                                </form>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header"><h6>Account Statistics</h6></div>
                            <div class="card-body">
                                <p><strong>Total Donated:</strong> ${formatCurrency(s.total_donated)}</p>
                                <p><strong>Total Donations:</strong> ${s.donation_count}</p>
                                <p><strong>Campaigns Supported:</strong> ${s.campaigns_supported}</p>
                                <hr>
                                <h6>Recent Activity</h6>
                                ${s.last_donation
                                    ? `<p>Last donation: ${new Date(s.last_donation).toLocaleDateString()}</p>`
                                    : '<p>No donations yet</p>'}
                            </div>
                        </div>
                    </div>
                </div>`;

            document.getElementById('profileForm').addEventListener('submit', function (e) {
                e.preventDefault();
                fetch('../api/update_profile.php', { method: 'POST', body: new FormData(this) })
                    .then(res => res.json())
                    .then(data => {
                        if (data.success) {
                            showToast('Profile updated successfully!');
                            loadProfile();
                        } else {
                            showToast(data.message || 'Update failed', 'error');
                        }
                    })
                    .catch(() => showToast('Network error', 'error'));
            });
        })
        .catch(err => {
            console.error('Error loading profile:', err);
            showToast('Failed to load profile', 'error');
        });
}

/* ── Utility Actions ─────────────────────────────────── */

function viewReceipt(donationId) {
    window.open(`../receipts/view.php?id=${donationId}`, '_blank', 'width=600,height=400');
}

function verifyTransaction(hash) {
    window.open(`../public/verify.php?hash=${hash}`, '_blank', 'width=600,height=400');
}
