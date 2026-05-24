<?php
$activePage = $activePage ?? '';
function orgNavItem(string $href, string $page, string $label, string $svg): string {
    global $activePage;
    $cls = $activePage === $page ? ' active' : '';
    return "<a href=\"$href\" class=\"nav-item$cls\">$svg$label</a>";
}
?>
<aside class="sidebar">
  <div class="sidebar-logo">
    <div class="logo-icon">UM</div>
    <div class="logo-text"><strong>UMDC</strong><span>Org Portal</span></div>
  </div>
  <nav>
    <div class="nav-section">
      <div class="nav-label">Overview</div>
      <?= orgNavItem('/organization/index.php','home','Home','<svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>') ?>
    </div>
    <div class="nav-section">
      <div class="nav-label">My Campaigns</div>
      <?= orgNavItem('/organization/campaigns.php','campaigns','Campaigns','<svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/></svg>') ?>
      <?= orgNavItem('/organization/donations.php','donations','Donations Received','<svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>') ?>
      <?= orgNavItem('/organization/analytics.php','analytics','Analytics','<svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>') ?>
    </div>
  </nav>
  <div class="sidebar-footer">
    <div class="avatar-sm"><?= $userInitials ?></div>
    <div class="user-info"><strong><?= $userName ?></strong><span>Organization</span></div>
    <a href="/dashboard/logout.php" class="logout-btn" title="Logout">
      <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
    </a>
  </div>
</aside>
