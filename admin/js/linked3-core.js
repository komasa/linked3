/**
 * Linked3 Admin JS — Core
 * Shared utilities for all dashboard views
 * Depends on: linked3-fetch.js
 */

(function() {
  'use strict';

  const L3 = window.L3 = window.L3 || {};

  // === Tab Switching ===
  L3.initTabs = function(container) {
    const tabs = container.querySelectorAll('.l3-tab');
    const panels = container.querySelectorAll('.l3-tab-panel');
    tabs.forEach(tab => {
      tab.addEventListener('click', () => {
        tabs.forEach(t => t.classList.remove('active'));
        panels.forEach(p => p.classList.remove('active'));
        tab.classList.add('active');
        const target = container.querySelector('#' + tab.dataset.tab);
        if (target) target.classList.add('active');
      });
    });
  };

  // === Modal ===
  L3.openModal = function(id) {
    const modal = document.getElementById(id);
    if (modal) modal.classList.add('active');
  };
  L3.closeModal = function(id) {
    const modal = document.getElementById(id);
    if (modal) modal.classList.remove('active');
  };

  // === Toast Notification ===
  L3.toast = function(message, type) {
    type = type || 'info';
    const toast = document.createElement('div');
    toast.className = 'l3-alert l3-alert-' + type;
    toast.style.cssText = 'position:fixed;top:32px;right:32px;z-index:100001;padding:12px 24px;border-radius:8px;box-shadow:0 4px 12px rgba(0,0,0,0.15);animation:l3-slide-in 0.3s ease;';
    toast.textContent = message;
    document.body.appendChild(toast);
    setTimeout(() => { toast.style.opacity = '0'; toast.style.transition = 'opacity 0.3s'; }, 3000);
    setTimeout(() => toast.remove(), 3300);
  };

  // === Confirm Dialog ===
  L3.confirm = function(message, onConfirm) {
    if (window.confirm(message)) onConfirm();
  };

  // === Copy to Clipboard ===
  L3.copyToClipboard = function(text) {
    navigator.clipboard.writeText(text).then(() => {
      L3.toast('Copied!', 'success');
    }).catch(() => {
      L3.toast('Copy failed', 'danger');
    });
  };

  // === Format numbers ===
  L3.formatNumber = function(n) {
    return new Intl.NumberFormat().format(n);
  };

  // === Debounce ===
  L3.debounce = function(fn, delay) {
    let timer;
    return function() {
      const args = arguments;
      clearTimeout(timer);
      timer = setTimeout(() => fn.apply(this, args), delay || 300);
    };
  };

  // === Auto-init on DOM ready ===
  document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.l3-tabs-container').forEach(L3.initTabs);
    
    // Close modal on overlay click
    document.querySelectorAll('.l3-modal-overlay').forEach(overlay => {
      overlay.addEventListener('click', (e) => {
        if (e.target === overlay) overlay.classList.remove('active');
      });
    });
  });

  // Slide-in animation
  const style = document.createElement('style');
  style.textContent = '@keyframes l3-slide-in { from { transform: translateX(100%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }';
  document.head.appendChild(style);
})();
