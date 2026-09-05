import $ from 'jquery';
window.$ = $;
window.jQuery = $;

import * as bootstrap from 'bootstrap';
window.bootstrap = bootstrap;

import Swal from 'sweetalert2';
window.Swal = Swal;

import 'admin-lte/dist/js/adminlte.js'

// Import utilities and make them global
import { clearErrors, showFormErrors, toast, confirmAction, handleSessionToast, initThemeSelector, showModal, hideModal } from './utils.js';
window.clearErrors = clearErrors;
window.showFormErrors = showFormErrors;
window.toast = toast;
window.confirmAction = confirmAction;
window.showModal = showModal;
window.hideModal = hideModal;

// Initialize on DOM ready
$(document).ready(function() {
  handleSessionToast();
  initThemeSelector();

  // Load the global search on every authenticated (dashboard) page
  import('./pages/search.js').catch(err => console.error('Failed to load search:', err));

  // Get the current page from data attribute
  const pageName = document.body.dataset.page;
  
  // Map route names to page modules
  const pageModules = {
    'dashboard': () => import('./pages/dashboard.js'),
    'favorites.index': () => import('./pages/favorites.js'),
    'login': () => import('./pages/auth.js'),
    'register': () => import('./pages/register.js'),
    'swap': () => import('./pages/swap.js'),
    'schedule': () => import('./pages/schedule.js'),
    'profile': () => Promise.all([import('./pages/profile/edit.js'), import('./pages/schedule.js')]),
    'users.profile': () => import('./pages/profile/show.js'),
    'users': () => import('./pages/users.js'),
    'community': () => import('./pages/community.js'),
    'community.show': () => import('./pages/community-show.js'),
    'messages': () => import('./pages/messages.js'),
  };
  
  // Dynamically import only the required page script
  if (pageName && pageModules[pageName]) {
    pageModules[pageName]().catch(err => console.error(`Failed to load page script for ${pageName}:`, err));
  }
  
  window.refreshSidebarMessagesBadge = function () {
    $.ajax({
      url: '/messages/unread-count',
      method: 'GET',
      success: function (response) {
        const $badge = $('#sidebarMessagesBadge');
        if (response.count > 0) {
          if ($badge.length) {
            $badge.text(response.count);
          } else {
            $('.sidebar-menu .nav-link p:contains("Messages")').append(
              `<span class="badge bg-danger rounded-pill" id="sidebarMessagesBadge">${response.count}</span>`
            );
          }
        } else {
          $badge.remove();
        }
      }
    });
  };

  if ($('#appSidebar').length) {
    window.refreshSidebarMessagesBadge();
    setInterval(window.refreshSidebarMessagesBadge, 6000);
  }
});
