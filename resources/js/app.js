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
    'users': () => import('./pages/users.js'),
  };
  
  // Dynamically import only the required page script
  if (pageName && pageModules[pageName]) {
    pageModules[pageName]().catch(err => console.error(`Failed to load page script for ${pageName}:`, err));
  }
});
