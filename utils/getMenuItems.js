/**
 * Menu Filtering Utility
 * Provides helper functions for role-based menu filtering
 */

import { sidebarItems } from '../config/sidebarConfig.js';

/**
 * Get menu items filtered by user role
 * @param {string} role - User role (salesperson, technician, manager, system_admin)
 * @returns {Array} Array of menu items the user can access
 */
export function getMenuItems(role) {
  if (!role) {
    return [];
  }
  
  return sidebarItems.filter(item => item.roles.includes(role));
}

/**
 * Get menu items grouped by category
 * @param {string} role - User role
 * @returns {Object} Grouped menu items
 */
export function getGroupedMenuItems(role) {
  const items = getMenuItems(role);
  
  return {
    main: items.filter(item => ['dashboard'].includes(item.id)),
    management: items.filter(item => ['companies', 'users', 'staff', 'categories', 'subcategories', 'brands'].includes(item.id)),
    operations: items.filter(item => ['inventory', 'pos', 'repairs', 'swaps', 'customers'].includes(item.id)),
    analytics: items.filter(item => ['reports', 'analytics'].includes(item.id)),
    settings: items.filter(item => ['settings'].includes(item.id))
  };
}

/**
 * Check if user has access to a specific menu item
 * @param {string} itemId - Menu item ID
 * @param {string} role - User role
 * @returns {boolean} Has access
 */
export function hasMenuAccess(itemId, role) {
  const item = sidebarItems.find(item => item.id === itemId);
  return item ? item.roles.includes(role) : false;
}

/**
 * Get user's primary navigation items (most important for their role)
 * @param {string} role - User role
 * @returns {Array} Primary navigation items
 */
export function getPrimaryMenuItems(role) {
  const rolePriorities = {
    'system_admin': ['dashboard', 'companies', 'users', 'analytics', 'settings'],
    'manager': ['dashboard', 'staff', 'inventory', 'reports', 'settings'],
    'salesperson': ['dashboard', 'pos', 'customers', 'inventory'],
    'technician': ['dashboard', 'repairs', 'swaps', 'inventory']
  };
  
  const priorities = rolePriorities[role] || [];
  const allItems = getMenuItems(role);
  
  return priorities
    .map(id => allItems.find(item => item.id === id))
    .filter(Boolean);
}

/**
 * Get menu items for mobile navigation (limited set)
 * @param {string} role - User role
 * @returns {Array} Mobile menu items
 */
export function getMobileMenuItems(role) {
  return getPrimaryMenuItems(role).slice(0, 5); // Limit to 5 items for mobile
}
