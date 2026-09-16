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
        window.sendDesktopNotification('FixMyCampus Alerts Active 🔔', {
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
      setTimeout(() => banner.remove(), 300);
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
      background: rgba(15, 23, 42, 0.94);
      backdrop-filter: blur(16px);
      border: 1px solid rgba(255, 255, 255, 0.14);
      color: #f8fafc;
      padding: 16px 20px;
      border-radius: 14px;
      box-shadow: 0 20px 40px rgba(0, 0, 0, 0.4);
      display: flex;
      align-items: center;
      gap: 16px;
      max-width: 440px;
      animation: floatUp 0.4s cubic-bezier(0.16, 1, 0.3, 1);
      font-family: inherit;
    `;

    banner.innerHTML = `
      <div style="width: 42px; height: 42px; border-radius: 10px; background: linear-gradient(135deg, #6366f1, #8b5cf6); display: flex; align-items: center; justify-content: center; font-size: 20px; flex-shrink: 0; box-shadow: 0 4px 12px rgba(99, 102, 241, 0.35);">
        🔔
      </div>
      <div style="flex: 1; min-width: 0;">
        <div style="font-weight: 600; font-size: 14px; margin-bottom: 2px;">Enable Instant Notifications?</div>
        <div style="font-size: 12px; color: #94a3b8; line-height: 1.4;">Receive instant desktop alerts when maintenance technicians update or resolve your reported issues.</div>
        <div style="margin-top: 10px; display: flex; gap: 8px;">
          <button id="btnEnablePush" style="background: #4f46e5; color: #fff; border: none; padding: 6px 14px; border-radius: 8px; font-size: 12px; font-weight: 600; cursor: pointer; transition: all 0.2s;">
            Enable Notifications
          </button>
          <button id="btnDismissPush" style="background: transparent; color: #94a3b8; border: 1px solid rgba(255,255,255,0.15); padding: 6px 12px; border-radius: 8px; font-size: 12px; cursor: pointer; transition: all 0.2s;">
            Later
          </button>
        </div>
      </div>
    `;

    document.body.appendChild(banner);

    document.getElementById('btnEnablePush')?.addEventListener('click', () => {
      window.requestPushPermission();
    });

    document.getElementById('btnDismissPush')?.addEventListener('click', () => {
      sessionStorage.setItem('fmc_push_banner_dismissed', 'true');
      hidePushBanner();
    });
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
