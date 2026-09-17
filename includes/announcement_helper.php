<?php
/**
 * FixMyCampus - Campus Broadcast Announcement Banner Helper
 */

function renderActiveAnnouncement($pdo) {
    try {
        $stmt = $pdo->query("SELECT * FROM announcements WHERE is_active = 1 ORDER BY created_at DESC LIMIT 1");
        $announcement = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$announcement) return '';

        $id = $announcement['announcement_id'] ?? $announcement['id'] ?? 0;
        $urgency = $announcement['urgency'] ?? 'info';
        $title = $announcement['title'] ?? 'Notice';
        $message = $announcement['message'] ?? '';

        $icon = 'bi-megaphone-fill';
        if ($urgency === 'critical') $icon = 'bi-exclamation-octagon-fill';
        elseif ($urgency === 'warning') $icon = 'bi-exclamation-triangle-fill';

        ob_start();
        ?>
        <div class="announcement-banner <?= htmlspecialchars($urgency) ?> fade-in-up" id="announcementBanner_<?= $id ?>" style="display:none;">
          <div class="announcement-content">
            <div style="font-size: 22px; display: flex; align-items: center; flex-shrink: 0;">
              <i class="bi <?= $icon ?>"></i>
            </div>
            <div>
              <div style="font-weight: 700; font-size: 14px; margin-bottom: 2px;">
                <span class="badge" style="background:rgba(255,255,255,0.22);color:#fff;margin-right:6px;text-transform:uppercase;font-size:10px;letter-spacing:0.04em;">Campus Notice</span>
                <?= htmlspecialchars($title) ?>
              </div>
              <div style="font-size: 13px; opacity: 0.95; line-height: 1.45;">
                <?= nl2br(htmlspecialchars($message)) ?>
              </div>
            </div>
          </div>
          <button type="button" class="announcement-dismiss" onclick="dismissAnnouncement(<?= $id ?>)" aria-label="Dismiss notice">
            <i class="bi bi-x"></i>
          </button>
        </div>
        <script>
        (function() {
          const aid = <?= $id ?>;
          if (!sessionStorage.getItem('fmc_announcement_dismissed_' + aid)) {
            const el = document.getElementById('announcementBanner_' + aid);
            if (el) el.style.display = 'flex';
          }
        })();
        function dismissAnnouncement(aid) {
          sessionStorage.setItem('fmc_announcement_dismissed_' + aid, 'true');
          const el = document.getElementById('announcementBanner_' + aid);
          if (el) {
            el.style.opacity = '0';
            el.style.transform = 'translateY(-8px)';
            setTimeout(() => el.remove(), 250);
          }
        }
        </script>
        <?php
        return ob_get_clean();
    } catch (Exception $e) {
        return '';
    }
}
