// Main JavaScript Functions

// Copy to Clipboard - handles both text string and element ID
function copyToClipboard(textOrElementId) {
    let text = textOrElementId;
    
    // Check if it's an element ID
    const element = document.getElementById(textOrElementId);
    if (element) {
        text = element.value || element.textContent || element.innerText;
    }
    
    // Modern clipboard API
    if (navigator.clipboard && window.isSecureContext) {
        navigator.clipboard.writeText(text).then(() => {
            showToast('Copied to clipboard!', 'success');
        }).catch(err => {
            console.error('Clipboard error:', err);
            fallbackCopyToClipboard(text);
        });
    } else {
        fallbackCopyToClipboard(text);
    }
}

// Fallback copy method for non-HTTPS contexts
function fallbackCopyToClipboard(text) {
    const textarea = document.createElement('textarea');
    textarea.value = text;
    textarea.style.position = 'fixed';
    textarea.style.top = '0';
    textarea.style.left = '0';
    textarea.style.width = '2em';
    textarea.style.height = '2em';
    textarea.style.padding = '0';
    textarea.style.border = 'none';
    textarea.style.outline = 'none';
    textarea.style.boxShadow = 'none';
    textarea.style.background = 'transparent';
    textarea.style.opacity = '0';
    document.body.appendChild(textarea);
    textarea.focus();
    textarea.select();
    
    try {
        const successful = document.execCommand('copy');
        if (successful) {
            showToast('Copied to clipboard!', 'success');
        } else {
            showToast('Failed to copy. Please copy manually.', 'warning');
        }
    } catch (err) {
        console.error('Fallback copy error:', err);
        showToast('Failed to copy. Please copy manually.', 'danger');
    }
    
    document.body.removeChild(textarea);
}

// Toast Notification
function showToast(message, type = 'info') {
    const toastContainer = document.getElementById('toastContainer') || createToastContainer();
    
    const toast = document.createElement('div');
    toast.className = `toast align-items-center text-white bg-${type} border-0 show`;
    toast.setAttribute('role', 'alert');
    toast.innerHTML = `
        <div class="d-flex">
            <div class="toast-body">${message}</div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
    `;
    
    toastContainer.appendChild(toast);
    
    setTimeout(() => {
        toast.classList.remove('show');
        setTimeout(() => toast.remove(), 300);
    }, 3000);
}

function createToastContainer() {
    const container = document.createElement('div');
    container.id = 'toastContainer';
    container.className = 'toast-container position-fixed top-0 end-0 p-3';
    container.style.zIndex = '9999';
    document.body.appendChild(container);
    return container;
}

// Form Validation
function validateForm(formId) {
    const form = document.getElementById(formId);
    if (!form) return false;
    
    const inputs = form.querySelectorAll('[required]');
    let isValid = true;
    
    inputs.forEach(input => {
        if (!input.value.trim()) {
            isValid = false;
            input.classList.add('is-invalid');
        } else {
            input.classList.remove('is-invalid');
        }
    });
    
    return isValid;
}

// AJAX Request Helper
async function apiRequest(url, method = 'GET', data = null) {
    const options = {
        method: method,
        headers: {
            'Content-Type': 'application/json',
        }
    };
    
    // Add API key from localStorage if exists
    const apiKey = localStorage.getItem('api_key');
    if (apiKey) {
        options.headers['X-API-Key'] = apiKey;
    }
    
    // Add JWT token if exists
    const token = localStorage.getItem('token');
    if (token) {
        options.headers['Authorization'] = `Bearer ${token}`;
    }
    
    if (data && method !== 'GET') {
        options.body = JSON.stringify(data);
    }
    
    try {
        const response = await fetch(url, options);
        const result = await response.json();
        
        if (!response.ok) {
            throw new Error(result.message || 'Request failed');
        }
        
        return result;
    } catch (error) {
        console.error('API Error:', error);
        showToast(error.message, 'danger');
        throw error;
    }
}

// Format Currency
function formatCurrency(amount, currency = 'USD') {
    const symbols = {
        'USD': '$',
        'EUR': '€',
        'GBP': '£',
        'INR': '₹',
        'IDR': 'Rp',
        'BRL': 'R$',
        'AUD': 'A$',
        'CAD': 'C$'
    };
    
    const symbol = symbols[currency] || '$';
    return `${symbol}${parseFloat(amount).toFixed(2)}`;
}

// Format Number
function formatNumber(num) {
    return new Intl.NumberFormat().format(num);
}

// Debounce Function
function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

// Loading State
function setLoading(elementId, isLoading) {
    const element = document.getElementById(elementId);
    if (!element) return;
    
    if (isLoading) {
        element.disabled = true;
        element.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Loading...';
    } else {
        element.disabled = false;
        // Restore original text (you should store it before)
    }
}

// Confirm Dialog
function confirmAction(message, callback) {
    if (confirm(message)) {
        callback();
    }
}

// Date Formatter
function formatDate(dateString) {
    const options = { year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' };
    return new Date(dateString).toLocaleDateString('en-US', options);
}

// QR Code Generator (using API)
function generateQRCode(text, size = 300) {
    return `https://api.qrserver.com/v1/create-qr-code/?size=${size}x${size}&data=${encodeURIComponent(text)}`;
}

// Download QR Code
function downloadQRCode(shortCode) {
    const url = generateQRCode(`${window.location.origin}/${shortCode}`, 500);
    const a = document.createElement('a');
    a.href = url;
    a.download = `qr-${shortCode}.png`;
    a.click();
}

// Search/Filter Table
function filterTable(inputId, tableId) {
    const input = document.getElementById(inputId);
    const table = document.getElementById(tableId);
    
    if (!input || !table) return;
    
    input.addEventListener('keyup', debounce(function() {
        const filter = this.value.toUpperCase();
        const rows = table.getElementsByTagName('tr');
        
        for (let i = 1; i < rows.length; i++) {
            const row = rows[i];
            const cells = row.getElementsByTagName('td');
            let found = false;
            
            for (let j = 0; j < cells.length; j++) {
                const cell = cells[j];
                if (cell) {
                    const textValue = cell.textContent || cell.innerText;
                    if (textValue.toUpperCase().indexOf(filter) > -1) {
                        found = true;
                        break;
                    }
                }
            }
            
            row.style.display = found ? '' : 'none';
        }
    }, 300));
}

// Auto-update Stats
function setupAutoUpdate(interval = 300000) { // 5 minutes default
    setInterval(() => {
        if (document.getElementById('statsContainer')) {
            refreshStats();
        }
    }, interval);
}

async function refreshStats() {
    try {
        const result = await apiRequest('/api/stats.php?action=current');
        if (result.success) {
            updateStatsDisplay(result.data);
        }
    } catch (error) {
        console.error('Failed to refresh stats:', error);
    }
}

function updateStatsDisplay(stats) {
    // Update balance
    const balanceEl = document.getElementById('balanceDisplay');
    if (balanceEl && stats.balance !== undefined) {
        balanceEl.textContent = formatCurrency(stats.balance);
    }
    
    // Update views
    const viewsEl = document.getElementById('totalViews');
    if (viewsEl && stats.total_views !== undefined) {
        viewsEl.textContent = formatNumber(stats.total_views);
    }
    
    // Update earnings
    const earningsEl = document.getElementById('totalEarnings');
    if (earningsEl && stats.total_earnings !== undefined) {
        earningsEl.textContent = formatCurrency(stats.total_earnings);
    }
}

// Initialize app
document.addEventListener('DOMContentLoaded', () => {
    // Setup auto-update if on dashboard
    if (window.location.pathname.includes('dashboard')) {
        setupAutoUpdate();
    }
    
    // Initialize tooltips
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
});