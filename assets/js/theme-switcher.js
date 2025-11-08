// Permanent Dark Mode
// Always apply dark mode - no toggle option
class DarkModeEnforcer {
    constructor() {
        this.init();
    }

    init() {
        // Force dark mode immediately
        document.body.classList.add('dark-mode');
        
        // Set localStorage to dark
        localStorage.setItem('theme', 'dark');
        
        // Remove any theme toggle buttons if they exist
        this.removeToggleButtons();
    }

    removeToggleButtons() {
        const toggleButtons = document.querySelectorAll('#themeToggle, .theme-toggle');
        toggleButtons.forEach(btn => {
            if (btn) {
                btn.style.display = 'none';
            }
        });
    }
}

// Initialize permanent dark mode
document.addEventListener('DOMContentLoaded', () => {
    window.darkModeEnforcer = new DarkModeEnforcer();
});

// Also apply immediately before DOM load
document.body.classList.add('dark-mode');