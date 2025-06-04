// KeNHAVATE Innovation Portal - Main JavaScript File
import './bootstrap';
import { gsap } from 'gsap';
import 'flowbite';

// Make GSAP available globally
window.gsap = gsap;

// Initialize Flowbite components
document.addEventListener('DOMContentLoaded', function () {
    // Initialize any custom animations or components here
    console.log('KeNHAVATE Innovation Portal initialized');
});

// Livewire hooks for GSAP animations
document.addEventListener('livewire:navigated', () => {
    // Reinitialize Flowbite components after Livewire navigation
    if (window.initFlowbite) {
        window.initFlowbite();
    }
});

// Common GSAP animation functions
window.animateIn = function(element, duration = 0.5) {
    gsap.fromTo(element, 
        { opacity: 0, y: 20 }, 
        { opacity: 1, y: 0, duration: duration, ease: "power2.out" }
    );
};

window.animateOut = function(element, duration = 0.3) {
    return gsap.to(element, 
        { opacity: 0, y: -20, duration: duration, ease: "power2.in" }
    );
};