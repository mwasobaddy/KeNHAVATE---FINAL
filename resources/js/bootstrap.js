// KeNHAVATE Innovation Portal - Bootstrap
import axios from 'axios';

window.axios = axios;
window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

// Add CSRF token to all requests
const token = document.head.querySelector('meta[name="csrf-token"]');
if (token) {
    window.axios.defaults.headers.common['X-CSRF-TOKEN'] = token.content;
    
    // Also set for Livewire - ensure the token is available for Livewire requests
    window.axios.defaults.headers.common['X-CSRF-TOKEN'] = token.content;
    
    // Set the token in a global variable for Livewire to access
    window.csrfToken = token.content;
} else {
    console.error('CSRF token not found: https://laravel.com/docs/csrf#csrf-x-csrf-token');
}

// Ensure CSRF token is available when Livewire needs it
document.addEventListener('DOMContentLoaded', function() {
    // Make sure the CSRF token is set for any late-loading components
    const currentToken = document.head.querySelector('meta[name="csrf-token"]');
    if (currentToken && window.Livewire) {
        // Refresh Livewire's CSRF token if needed
        window.Livewire.directive('csrf', () => currentToken.content);
    }
});
