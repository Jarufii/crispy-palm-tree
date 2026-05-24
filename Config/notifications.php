<?php
// =============================================================
// UMDC – Notification Service
// =============================================================
// Usage:
//   require_once __DIR__ . '/notifications.php';
//   Notifier::send($pdo, $userId, 'donation_received', [...]);
//
// Every notify call:
//   1. Writes an in-app notification row
//   2. Queues an email (if user has email on file)
//   3. Queues an SMS   (if user has phone on file)
// =============================================================

defined('UMDC_APP') or define('UMDC_APP', true);

class Notifier {

    // ── Template registry ────────────────────────────────────
    // Each key maps to: icon, subject line, body template
    // Body placeholders use {VAR} syntax
    private static array $templates = [

        // ── Donations ──
        'donation_received' => [
            'icon'    => 'fa-heart',
            'subject' => 'Your donation was received – UMDC',
            'body'    => "Hi {name},\n\nWe've received your donation of {amount} to \"{campaign}\".\n\nDonation ID: #{donation_id}\nStatus: Pending review\n\nYou'll be notified when it's confirmed.\n\nThank you for giving,\nThe UMDC Team",
            'html'    => '<p>Hi <strong>{name}</strong>,</p><p>We\'ve received your donation of <strong>{amount}</strong> to <strong>"{campaign}"</strong>.</p><table style="margin:16px 0;background:#f8f9fc;border-radius:8px;padding:16px;width:100%;"><tr><td style="color:#64748b;font-size:.85rem;">Donation ID</td><td style="font-weight:600;">#{donation_id}</td></tr><tr><td style="color:#64748b;font-size:.85rem;">Status</td><td><span style="color:#d97706;font-weight:600;">Pending Review</span></td></tr></table><p>You\'ll receive another notification when it\'s confirmed.</p>',
        ],
        'donation_approved' => [
            'icon'    => 'fa-circle-check',
            'subject' => 'Donation approved ✓ – UMDC',
            'body'    => "Hi {name},\n\nGreat news! Your donation of {amount} to \"{campaign}\" has been approved.\n\nIt will now be processed and delivered.\n\nThank you,\nThe UMDC Team",
            'html'    => '<p>Hi <strong>{name}</strong>,</p><p>🎉 Your donation of <strong>{amount}</strong> to <strong>"{campaign}"</strong> has been <strong style="color:#059669;">approved</strong>.</p><p>It will now be processed and delivered to the beneficiaries.</p>',
        ],
        'donation_completed' => [
            'icon'    => 'fa-box-check',
            'subject' => 'Donation delivered – UMDC',
            'body'    => "Hi {name},\n\nYour donation of {amount} to \"{campaign}\" has been successfully delivered!\n\nThank you for making a difference.\n\nThe UMDC Team",
            'html'    => '<p>Hi <strong>{name}</strong>,</p><p>✅ Your donation of <strong>{amount}</strong> to <strong>"{campaign}"</strong> has been <strong style="color:#059669;">delivered</strong>!</p><p>Thank you for making a real difference.</p>',
        ],

        // ── Campaigns ──
        'campaign_approved' => [
            'icon'    => 'fa-bullhorn',
            'subject' => 'Campaign approved – UMDC',
            'body'    => "Hi {name},\n\nYour campaign \"{campaign}\" has been approved and is now live!\n\nShare it with your supporters to start receiving donations.\n\nThe UMDC Team",
            'html'    => '<p>Hi <strong>{name}</strong>,</p><p>🎉 Your campaign <strong>"{campaign}"</strong> has been <strong style="color:#059669;">approved</strong> and is now live!</p><p>Share it with your supporters to start receiving donations.</p>',
        ],
        'campaign_rejected' => [
            'icon'    => 'fa-circle-xmark',
            'subject' => 'Campaign needs revision – UMDC',
            'body'    => "Hi {name},\n\nYour campaign \"{campaign}\" requires some changes before it can be approved.\n\nReason: {reason}\n\nPlease update your campaign and resubmit.\n\nThe UMDC Team",
            'html'    => '<p>Hi <strong>{name}</strong>,</p><p>Your campaign <strong>"{campaign}"</strong> requires some changes.</p><p><strong>Reason:</strong> {reason}</p><p>Please log in to update and resubmit your campaign.</p>',
        ],
        'campaign_ending_soon' => [
            'icon'    => 'fa-clock',
            'subject' => 'Campaign ending in 3 days – UMDC',
            'body'    => "Hi {name},\n\nYour campaign \"{campaign}\" ends in 3 days.\n\nCurrent raised: {amount} of {target}\n\nShare it now to maximize donations before it closes!\n\nThe UMDC Team",
            'html'    => '<p>Hi <strong>{name}</strong>,</p><p>⏰ Your campaign <strong>"{campaign}"</strong> ends in <strong>3 days</strong>.</p><p>Current: <strong>{amount}</strong> of <strong>{target}</strong></p><p>Share it now to maximize donations!</p>',
        ],

        // ── Courier / Delivery ──
        'courier_pickup_scheduled' => [
            'icon'    => 'fa-truck',
            'subject' => 'Courier pickup scheduled – UMDC',
            'body'    => "Hi {name},\n\nA courier has been scheduled to pick up your item donation.\n\nPickup Date: {pickup_date}\nTracking Code: {tracking_code}\n\nPlease ensure your item is ready.\n\nThe UMDC Team",
            'html'    => '<p>Hi <strong>{name}</strong>,</p><p>🚚 A courier has been scheduled to pick up your item donation.</p><table style="margin:16px 0;background:#f8f9fc;border-radius:8px;padding:16px;width:100%;"><tr><td style="color:#64748b;font-size:.85rem;">Pickup Date</td><td style="font-weight:600;">{pickup_date}</td></tr><tr><td style="color:#64748b;font-size:.85rem;">Tracking Code</td><td style="font-weight:600;font-family:monospace;">{tracking_code}</td></tr></table>',
        ],
        'delivery_in_transit' => [
            'icon'    => 'fa-location-dot',
            'subject' => 'Your donation is on its way – UMDC',
            'body'    => "Hi {name},\n\nYour donation is now in transit to the organization.\n\nTracking: {tracking_code}\n\nThe UMDC Team",
            'html'    => '<p>Hi <strong>{name}</strong>,</p><p>📦 Your donation is now <strong>in transit</strong>.</p><p>Tracking: <code style="background:#f1f5fb;padding:2px 8px;border-radius:4px;">{tracking_code}</code></p>',
        ],
        'delivery_completed' => [
            'icon'    => 'fa-house-circle-check',
            'subject' => 'Delivery confirmed – UMDC',
            'body'    => "Hi {name},\n\nYour item donation has been delivered and confirmed by the organization!\n\nThank you for your generosity.\n\nThe UMDC Team",
            'html'    => '<p>Hi <strong>{name}</strong>,</p><p>✅ Your item donation has been <strong style="color:#059669;">delivered and confirmed</strong>!</p><p>Thank you for your generosity.</p>',
        ],

        // ── Verification ──
        'verification_approved' => [
            'icon'    => 'fa-shield-check',
            'subject' => 'Identity verified – UMDC',
            'body'    => "Hi {name},\n\nYour identity verification has been approved. Your account is now verified.\n\nThe UMDC Team",
            'html'    => '<p>Hi <strong>{name}</strong>,</p><p>✅ Your identity verification has been <strong style="color:#059669;">approved</strong>. Your account is now verified.</p>',
        ],
        'verification_rejected' => [
            'icon'    => 'fa-shield-xmark',
            'subject' => 'Verification needs attention – UMDC',
            'body'    => "Hi {name},\n\nYour verification submission could not be approved.\n\nReason: {reason}\n\nPlease resubmit with the correct documents.\n\nThe UMDC Team",
            'html'    => '<p>Hi <strong>{name}</strong>,</p><p>Your verification submission needs attention.</p><p><strong>Reason:</strong> {reason}</p><p>Please resubmit with the correct documents.</p>',
        ],
        'org_verified' => [
            'icon'    => 'fa-badge-check',
            'subject' => 'Organization verified – UMDC',
            'body'    => "Hi {name},\n\nCongratulations! Your organization \"{org_name}\" has been verified by UMDC.\n\nYou now have a verified badge on your public profile.\n\nThe UMDC Team",
            'html'    => '<p>Hi <strong>{name}</strong>,</p><p>🎉 Congratulations! Your organization <strong>"{org_name}"</strong> has been <strong style="color:#059669;">verified</strong>.</p><p>You now have a verified badge on your public profile.</p>',
        ],

        // ── Security ──
        'password_reset' => [
            'icon'    => 'fa-key',
            'subject' => 'Password reset request – UMDC',
            'body'    => "Hi {name},\n\nWe received a request to reset your password.\n\nClick this link to reset it (expires in 1 hour):\n{reset_link}\n\nIf you didn't request this, ignore this email.\n\nThe UMDC Team",
            'html'    => '<p>Hi <strong>{name}</strong>,</p><p>We received a request to reset your password.</p><p style="margin:20px 0;"><a href="{reset_link}" style="background:linear-gradient(135deg,#3b6ff0,#7c3aed);color:#fff;padding:12px 24px;border-radius:8px;text-decoration:none;font-weight:600;display:inline-block;">Reset My Password</a></p><p style="color:#64748b;font-size:.85rem;">This link expires in 1 hour. If you didn\'t request this, ignore this email.</p>',
        ],
        'email_verification' => [
            'icon'    => 'fa-envelope-circle-check',
            'subject' => 'Verify your email – UMDC',
            'body'    => "Hi {name},\n\nPlease verify your email address by clicking this link:\n{verify_link}\n\nThis link expires in 24 hours.\n\nThe UMDC Team",
            'html'    => '<p>Hi <strong>{name}</strong>,</p><p>Please verify your email address to activate your account.</p><p style="margin:20px 0;"><a href="{verify_link}" style="background:linear-gradient(135deg,#3b6ff0,#7c3aed);color:#fff;padding:12px 24px;border-radius:8px;text-decoration:none;font-weight:600;display:inline-block;">Verify My Email</a></p><p style="color:#64748b;font-size:.85rem;">This link expires in 24 hours.</p>',
        ],
        'login_new_device' => [
            'icon'    => 'fa-laptop-mobile',
            'subject' => 'New login detected – UMDC',
            'body'    => "Hi {name},\n\nWe detected a login to your account from a new device.\n\nTime: {time}\nLocation: {location}\nDevice: {device}\n\nIf this was you, no action needed. If not, please change your password immediately.\n\nThe UMDC Team",
            'html'    => '<p>Hi <strong>{name}</strong>,</p><p>⚠️ We detected a login from a new device.</p><table style="margin:16px 0;background:#fff8f0;border-radius:8px;padding:16px;width:100%;border:1px solid #fed7aa;"><tr><td style="color:#64748b;font-size:.85rem;">Time</td><td>{time}</td></tr><tr><td style="color:#64748b;font-size:.85rem;">Location</td><td>{location}</td></tr><tr><td style="color:#64748b;font-size:.85rem;">Device</td><td>{device}</td></tr></table><p>If this wasn\'t you, please change your password immediately.</p>',
        ],

        // ── Admin ──
        'admin_new_fraud_flag' => [
            'icon'    => 'fa-triangle-exclamation',
            'subject' => '[Admin] Fraud flag raised – UMDC',
            'body'    => "A new fraud flag has been raised.\n\nEntity: {entity_type} #{entity_id}\nReason: {reason}\nFlagged by: {flagged_by}\n\nReview it in the admin dashboard.",
            'html'    => '<p>A new fraud flag has been raised.</p><table style="margin:16px 0;background:#fff1f2;border-radius:8px;padding:16px;width:100%;border:1px solid #fecdd3;"><tr><td style="color:#64748b;font-size:.85rem;">Entity</td><td>{entity_type} #{entity_id}</td></tr><tr><td style="color:#64748b;font-size:.85rem;">Reason</td><td>{reason}</td></tr><tr><td style="color:#64748b;font-size:.85rem;">Flagged by</td><td>{flagged_by}</td></tr></table>',
        ],
        'admin_withdrawal_request' => [
            'icon'    => 'fa-money-bill-transfer',
            'subject' => '[Admin] New withdrawal request – UMDC',
            'body'    => "A new fund withdrawal has been requested.\n\nOrganization: {org_name}\nAmount: {amount}\nCampaign: {campaign}\n\nReview it in the admin dashboard.",
            'html'    => '<p>A new fund withdrawal has been requested.</p><table style="margin:16px 0;background:#f8f9fc;border-radius:8px;padding:16px;width:100%;"><tr><td style="color:#64748b;font-size:.85rem;">Organization</td><td><strong>{org_name}</strong></td></tr><tr><td style="color:#64748b;font-size:.85rem;">Amount</td><td><strong>{amount}</strong></td></tr><tr><td style="color:#64748b;font-size:.85rem;">Campaign</td><td>{campaign}</td></tr></table>',
        ],
    ];

    // ── Priority map ─────────────────────────────────────────
    private static array $priorities = [
        'donation_received'        => 'normal',
        'donation_approved'        => 'normal',
        'donation_completed'       => 'normal',
        'campaign_approved'        => 'high',
        'campaign_rejected'        => 'high',
        'campaign_ending_soon'     => 'normal',
        'courier_pickup_scheduled' => 'normal',
        'delivery_in_transit'      => 'normal',
        'delivery_completed'       => 'normal',
        'verification_approved'    => 'high',
        'verification_rejected'    => 'high',
        'org_verified'             => 'high',
        'password_reset'           => 'urgent',
        'email_verification'       => 'urgent',
        'login_new_device'         => 'high',
        'admin_new_fraud_flag'     => 'urgent',
        'admin_withdrawal_request' => 'high',
    ];

    // ── Main entry point ─────────────────────────────────────
    /**
     * Send an in-app notification + queue email + queue SMS.
     *
     * @param PDO    $pdo
     * @param int    $userId   Recipient user_id
     * @param string $type     Template key (see $templates above)
     * @param array  $vars     Placeholder replacements {KEY} => value
     * @param string $link     Optional URL for the notification bell item
     * @param bool   $emailOnly  Skip in-app (for admin email blasts)
     */
    public static function send(
        PDO    $pdo,
        int    $userId,
        string $type,
        array  $vars    = [],
        string $link    = '',
        bool   $emailOnly = false
    ): bool {
        $tpl = self::$templates[$type] ?? null;
        if (!$tpl) {
            error_log("Notifier: unknown type '$type'");
            return false;
        }

        // Resolve placeholder text for in-app message
        $message = self::interpolate($tpl['body'], $vars);
        // First line of body = short in-app summary
        $summary = explode("\n\n", $message)[1] ?? explode("\n", $message)[0];
        $summary = trim(substr($summary, 0, 200));

        $priority = self::$priorities[$type] ?? 'normal';
        $icon     = $tpl['icon'];
        $expires  = null;
        if ($type === 'email_verification' || $type === 'password_reset') {
            $expires = date('Y-m-d H:i:s', strtotime('+24 hours'));
        }

        try {
            // 1. In-app notification
            if (!$emailOnly) {
                $pdo->prepare("
                    INSERT INTO notifications
                        (user_id, message, type, link, icon, priority, is_read, expires_at, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, 0, ?, NOW())
                ")->execute([$userId, $summary, $type, $link, $icon, $priority, $expires]);
            }

            // 2. Fetch user contact details
            $user = $pdo->prepare("SELECT first_name, last_name, email, phone FROM users WHERE user_id = ?");
            $user->execute([$userId]);
            $user = $user->fetch();
            if (!$user) return true; // no contact info, in-app only

            $vars['name'] = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
            if (empty(trim($vars['name']))) $vars['name'] = 'there';

            // 3. Queue email
            if (!empty($user['email'])) {
                self::queueEmail(
                    $pdo,
                    $user['email'],
                    $vars['name'],
                    self::interpolate($tpl['subject'], $vars),
                    self::buildHtmlEmail($tpl['html'] ?? '', $vars, $tpl['subject']),
                    self::interpolate($tpl['body'], $vars),
                    $type
                );
            }

            // 4. Queue SMS (only for urgent / high priority & short message types)
            if (!empty($user['phone']) && in_array($priority, ['urgent','high'])) {
                self::queueSms($pdo, $user['phone'], self::buildSms($type, $vars));
            }

            return true;

        } catch (PDOException $e) {
            error_log("Notifier::send failed ($type): " . $e->getMessage());
            return false;
        }
    }

    // ── Bulk send to all admins ──────────────────────────────
    public static function notifyAdmins(PDO $pdo, string $type, array $vars = [], string $link = ''): void {
        $admins = $pdo->query("SELECT user_id FROM users WHERE role_id = 1 AND status = 'active'")->fetchAll();
        foreach ($admins as $admin) {
            self::send($pdo, (int)$admin['user_id'], $type, $vars, $link);
        }
    }

    // ── Mark notifications read ──────────────────────────────
    public static function markRead(PDO $pdo, int $userId, ?int $notificationId = null): void {
        if ($notificationId) {
            $pdo->prepare("UPDATE notifications SET is_read=1, read_at=NOW() WHERE notification_id=? AND user_id=?")
                ->execute([$notificationId, $userId]);
        } else {
            $pdo->prepare("UPDATE notifications SET is_read=1, read_at=NOW() WHERE user_id=? AND is_read=0")
                ->execute([$userId]);
        }
    }

    // ── Unread count ─────────────────────────────────────────
    public static function unreadCount(PDO $pdo, int $userId): int {
        return (int)$pdo->prepare("
            SELECT COUNT(*) FROM notifications
            WHERE user_id=? AND is_read=0
              AND (expires_at IS NULL OR expires_at > NOW())
        ")->execute([$userId]) ? $pdo->prepare("
            SELECT COUNT(*) FROM notifications
            WHERE user_id=? AND is_read=0
              AND (expires_at IS NULL OR expires_at > NOW())
        ")->execute([$userId]) : 0;
    }

    // Cleaner version of unreadCount
    public static function getUnreadCount(PDO $pdo, int $userId): int {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM notifications
            WHERE user_id = ? AND is_read = 0
              AND (expires_at IS NULL OR expires_at > NOW())
        ");
        $stmt->execute([$userId]);
        return (int)$stmt->fetchColumn();
    }

    // ── Private helpers ──────────────────────────────────────

    private static function interpolate(string $tpl, array $vars): string {
        foreach ($vars as $k => $v) {
            $tpl = str_replace('{' . strtoupper($k) . '}', htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'), $tpl);
            $tpl = str_replace('{' . $k . '}', htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'), $tpl);
        }
        return $tpl;
    }

    private static function buildHtmlEmail(string $bodyHtml, array $vars, string $subject): string {
        $body    = self::interpolate($bodyHtml, $vars);
        $year    = date('Y');
        $subject = htmlspecialchars($subject, ENT_QUOTES, 'UTF-8');
        return <<<HTML
<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>{$subject}</title>
</head>
<body style="margin:0;padding:0;background:#f8f9fc;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;">
  <table width="100%" cellpadding="0" cellspacing="0" style="background:#f8f9fc;padding:40px 16px;">
    <tr><td align="center">
      <table width="600" cellpadding="0" cellspacing="0" style="max-width:600px;background:#ffffff;border-radius:16px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,.08);">
        <!-- Header -->
        <tr>
          <td style="background:linear-gradient(135deg,#3b6ff0,#7c3aed);padding:28px 32px;">
            <p style="margin:0;color:#fff;font-size:1.3rem;font-weight:700;letter-spacing:-.5px;">UMDC</p>
            <p style="margin:4px 0 0;color:rgba(255,255,255,.7);font-size:.8rem;">United Movement for Donation & Charity</p>
          </td>
        </tr>
        <!-- Body -->
        <tr>
          <td style="padding:32px;color:#1e293b;font-size:.95rem;line-height:1.7;">
            {$body}
          </td>
        </tr>
        <!-- Footer -->
        <tr>
          <td style="padding:20px 32px;background:#f8f9fc;border-top:1px solid #e8ecf3;text-align:center;">
            <p style="margin:0;color:#94a3b8;font-size:.78rem;">
              © {$year} UMDC. This email was sent because of activity on your account.<br>
              If you have questions, reply to this email.
            </p>
          </td>
        </tr>
      </table>
    </td></tr>
  </table>
</body>
</html>
HTML;
    }

    private static function buildSms(string $type, array $vars): string {
        $map = [
            'donation_received'        => 'UMDC: Donation received for {campaign}. ID #{donation_id}.',
            'donation_approved'        => 'UMDC: Your donation to {campaign} was approved!',
            'donation_completed'       => 'UMDC: Your donation to {campaign} has been delivered!',
            'campaign_approved'        => 'UMDC: Your campaign "{campaign}" is now live!',
            'campaign_rejected'        => 'UMDC: Your campaign needs revision. Check your dashboard.',
            'verification_approved'    => 'UMDC: Your identity is now verified!',
            'verification_rejected'    => 'UMDC: Verification issue. Please resubmit your documents.',
            'org_verified'             => 'UMDC: Your organization is now verified!',
            'password_reset'           => 'UMDC: Password reset link sent to your email. Valid 1hr.',
            'email_verification'       => 'UMDC: Check your email to verify your account.',
            'login_new_device'         => 'UMDC: New login from {device}. Not you? Change password now.',
            'courier_pickup_scheduled' => 'UMDC: Courier pickup scheduled for {pickup_date}.',
            'delivery_completed'       => 'UMDC: Your item donation has been delivered!',
        ];
        $sms = $map[$type] ?? 'UMDC: You have a new notification. Check your dashboard.';
        return self::interpolate($sms, $vars);
    }

    private static function queueEmail(
        PDO    $pdo,
        string $toEmail,
        string $toName,
        string $subject,
        string $bodyHtml,
        string $bodyText,
        string $templateKey
    ): void {
        $pdo->prepare("
            INSERT INTO email_queue
                (to_email, to_name, subject, body_html, body_text, template_key, status, created_at)
            VALUES (?, ?, ?, ?, ?, ?, 'queued', NOW())
        ")->execute([$toEmail, $toName, $subject, $bodyHtml, $bodyText, $templateKey]);
    }

    private static function queueSms(PDO $pdo, string $phone, string $message): void {
        // Normalize PH numbers: 09xxxxxxxxx → +639xxxxxxxxx
        $phone = preg_replace('/^0/', '+63', $phone);
        $pdo->prepare("
            INSERT INTO sms_queue (to_phone, message, status, created_at)
            VALUES (?, ?, 'queued', NOW())
        ")->execute([$phone, $message]);
    }
}
