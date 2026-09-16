/**
 * FixMyCampus - Interactive UI Animations Engine
 * Inspired by Unicorn Studio, 21st.dev, and shadcn/ui
 */

(function () {
  'use strict';

  // Check user preference for reduced motion
  const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  /* ==========================================================================
     1. Unicorn Studio Style: Interactive Fluid Ambient Mesh Canvas
     ========================================================================== */
  function initAmbientCanvas() {
    const canvas = document.getElementById('ambientCanvas');
    if (!canvas || prefersReducedMotion) return;

    const ctx = canvas.getContext('2d');
    let width, height;
    let animationFrameId;
    let isVisible = true;

    // Palette: Campus Burgundy, Crimson, Amber & Soft Peach
    const orbColors = [
      { r: 74,  g: 14,  b: 23,  a: 0.40 }, // Deep Burgundy
      { r: 123, g: 30,  b: 43,  a: 0.35 }, // Rich Crimson
      { r: 217, g: 119, b: 6,   a: 0.28 }, // Warm Amber
      { r: 251, g: 196, b: 158, a: 0.30 }, // Soft Peach
      { r: 94,  g: 17,  b: 32,  a: 0.32 }  // Burgundy Shade
    ];

    let orbs = [];
    let mouse = { x: 0, y: 0, targetX: 0, targetY: 0, active: false };

    function resize() {
      width = canvas.width = window.innerWidth;
      height = canvas.height = window.innerHeight;
      createOrbs();
    }

    function createOrbs() {
      orbs = [];
      const count = Math.max(4, Math.min(6, Math.floor(width / 300)));
      for (let i = 0; i < count; i++) {
        const color = orbColors[i % orbColors.length];
        orbs.push({
          x: Math.random() * width,
          y: Math.random() * height,
          baseRadius: Math.min(width, height) * (0.28 + Math.random() * 0.18),
          radius: 0,
          vx: (Math.random() - 0.5) * 0.45,
          vy: (Math.random() - 0.5) * 0.45,
          color: color,
          phase: Math.random() * Math.PI * 2,
          phaseSpeed: 0.003 + Math.random() * 0.004
        });
      }
    }

    window.addEventListener('resize', resize, { passive: true });
    window.addEventListener('pointermove', (e) => {
      mouse.targetX = e.clientX;
      mouse.targetY = e.clientY;
      mouse.active = true;
    }, { passive: true });

    document.addEventListener('visibilitychange', () => {
      isVisible = !document.hidden;
      if (isVisible) {
        animate();
      } else {
        cancelAnimationFrame(animationFrameId);
      }
    });

    resize();

    function animate() {
      if (!isVisible) return;

      ctx.clearRect(0, 0, width, height);

      // Smooth mouse interpolation
      mouse.x += (mouse.targetX - mouse.x) * 0.05;
      mouse.y += (mouse.targetY - mouse.y) * 0.05;

      orbs.forEach((orb, i) => {
        orb.phase += orb.phaseSpeed;
        orb.x += orb.vx + Math.sin(orb.phase) * 0.3;
        orb.y += orb.vy + Math.cos(orb.phase) * 0.3;

        // Bounce gently at screen edges
        if (orb.x < -orb.baseRadius * 0.5) orb.vx = Math.abs(orb.vx);
        if (orb.x > width + orb.baseRadius * 0.5) orb.vx = -Math.abs(orb.vx);
        if (orb.y < -orb.baseRadius * 0.5) orb.vy = Math.abs(orb.vy);
        if (orb.y > height + orb.baseRadius * 0.5) orb.vy = -Math.abs(orb.vy);

        // Gentle interactive pointer influence
        if (mouse.active) {
          const dx = mouse.x - orb.x;
          const dy = mouse.y - orb.y;
          const dist = Math.sqrt(dx * dx + dy * dy);
          if (dist < 400 && dist > 1) {
            const force = (400 - dist) / 400 * 0.35;
            orb.x += (dx / dist) * force;
            orb.y += (dy / dist) * force;
          }
        }

        orb.radius = orb.baseRadius * (1 + Math.sin(orb.phase) * 0.12);

        // Create fluid glowing radial gradient
        const grad = ctx.createRadialGradient(
          orb.x, orb.y, 0,
          orb.x, orb.y, orb.radius
        );
        const { r, g, b, a } = orb.color;
        grad.addColorStop(0, `rgba(${r}, ${g}, ${b}, ${a})`);
        grad.addColorStop(0.5, `rgba(${r}, ${g}, ${b}, ${a * 0.45})`);
        grad.addColorStop(1, `rgba(${r}, ${g}, ${b}, 0)`);

        ctx.beginPath();
        ctx.arc(orb.x, orb.y, orb.radius, 0, Math.PI * 2);
        ctx.fillStyle = grad;
        ctx.fill();
      });

      animationFrameId = requestAnimationFrame(animate);
    }

    animate();
  }

  /* ==========================================================================
     2. Unicorn Studio / 21st.dev Style: Spotlight Card Pointer Tracker
     ========================================================================== */
  function initSpotlightCards() {
    if (prefersReducedMotion) return;

    const cards = document.querySelectorAll('.spotlight-card, .stat-card, .panel, .auth-card');
    cards.forEach((card) => {
      card.classList.add('spotlight-card');
      card.addEventListener('pointermove', (e) => {
        const rect = card.getBoundingClientRect();
        const x = e.clientX - rect.left;
        const y = e.clientY - rect.top;
        card.style.setProperty('--mouse-x', `${x}px`);
        card.style.setProperty('--mouse-y', `${y}px`);
      }, { passive: true });
    });
  }

  /* ==========================================================================
     3. 21st.dev Style: Animated Number CountUp
     ========================================================================== */
  function initCountUp() {
    if (prefersReducedMotion) return;

    const countElements = document.querySelectorAll('.stat-value, [data-countup]');
    countElements.forEach((el) => {
      // Extract target numerical value
      const rawText = el.textContent.trim();
      const match = rawText.match(/^([^\d]*)(\d+)([^\d]*)$/);
      if (!match) return;

      const prefix = match[1] || '';
      const targetNumber = parseInt(match[2], 10);
      const suffix = match[3] || '';

      if (isNaN(targetNumber) || targetNumber === 0) return;

      el.textContent = `${prefix}0${suffix}`;
      const duration = Math.min(1600, Math.max(800, targetNumber * 40));
      const startTime = performance.now();

      function updateCounter(currentTime) {
        const elapsed = currentTime - startTime;
        const progress = Math.min(1, elapsed / duration);
        // Cubic ease out
        const ease = 1 - Math.pow(1 - progress, 3);
        const currentVal = Math.floor(ease * targetNumber);
        el.textContent = `${prefix}${currentVal}${suffix}`;

        if (progress < 1) {
          requestAnimationFrame(updateCounter);
        } else {
          el.textContent = `${prefix}${targetNumber}${suffix}`;
        }
      }

      requestAnimationFrame(updateCounter);
    });
  }

  /* ==========================================================================
     4. shadcn/ui Style: Floating Toast Notifications
     ========================================================================== */
  function getOrCreateToastContainer() {
    let container = document.querySelector('.toast-container');
    if (!container) {
      container = document.createElement('div');
      container.className = 'toast-container';
      container.setAttribute('aria-live', 'polite');
      container.setAttribute('aria-atomic', 'true');
      document.body.appendChild(container);
    }
    return container;
  }

  window.showToast = function (message, type = 'info', duration = 4500) {
    const container = getOrCreateToastContainer();
    const toast = document.createElement('div');
    toast.className = `toast-item toast-${type}`;

    const iconMap = {
      success: 'bi-check-circle-fill',
      danger:  'bi-exclamation-octagon-fill',
      warning: 'bi-exclamation-triangle-fill',
      info:    'bi-info-circle-fill'
    };
    const iconClass = iconMap[type] || 'bi-bell-fill';

    toast.innerHTML = `
      <i class="bi ${iconClass} toast-icon"></i>
      <div class="toast-content">${message}</div>
      <button type="button" class="toast-close" aria-label="Close notification"><i class="bi bi-x"></i></button>
      <div class="toast-progress" style="animation-duration: ${duration}ms;"></div>
    `;

    container.appendChild(toast);

    function dismiss() {
      toast.classList.add('hiding');
      setTimeout(() => {
        if (toast.parentElement) toast.remove();
      }, 250);
    }

    const timer = setTimeout(dismiss, duration);

    toast.querySelector('.toast-close').addEventListener('click', () => {
      clearTimeout(timer);
      dismiss();
    });
  };

  // Convert static page alert-banners into toast notifications or elevate them
  function initAutoToasts() {
    const alerts = document.querySelectorAll('.alert-banner:not([data-no-toast])');
    alerts.forEach((alert) => {
      const text = alert.innerHTML.trim();
      let type = 'info';
      if (alert.classList.contains('alert-success')) type = 'success';
      else if (alert.classList.contains('alert-danger')) type = 'danger';
      else if (alert.classList.contains('alert-warning')) type = 'warning';

      if (text && !alert.classList.contains('no-toast')) {
        // Show non-blocking toast
        window.showToast(text, type, 5000);
      }
    });
  }

  /* ==========================================================================
     5. 21st.dev Style: Button Ripple & Tactile Micro-Interactions
     ========================================================================== */
  function initRippleEffect() {
    if (prefersReducedMotion) return;

    document.addEventListener('click', (e) => {
      const btn = e.target.closest('.btn-primary, .btn-secondary, .auth-submit, .demo-chip');
      if (!btn) return;

      const rect = btn.getBoundingClientRect();
      const circle = document.createElement('span');
      const diameter = Math.max(rect.width, rect.height);
      const radius = diameter / 2;

      circle.style.width = circle.style.height = `${diameter}px`;
      circle.style.left = `${e.clientX - rect.left - radius}px`;
      circle.style.top = `${e.clientY - rect.top - radius}px`;
      circle.style.position = 'absolute';
      circle.style.borderRadius = '50%';
      circle.style.pointerEvents = 'none';
      circle.style.backgroundColor = 'rgba(255, 255, 255, 0.28)';
      circle.style.transform = 'scale(0)';
      circle.style.animation = 'ripple 0.55s ease-out forwards';

      btn.style.position = 'relative';
      btn.style.overflow = 'hidden';

      btn.appendChild(circle);
      setTimeout(() => circle.remove(), 600);
    }, { passive: true });
  }

  // Inject dynamic keyframe for ripple
  const style = document.createElement('style');
  style.textContent = `
    @keyframes ripple {
      to {
        transform: scale(3.5);
        opacity: 0;
      }
    }
  `;
  document.head.appendChild(style);

  /* ==========================================================================
     Initialization on DOMContentLoaded
     ========================================================================== */
  function initAll() {
    initAmbientCanvas();
    initSpotlightCards();
    initCountUp();
    initAutoToasts();
    initRippleEffect();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initAll);
  } else {
    initAll();
  }

})();
