<?php
// Notifications Page for Managers
// Start output buffering to capture content
ob_start();
?>

<div class="container mx-auto px-4 py-8">
    <div class="flex justify-between items-center mb-6">
        <div>
            <h1 class="text-3xl font-bold text-gray-800">Notifications</h1>
            <p class="text-gray-600 mt-1">View and manage all system notifications</p>
        </div>
        <div class="flex gap-2">
            <button onclick="markAllAsRead()" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg flex items-center gap-2">
                <i class="fas fa-check-double"></i>
                Mark All as Read
            </button>
            <button onclick="clearAllNotifications()" class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-lg flex items-center gap-2">
                <i class="fas fa-trash"></i>
                Clear All
            </button>
        </div>
    </div>

    <!-- Filters -->
    <div class="bg-white rounded-lg shadow p-4 mb-6">
        <div class="flex gap-4 items-center">
            <select id="notificationFilter" onchange="filterNotifications()" class="border border-gray-300 rounded px-3 py-2 text-sm">
                <option value="all">All Notifications</option>
                <option value="unread">Unread Only</option>
                <option value="low_stock">Low Stock</option>
                <option value="out_of_stock">Out of Stock</option>
                <option value="critical">Critical</option>
            </select>
            <button onclick="refreshNotifications()" class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-4 py-2 rounded-lg text-sm">
                <i class="fas fa-sync-alt mr-2"></i>Refresh
            </button>
        </div>
    </div>

    <!-- Notifications List -->
    <div class="bg-white rounded-lg shadow">
        <div id="notificationsList" class="divide-y divide-gray-200">
            <!-- Notifications will be loaded here -->
            <div class="p-8 text-center text-gray-500">
                <i class="fas fa-spinner fa-spin text-2xl mb-2"></i>
                <p>Loading notifications...</p>
            </div>
        </div>
    </div>

    <!-- Notification Details Modal -->
    <div id="notificationModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-lg shadow-xl max-w-2xl w-full max-h-[90vh] overflow-y-auto">
            <div class="sticky top-0 bg-white border-b border-gray-200 px-6 py-4 flex justify-between items-center">
                <h2 class="text-xl font-semibold text-gray-800">Notification Details</h2>
                <button onclick="closeNotificationModal()" class="text-gray-500 hover:text-gray-700">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            <div id="notificationDetails" class="p-6">
                <!-- Details will be loaded here -->
            </div>
            <div class="sticky bottom-0 bg-gray-50 border-t border-gray-200 px-6 py-4 flex justify-end gap-2">
                <button onclick="closeNotificationModal()" class="px-4 py-2 text-gray-700 hover:bg-gray-200 rounded-lg">
                    Close
                </button>
                <button id="clearNotificationBtn" onclick="clearCurrentNotification()" class="px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded-lg">
                    Clear Notification
                </button>
            </div>
        </div>
    </div>
</div>

<script>
// BASE is already declared in dashboard layout (window.APP_BASE_PATH)
// Use it directly - no need to redeclare
let currentNotificationId = null;
let allNotifications = [];

// Load notifications on page load
document.addEventListener('DOMContentLoaded', function() {
    loadNotifications();
    
    // Check if viewing specific notification from URL
    const urlParams = new URLSearchParams(window.location.search);
    const viewId = urlParams.get('view');
    if (viewId) {
        setTimeout(() => viewNotification(viewId), 1000); // Wait for notifications to load
    }
    
    // Auto-refresh every 30 seconds
    setInterval(loadNotifications, 30000);
});

// Load notifications
async function loadNotifications() {
    try {
        const token = localStorage.getItem('token') || localStorage.getItem('sellapp_token') || localStorage.getItem('auth_token');
        const headers = {
            'Content-Type': 'application/json',
        };
        
        if (token) {
            headers['Authorization'] = 'Bearer ' + token;
        }
        
        // BASE is already declared in dashboard layout
        const baseUrl = typeof BASE !== 'undefined' ? BASE : (window.APP_BASE_PATH || '');
        const response = await fetch(baseUrl + '/api/notifications', {
            method: 'GET',
            headers: headers,
            credentials: 'same-origin'
        });
        
        const data = await response.json();
        
        if (data.success) {
            allNotifications = data.notifications || [];
            renderNotifications();
        } else {
            showError('Failed to load notifications: ' + (data.error || 'Unknown error'));
        }
    } catch (error) {
        console.error('Error loading notifications:', error);
        showError('Failed to load notifications');
    }
}

// Render notifications
function renderNotifications() {
    const container = document.getElementById('notificationsList');
    const filter = document.getElementById('notificationFilter')?.value || 'all';
    
    let filtered = allNotifications;
    
    // Apply filter
    if (filter === 'unread') {
        filtered = filtered.filter(n => !n.read);
    } else if (filter === 'low_stock') {
        filtered = filtered.filter(n => n.type === 'low_stock');
    } else if (filter === 'out_of_stock') {
        filtered = filtered.filter(n => n.type === 'out_of_stock');
    } else if (filter === 'critical') {
        filtered = filtered.filter(n => n.priority === 'critical');
    }
    
    if (filtered.length === 0) {
        container.innerHTML = `
            <div class="p-8 text-center text-gray-500">
                <i class="fas fa-bell-slash text-4xl mb-4"></i>
                <p>No notifications found</p>
            </div>
        `;
        return;
    }
    
    container.innerHTML = filtered.map(notification => {
        const timeAgo = getTimeAgo(notification.created_at);
        const priorityClass = getPriorityClass(notification.priority);
        const iconClass = getIconClass(notification.type);
        const readClass = notification.read ? '' : 'font-bold';
        
        return `
            <div class="p-4 hover:bg-gray-50 cursor-pointer notification-item" 
                 onclick="viewNotification('${notification.id}')"
                 data-id="${notification.id}" 
                 data-read="${notification.read}">
                <div class="flex items-start space-x-3">
                    <div class="flex-shrink-0">
                        <div class="w-10 h-10 ${priorityClass} rounded-full flex items-center justify-center">
                            <i class="${iconClass} text-white"></i>
                        </div>
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center justify-between">
                            <p class="text-sm font-medium text-gray-900 ${readClass}">
                                ${notification.title || 'Notification'}
                            </p>
                            <div class="flex items-center gap-2">
                                <p class="text-xs text-gray-500">${timeAgo}</p>
                                ${!notification.read ? '<span class="w-2 h-2 bg-blue-500 rounded-full"></span>' : ''}
                            </div>
                        </div>
                        <p class="text-sm text-gray-600 mt-1">${notification.message || ''}</p>
                        ${notification.data ? `
                            <div class="mt-2 text-xs text-gray-500">
                                ${notification.data.product_name ? `Product: ${notification.data.product_name}` : ''}
                                ${notification.data.quantity !== undefined ? ` | Quantity: ${notification.data.quantity}` : ''}
                            </div>
                        ` : ''}
                    </div>
                    <div class="flex-shrink-0">
                        <button onclick="event.stopPropagation(); clearNotification('${notification.id}')" 
                                class="text-red-600 hover:text-red-800 p-2" title="Clear">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </div>
            </div>
        `;
    }).join('');
}

// View notification details
async function viewNotification(notificationId) {
    currentNotificationId = notificationId;
    
    try {
        const token = localStorage.getItem('token') || localStorage.getItem('sellapp_token') || localStorage.getItem('auth_token');
        const headers = {
            'Content-Type': 'application/json',
        };
        
        if (token) {
            headers['Authorization'] = 'Bearer ' + token;
        }
        
        // BASE is already declared in dashboard layout
        const baseUrl = typeof BASE !== 'undefined' ? BASE : (window.APP_BASE_PATH || '');
        const response = await fetch(baseUrl + '/api/notifications/' + encodeURIComponent(notificationId), {
            method: 'GET',
            headers: headers,
            credentials: 'same-origin'
        });
        
        if (!response.ok) {
            const contentType = response.headers.get('content-type');
            let errorMessage = 'Failed to load notification details';
            
            if (contentType && contentType.includes('application/json')) {
                const errorData = await response.json();
                errorMessage = errorData.error || errorMessage;
            }
            
            showError(errorMessage);
            console.error('Error loading notification details:', errorMessage);
            return;
        }
        
        const data = await response.json();
        
        if (data.success && data.notification) {
            const notif = data.notification;
            
            // Mark notification as read when viewed (for repair notifications and other database notifications)
            if (!notif.read && (notif.type === 'repair' || (typeof notificationId === 'string' && (notificationId.startsWith('notif_') || !isNaN(parseInt(notificationId)))))) {
                try {
                    const markReadResponse = await fetch(baseUrl + '/api/notifications/mark-read', {
                        method: 'POST',
                        headers: headers,
                        credentials: 'same-origin',
                        body: JSON.stringify({ notification_id: notificationId })
                    });
                    if (markReadResponse.ok) {
                        // Update the notification in the list
                        const notifIndex = allNotifications.findIndex(n => n.id === notificationId);
                        if (notifIndex !== -1) {
                            allNotifications[notifIndex].read = true;
                            renderNotifications();
                        }
                    }
                } catch (error) {
                    console.error('Error marking notification as read:', error);
                }
            }
            
            const details = document.getElementById('notificationDetails');
            
            // Format notification data based on type
            let dataHtml = '';
            if (notif.data && typeof notif.data === 'object') {
                if (notif.type === 'sms_purchase') {
                    // Format SMS purchase notification nicely
                    dataHtml = `
                        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 space-y-3">
                            <h3 class="font-semibold text-blue-900 mb-3">Purchase Details</h3>
                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="text-xs font-medium text-blue-700">Company</label>
                                    <p class="text-sm text-blue-900 font-medium">${notif.data.company_name || 'N/A'}</p>
                                </div>
                                <div>
                                    <label class="text-xs font-medium text-blue-700">User</label>
                                    <p class="text-sm text-blue-900 font-medium">${notif.data.username || 'N/A'}</p>
                                </div>
                                <div>
                                    <label class="text-xs font-medium text-blue-700">SMS Credits</label>
                                    <p class="text-sm text-blue-900 font-bold">${notif.data.sms_credits || 0} credits</p>
                                </div>
                                <div>
                                    <label class="text-xs font-medium text-blue-700">Amount Paid</label>
                                    <p class="text-sm text-blue-900 font-bold">₵${parseFloat(notif.data.amount || 0).toFixed(2)}</p>
                                </div>
                                <div class="col-span-2">
                                    <label class="text-xs font-medium text-blue-700">Payment ID</label>
                                    <p class="text-sm text-blue-600 font-mono">${notif.data.payment_id || 'N/A'}</p>
                                </div>
                            </div>
                        </div>
                    `;
                } else if (notif.type === 'sms_sent') {
                    // Format SMS sent notification nicely
                    dataHtml = `
                        <div class="bg-green-50 border border-green-200 rounded-lg p-4 space-y-3">
                            <h3 class="font-semibold text-green-900 mb-3">SMS Activity</h3>
                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="text-xs font-medium text-green-700">Company</label>
                                    <p class="text-sm text-green-900 font-medium">${notif.data.company_name || 'N/A'}</p>
                                </div>
                                <div>
                                    <label class="text-xs font-medium text-green-700">Messages Sent</label>
                                    <p class="text-sm text-green-900 font-bold">${notif.data.message_count || 0}</p>
                                </div>
                                <div class="col-span-2">
                                    <label class="text-xs font-medium text-green-700">Last Sent</label>
                                    <p class="text-sm text-green-600">${notif.data.last_sent ? new Date(notif.data.last_sent).toLocaleString() : 'N/A'}</p>
                                </div>
                            </div>
                        </div>
                    `;
                } else {
                    // Generic data display for other types
                    const dataEntries = Object.entries(notif.data).filter(([key]) => !['payment_id', 'company_id', 'user_id'].includes(key));
                    if (dataEntries.length > 0) {
                        dataHtml = `
                            <div class="bg-gray-50 border border-gray-200 rounded-lg p-4">
                                <h3 class="font-semibold text-gray-900 mb-3">Additional Details</h3>
                                <div class="space-y-2">
                                    ${dataEntries.map(([key, value]) => `
                                        <div class="flex justify-between items-start py-2 border-b border-gray-200 last:border-0">
                                            <span class="text-sm font-medium text-gray-600 capitalize">${key.replace(/_/g, ' ')}:</span>
                                            <span class="text-sm text-gray-900 text-right">${typeof value === 'object' ? JSON.stringify(value) : value}</span>
                                        </div>
                                    `).join('')}
                                </div>
                            </div>
                        `;
                    }
                }
            }
            
            details.innerHTML = `
                <div class="space-y-4">
                    <div class="bg-white border border-gray-200 rounded-lg p-4">
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="text-xs font-medium text-gray-500 uppercase tracking-wide">Type</label>
                                <p class="text-sm text-gray-900 font-medium mt-1 capitalize">${notif.type || 'N/A'}</p>
                            </div>
                            <div>
                                <label class="text-xs font-medium text-gray-500 uppercase tracking-wide">Priority</label>
                                <p class="text-sm text-gray-900 font-medium mt-1 capitalize">${notif.priority || 'normal'}</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-white border border-gray-200 rounded-lg p-4">
                        <label class="text-xs font-medium text-gray-500 uppercase tracking-wide">Title</label>
                        <p class="text-base text-gray-900 font-semibold mt-1">${notif.title || 'Notification'}</p>
                    </div>
                    
                    <div class="bg-white border border-gray-200 rounded-lg p-4">
                        <label class="text-xs font-medium text-gray-500 uppercase tracking-wide">Message</label>
                        <p class="text-sm text-gray-700 mt-1 leading-relaxed">${notif.message || 'No message'}</p>
                    </div>
                    
                    <div class="bg-white border border-gray-200 rounded-lg p-4">
                        <label class="text-xs font-medium text-gray-500 uppercase tracking-wide">Date & Time</label>
                        <p class="text-sm text-gray-900 mt-1">${new Date(notif.created_at).toLocaleString()}</p>
                    </div>
                    
                    ${dataHtml}
                </div>
            `;
            
            document.getElementById('notificationModal').classList.remove('hidden');
        } else {
            const errorMsg = data.error || 'Notification not found or has been dismissed';
            showError(errorMsg);
            console.error('Notification details error:', data);
        }
    } catch (error) {
        console.error('Error loading notification details:', error);
        showError('Failed to load notification details. Please try again.');
    }
}

// Close notification modal
function closeNotificationModal() {
    document.getElementById('notificationModal').classList.add('hidden');
    currentNotificationId = null;
}

// Clear notification
async function clearNotification(notificationId, skipConfirmation = false) {
    if (!skipConfirmation && !confirm('Are you sure you want to clear this notification?')) {
        return;
    }
    
    try {
        const token = localStorage.getItem('token') || localStorage.getItem('sellapp_token') || localStorage.getItem('auth_token');
        const headers = {
            'Content-Type': 'application/json',
        };
        
        if (token) {
            headers['Authorization'] = 'Bearer ' + token;
        }
        
        // BASE is already declared in dashboard layout
        const baseUrl = typeof BASE !== 'undefined' ? BASE : (window.APP_BASE_PATH || '');
        const response = await fetch(baseUrl + '/api/notifications/delete/' + encodeURIComponent(notificationId), {
            method: 'POST',
            headers: headers,
            credentials: 'same-origin'
        });
        
        const data = await response.json();
        
        if (data.success) {
            // Remove from local list immediately
            allNotifications = allNotifications.filter(n => {
                // Handle both string and numeric ID matching
                return n.id !== notificationId && 
                       String(n.id) !== String(notificationId) &&
                       n.id !== 'notif_' + notificationId &&
                       notificationId !== 'notif_' + n.id;
            });
            renderNotifications();
            
            // Reload notifications from server after a longer delay to ensure dismissal is persisted
            // Increased delay to 2 seconds to ensure database transaction is committed
            setTimeout(() => {
                loadNotifications();
            }, 2000);
            
            // Only show success message if not skipping confirmation (individual clear)
            if (!skipConfirmation) {
                showSuccess('Notification cleared');
            }
            
            // Refresh the bell icon notifications if NotificationSystem exists
            if (typeof window.notificationSystem !== 'undefined' && window.notificationSystem.loadNotifications) {
                setTimeout(() => {
                    if (typeof window.notificationSystem !== 'undefined' && window.notificationSystem.loadNotifications) {
                        window.notificationSystem.loadNotifications();
                    }
                }, 2000);
            }
        } else {
            showError('Failed to clear notification: ' + (data.error || 'Unknown error'));
        }
    } catch (error) {
        console.error('Error clearing notification:', error);
        showError('Failed to clear notification');
    }
}

// Clear current notification (from modal)
function clearCurrentNotification() {
    if (currentNotificationId) {
        clearNotification(currentNotificationId);
        closeNotificationModal();
    }
}

// Mark all as read
async function markAllAsRead() {
    try {
        const token = localStorage.getItem('token') || localStorage.getItem('sellapp_token') || localStorage.getItem('auth_token');
        const headers = {
            'Content-Type': 'application/json',
        };
        
        if (token) {
            headers['Authorization'] = 'Bearer ' + token;
        }
        
        // BASE is already declared in dashboard layout
        const baseUrl = typeof BASE !== 'undefined' ? BASE : (window.APP_BASE_PATH || '');
        const response = await fetch(baseUrl + '/api/notifications/mark-read', {
            method: 'POST',
            headers: headers,
            credentials: 'same-origin',
            body: JSON.stringify({ all: true })
        });
        
        const data = await response.json();
        
        if (data.success) {
            // Mark all as read in local list
            allNotifications.forEach(n => n.read = true);
            renderNotifications();
            showSuccess('All notifications marked as read');
        } else {
            showError('Failed to mark all as read');
        }
    } catch (error) {
        console.error('Error marking all as read:', error);
        showError('Failed to mark all as read');
    }
}

// Clear all notifications
async function clearAllNotifications() {
    if (!confirm('Are you sure you want to clear all notifications? This action cannot be undone.')) {
        return;
    }
    
    try {
        // Clear each notification individually (skip individual confirmations)
        const notificationIds = allNotifications.map(n => n.id);
        for (const notificationId of notificationIds) {
            await clearNotification(notificationId, true); // Pass true to skip confirmation
        }
        
        // Show success message once
        showSuccess('All notifications cleared');
        
        // Clear local list immediately
        allNotifications = [];
        renderNotifications();
        
        // Reload notifications from server after delay to ensure dismissals are persisted
        setTimeout(async () => {
            await loadNotifications();
            
            // Refresh the bell icon notifications
            if (typeof window.notificationSystem !== 'undefined' && window.notificationSystem.loadNotifications) {
                window.notificationSystem.loadNotifications();
            }
        }, 2000);
    } catch (error) {
        console.error('Error clearing all notifications:', error);
        showError('Failed to clear all notifications');
    }
}

// Filter notifications
function filterNotifications() {
    renderNotifications();
}

// Refresh notifications
function refreshNotifications() {
    loadNotifications();
}

// Helper functions
function getTimeAgo(dateString) {
    const date = new Date(dateString);
    const now = new Date();
    const diffMs = now - date;
    const diffMins = Math.floor(diffMs / 60000);
    const diffHours = Math.floor(diffMs / 3600000);
    const diffDays = Math.floor(diffMs / 86400000);
    
    if (diffMins < 1) return 'Just now';
    if (diffMins < 60) return `${diffMins} minute${diffMins > 1 ? 's' : ''} ago`;
    if (diffHours < 24) return `${diffHours} hour${diffHours > 1 ? 's' : ''} ago`;
    if (diffDays < 7) return `${diffDays} day${diffDays > 1 ? 's' : ''} ago`;
    return date.toLocaleDateString();
}

function getPriorityClass(priority) {
    switch (priority) {
        case 'critical': return 'bg-red-500';
        case 'high': return 'bg-orange-500';
        case 'medium': return 'bg-yellow-500';
        case 'low': return 'bg-blue-500';
        default: return 'bg-gray-500';
    }
}

function getIconClass(type) {
    switch (type) {
        case 'low_stock': return 'fas fa-exclamation-triangle';
        case 'out_of_stock': return 'fas fa-times-circle';
        case 'repair': return 'fas fa-tools';
        case 'swap': return 'fas fa-exchange-alt';
        default: return 'fas fa-bell';
    }
}

function showSuccess(message) {
    // Simple success notification
    const toast = document.createElement('div');
    toast.className = 'fixed top-4 right-4 bg-green-500 text-white px-6 py-3 rounded-lg shadow-lg z-50';
    toast.textContent = message;
    document.body.appendChild(toast);
    setTimeout(() => toast.remove(), 3000);
}

function showError(message) {
    // Simple error notification
    const toast = document.createElement('div');
    toast.className = 'fixed top-4 right-4 bg-red-500 text-white px-6 py-3 rounded-lg shadow-lg z-50';
    toast.textContent = message;
    document.body.appendChild(toast);
    setTimeout(() => toast.remove(), 3000);
}
</script>

<?php
// Capture the content
$content = ob_get_clean();

// Set page title
$pageTitle = 'Notifications';

// Include the dashboard layout
include __DIR__ . '/layouts/dashboard.php';
?>

