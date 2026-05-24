<?php
$activePage = $activePage ?? '';
function userNavItem(string $href, string $page, string $label, string $svg): string {
    global $activePage;
    $cls = $activePage === $page ? ' active' : '';
    return "<a href=\"$href\" class=\"nav-item$cls\">$svg$label</a>";
}
?>
<aside class="sidebar">
  <div class="sidebar-logo">
    <div class="logo-icon">UM</div>
    <div class="logo-text"><strong>UMDC</strong><span>User Portal</span></div>
  </div>
  <nav>
    <div class="nav-section">
      <div class="nav-label">My Account</div>
      <?= userNavItem('/dashboard/user.php','dashboard','Dashboard','<svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>') ?>
      <?= userNavItem('/public/campaigns.php','campaigns','Browse Campaigns','<svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/></svg>') ?>
      <?= userNavItem('/public/transparency.php','transparency','Transparency','<svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>') ?>
    </div>
  </nav>
  <div class="sidebar-footer">
    <div class="avatar-sm"><?= $userInitials ?></div>
    <div class="user-info"><strong><?= $userName ?></strong><span>User</span></div>
    <a href="/dashboard/logout.php" class="logout-btn" title="Logout">
      <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
    </a>
  </div>
</aside>
