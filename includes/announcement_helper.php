<?php
/**
 * FixMyCampus - Campus Broadcast Announcement Banner Helper
 */

function renderActiveAnnouncement($pdo) {
    try {
        // Fetch only the latest active and unexpired announcement for dashboard
        $stmt = $pdo->query("
            SELECT * FROM announcements 
            WHERE is_active = 1 
              AND (expires_at IS NULL OR expires_at > CURRENT_TIMESTAMP)
            ORDER BY created_at DESC 
            LIMIT 1
        ");
        $announcement = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$announcement) return '';

        // Check if there are multiple active announcements
        $totalActive = 1;
        try {
            $totalActive = (int)$pdo->query("
                SELECT COUNT(*) FROM announcements 
                WHERE is_active = 1 
                  AND (expires_at IS NULL OR expires_at > CURRENT_TIMESTAMP)
            ")->fetchColumn();
        } catch (Exception $e) {}

        $id = (int)($announcement['announcement_id'] ?? $announcement['id'] ?? 0);
        $user = currentUser();
        $uid = (int)($user['id'] ?? 0);
        $dismissKey = 'fmc_announcement_dismissed_' . $uid . '_' . $id;
        $urgency = $announcement['urgency'] ?? 'info';
        $title = $announcement['title'] ?? 'Notice';
        $message = $announcement['message'] ?? '';

        $icon = 'bi-megaphone-fill';
        if ($urgency === 'critical') $icon = 'bi-exclamation-octagon-fill';
        elseif ($urgency === 'warning') $icon = 'bi-exclamation-triangle-fill';

        $announcementsUrl = BASE_URL . 'reporter/announcements.php';

        ob_start();
        ?>
        <div class="announcement-banner <?= htmlspecialchars($urgency) ?> fade-in-up" id="announcementBanner_<?= $id ?>" style="display:flex;">
          <div class="announcement-content" style="flex:1;">
            <div style="font-size: 22px; display: flex; align-items: center; flex-shrink: 0;">
              <i class="bi <?= $icon ?>"></i>
            </div>
            <div style="flex:1;">
              <div style="font-weight: 700; font-size: 14px; margin-bottom: 2px; display:flex; align-items:center; flex-wrap:wrap; gap:6px;">
                <span class="badge" style="background:rgba(255,255,255,0.22);color:#fff;text-transform:uppercase;font-size:10px;letter-spacing:0.04em;">Notice</span>
                <span><?= htmlspecialchars($title) ?></span>
                <?php if ($totalActive > 1): ?>
                  <a href="<?= $announcementsUrl ?>" style="margin-left:auto;color:#fff;font-size:12px;font-weight:500;text-decoration:underline;opacity:0.9;" title="View all active notices">
                    View all notices (<?= $totalActive ?>) &rarr;
                  </a>
                <?php endif; ?>
              </div>
              <div style="font-size: 13px; opacity: 0.95; line-height: 1.45;">
                <?= nl2br(htmlspecialchars($message)) ?>
              </div>
            </div>
          </div>
          <button type="button" class="announcement-dismiss" id="announcementDismiss_<?= $id ?>" aria-label="Dismiss notice">
            <i class="bi bi-x"></i>
          </button>
        </div>
        <button type="button" class="announcement-restore" id="announcementRestore_<?= $id ?>" aria-label="Show notice" style="display:none;">
          <i class="bi <?= $icon ?>"></i> Show notice
        </button>
        <script>
        (function() {
          const aid = <?= $id ?>;
          const dismissKey = <?= json_encode($dismissKey) ?>;
          const el = document.getElementById('announcementBanner_' + aid);
          const button = document.getElementById('announcementDismiss_' + aid);
          const restore = document.getElementById('announcementRestore_' + aid);
          if (!el || !button || !restore) return;
          let dismissed = false;
          try {
            dismissed = !!sessionStorage.getItem(dismissKey);
          } catch (error) {
            // Keep notices visible when browser storage is unavailable.
          }
          function showBanner() {
            el.style.display = 'flex';
            el.style.opacity = '1';
            el.style.transform = 'translateY(0)';
            restore.style.display = 'none';
            try { sessionStorage.removeItem(dismissKey); } catch (error) {}
          }
          function hideBanner() {
            el.style.display = 'none';
            restore.style.display = 'inline-flex';
          }
          if (dismissed) hideBanner();
          button.addEventListener('click', function() {
            try {
              sessionStorage.setItem(dismissKey, 'true');
            } catch (error) {
              // Dismiss this view even if the preference cannot be stored.
            }
            el.style.opacity = '0';
            el.style.transform = 'translateY(-8px)';
            setTimeout(hideBanner, 250);
          });
          restore.addEventListener('click', showBanner);
        })();
        </script>
        <?php
        return ob_get_clean();
    } catch (Exception $e) {
        return '';
    }
}
