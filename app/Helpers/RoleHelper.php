<?php

namespace App\Helpers;

if (!class_exists('App\Helpers\RoleHelper')) {
    class RoleHelper {
    
    /**
     * Check if user has access to a specific route based on their role
     */
    public static function hasAccess($userRole, $route) {
        $rolePermissions = [
            'admin' => [
                'dashboard',
                'dashboard/users',
                'dashboard/companies',
                'dashboard/staff',
                'dashboard/categories',
                'dashboard/subcategories',
                'dashboard/brands',
                'dashboard/inventory',
                'dashboard/sales',
                'dashboard/reports',
                'dashboard/settings'
            ],
            'manager' => [
                'dashboard',
                'dashboard/staff',
                'dashboard/categories',
                'dashboard/subcategories',
                'dashboard/brands',
                'dashboard/inventory',
                'dashboard/reports',
                'dashboard/settings'
            ],
            'salesperson' => [
                'dashboard',
                'dashboard/inventory',
                'dashboard/sales',
                'dashboard/customers'
            ],
            'technician' => [
                'dashboard',
                'dashboard/inventory',
                'dashboard/repairs'
            ]
        ];
        
        // Admin has access to everything
        if ($userRole === 'admin') {
            return true;
        }
        
        // Check if user's role has access to the route
        if (isset($rolePermissions[$userRole])) {
            return in_array($route, $rolePermissions[$userRole]);
        }
        
        return false;
    }
    
    /**
     * Get allowed routes for a specific role
     */
    public static function getAllowedRoutes($role) {
        $rolePermissions = [
            'admin' => [
                'dashboard' => 'Dashboard',
                'dashboard/users' => 'User Management',
                'dashboard/companies' => 'Company Management',
                'dashboard/staff' => 'Staff Management',
                'dashboard/categories' => 'Categories',
                'dashboard/subcategories' => 'Subcategories',
                'dashboard/brands' => 'Brands',
                'dashboard/inventory' => 'Product Management',
                'dashboard/sales' => 'Sales',
                'dashboard/reports' => 'Reports',
                'dashboard/settings' => 'Settings'
            ],
            'manager' => [
                'dashboard' => 'Dashboard',
                'dashboard/staff' => 'Staff Management',
                'dashboard/categories' => 'Categories',
                'dashboard/subcategories' => 'Subcategories',
                'dashboard/brands' => 'Brands',
                'dashboard/inventory' => 'Product Management',
                'dashboard/reports' => 'Reports',
                'dashboard/settings' => 'Settings'
            ],
            'salesperson' => [
                'dashboard' => 'Dashboard',
                'dashboard/inventory' => 'Product Management',
                'dashboard/sales' => 'Sales',
                'dashboard/customers' => 'Customers'
            ],
            'technician' => [
                'dashboard' => 'Dashboard',
                'dashboard/inventory' => 'Product Management',
                'dashboard/repairs' => 'Repairs'
            ]
        ];
        
        return $rolePermissions[$role] ?? [];
    }
    
    /**
     * Check if user can perform a specific action
     */
    public static function canPerformAction($userRole, $action) {
        $actionPermissions = [
            'admin' => [
                'create_user', 'edit_user', 'delete_user',
                'create_company', 'edit_company', 'delete_company',
                'create_staff', 'edit_staff', 'delete_staff',
                'create_category', 'edit_category', 'delete_category',
                'create_subcategory', 'edit_subcategory', 'delete_subcategory',
                'create_brand', 'edit_brand', 'delete_brand',
                'create_product', 'edit_product', 'delete_product',
                'view_sales', 'create_sale', 'edit_sale',
                'view_reports', 'export_reports',
                'manage_settings'
            ],
            'manager' => [
                'create_staff', 'edit_staff', 'delete_staff',
                'create_category', 'edit_category', 'delete_category',
                'create_subcategory', 'edit_subcategory', 'delete_subcategory',
                'create_brand', 'edit_brand', 'delete_brand',
                'create_product', 'edit_product', 'delete_product',
                'view_sales', 'create_sale', 'edit_sale',
                'view_reports', 'export_reports',
                'manage_settings'
            ],
            'salesperson' => [
                'view_product', 'create_sale', 'edit_sale',
                'view_customers', 'create_customer', 'edit_customer'
            ],
            'technician' => [
                'view_product', 'create_repair', 'edit_repair',
                'update_product_quantity'
            ]
        ];
        
        // Admin can do everything
        if ($userRole === 'admin') {
            return true;
        }
        
        // Check if user's role can perform the action
        if (isset($actionPermissions[$userRole])) {
            return in_array($action, $actionPermissions[$userRole]);
        }
        
        return false;
    }
}
}
