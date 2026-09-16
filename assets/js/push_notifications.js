/**
 * FixMyCampus - Native Browser Push & Desktop Notifications
 * ZERO email dependency - uses standard HTML5 Web Notifications API
 */

(function () {
  'use strict';

  // State
  let permissionPromptShown = false;
  const SEEN_NOTIFS_KEY = 'fmc_seen_push_ids';

  function getSeenIds() {
    try {
      return JSON.parse(localStorage.getItem(SEEN_NOTIFS_KEY) || '[]');
    } catch (e) {
      return [];
    }
  }

  function addSeenId(id) {
    try {
      const ids = getSeenIds();
      if (!ids.includes(id)) {
        ids.push(id);
        // keep only recent 50
        if (ids.length > 50) ids.shift();
        localStorage.setItem(SEEN_NOTIFS_KEY, JSON.stringify(ids));
      }
    } catch (e) {}
  }

  // Check support
  const isSupported = 'Notification' in window;

  window.sendDesktopNotification = function (title, options = {}) {
    if (!isSupported) return null;

    if (Notification.permission === 'granted') {
      const notif = new Notification(title, {
        icon: options.icon || '/assets/images/favicon.png',
        badge: '/assets/images/favicon.png',
        body: options.body || '',
        tag: options.tag || 'fixmycampus-alert',
        renotify: true,
        ...options
      });

      if (options.url) {
        notif.onclick = function () {
          window.focus();
          window.location.href = options.url;
          notif.close();
        };
      }
      return notif;
    }
    return null;
  };

  window.requestPushPermission = async function (callback) {
    if (!isSupported) {
      alert('Desktop notifications are not supported by your browser.');
      return false;
    }

    try {
      const permission = await Notification.requestPermission();
      if (permission === 'granted') {
        window.sendDesktopNotification('FixMyCampus Alerts Active', {
          body: 'You will receive instant desktop notifications whenever campus issues are updated.',
          tag: 'fmc-welcome'
        });
        hidePushBanner();
        if (callback) callback(true);
        return true;
      } else {
        hidePushBanner();
        if (callback) callback(false);
        return false;
      }
    } catch (err) {
      console.warn('Push permission request error:', err);
      if (callback) callback(false);
      return false;
    }
  };

  function hidePushBanner() {
    const banner = document.getElementById('pushPermissionBanner');
    if (banner) {
      banner.style.opacity = '0';
      banner.style.transform = 'translateY(8px)';
      setTimeout(() => banner.remove(), 250);
    }
  }

  function renderPushBanner() {
    if (!isSupported || Notification.permission !== 'default') return;
    if (sessionStorage.getItem('fmc_push_banner_dismissed')) return;
    if (document.getElementById('pushPermissionBanner')) return;

    const banner = document.createElement('div');
    banner.id = 'pushPermissionBanner';
    banner.style.cssText = `
      position: fixed;
      bottom: 24px;
      right: 24px;
      z-index: 9999;
      background: var(--surface, #ffffff);
      border: 1px solid var(--border, #d4c8b8);
      color: var(--text, #2b0d0d);
      padding: 16px 18px;
      border-radius: 10px;
      box-shadow: 0 12px 30px -4px rgba(74, 14, 23, 0.12), 0 4px 12px rgba(0, 0, 0, 0.05);
      display: flex;
      align-items: flex-start;
      gap: 14px;
      max-width: 380px;
      animation: pushSlideUp 0.3s cubic-bezier(0.16, 1, 0.3, 1);
      font-family: inherit;
      transition: opacity 0.25s ease, transform 0.25s ease;
    `;

    banner.innerHTML = `
      <div style="width: 36px; height: 36px; border-radius: 8px; background: rgba(74, 14, 23, 0.08); color: var(--burg, #4A0E17); display: flex; align-items: center; justify-content: center; font-size: 16px; flex-shrink: 0; margin-top: 2px;">
        <i class="bi bi-bell-fill"></i>
      </div>
      <div style="flex: 1; min-width: 0;">
        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 4px;">
          <span style="font-weight: 600; font-size: 13.5px; color: var(--text, #2b0d0d);">Enable desktop alerts</span>
          <button id="btnDismissPushClose" aria-label="Close" style="background: none; border: none; color: var(--text-muted, #5c5148); cursor: pointer; padding: 0; font-size: 16px; line-height: 1; opacity: 0.65;">
            <i class="bi bi-x"></i>
          </button>
        </div>
        <div style="font-size: 12.5px; color: var(--text-muted, #5c5148); line-height: 1.45; margin-bottom: 12px;">
          Receive real-time browser notifications when maintenance staff update or resolve your campus tickets.
        </div>
        <div style="display: flex; gap: 8px; align-items: center;">
          <button id="btnEnablePush" style="background: var(--burg, #4A0E17); color: #ffffff; border: none; padding: 6px 14px; border-radius: 6px; font-size: 12.5px; font-weight: 600; cursor: pointer; transition: background 0.15s ease;">
            Turn on alerts
          </button>
          <button id="btnDismissPush" style="background: transparent; color: var(--text-muted, #5c5148); border: 1px solid var(--border, #d4c8b8); padding: 6px 12px; border-radius: 6px; font-size: 12.5px; font-weight: 500; cursor: pointer; transition: all 0.15s ease;">
            Not now
          </button>
        </div>
      </div>
    `;

    // Add keyframe for smooth entrance
    if (!document.getElementById('fmcPushAnimStyle')) {
      const style = document.createElement('style');
      style.id = 'fmcPushAnimStyle';
      style.textContent = `
        @keyframes pushSlideUp {
          from { opacity: 0; transform: translateY(12px) scale(0.98); }
          to { opacity: 1; transform: translateY(0) scale(1); }
        }
      `;
      document.head.appendChild(style);
    }

    document.body.appendChild(banner);

    const enableBtn = document.getElementById('btnEnablePush');
    enableBtn?.addEventListener('mouseenter', () => enableBtn.style.background = 'var(--burg2, #7B1E2B)');
    enableBtn?.addEventListener('mouseleave', () => enableBtn.style.background = 'var(--burg, #4A0E17)');
    enableBtn?.addEventListener('click', () => {
      window.requestPushPermission();
    });

    const dismissFn = () => {
      sessionStorage.setItem('fmc_push_banner_dismissed', 'true');
      hidePushBanner();
    };

    document.getElementById('btnDismissPush')?.addEventListener('click', dismissFn);
    document.getElementById('btnDismissPushClose')?.addEventListener('click', dismissFn);
  }

  // Periodic polling for notifications
  let pollInterval = null;
  async function pollLatestNotifications() {
    if (!isSupported || Notification.permission !== 'granted') return;

    try {
      // Relative path depending on directory
      const basePath = window.location.pathname.includes('/reporter/') ||
                       window.location.pathname.includes('/maintenance/') ||
                       window.location.pathname.includes('/admin/')
                       ? '../api/poll_notifications.php'
                       : 'api/poll_notifications.php';

      const res = await fetch(basePath);
      if (!res.ok) return;
      const data = await res.json();

      if (data.success && data.notifications && data.notifications.length > 0) {
        const seen = getSeenIds();
        data.notifications.forEach(n => {
          const nid = String(n.notification_id);
          // If unread and haven't displayed desktop push for this id
          if (!n.is_read && !seen.includes(nid)) {
            addSeenId(nid);
            window.sendDesktopNotification('Campus Issue Update', {
              body: n.message,
              tag: 'fmc-notif-' + nid,
              url: n.issue_id ? (window.location.origin + '/reporter/view_issue.php?id=' + n.issue_id) : undefined
            });
          }
        });
      }
    } catch (err) {
      // silent network error
    }
  }

  // Initialize on page load
  document.addEventListener('DOMContentLoaded', () => {
    // Delay prompt slightly so page loads gracefully
    setTimeout(renderPushBanner, 3000);

    // Poll every 30 seconds for live updates
    if (isSupported && Notification.permission === 'granted') {
      pollLatestNotifications();
      pollInterval = setInterval(pollLatestNotifications, 30000);
    }
  });

})();
