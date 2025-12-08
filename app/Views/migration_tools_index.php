<?php
// Migration Tools Index Page
?>

<div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
    <div class="mb-6">
        <h1 class="text-2xl sm:text-3xl font-bold text-gray-800">Migration Tools</h1>
        <p class="text-sm sm:text-base text-gray-600 mt-2">Run database migrations and seed data</p>
    </div>

    <div class="bg-white rounded-lg shadow-sm border p-4 sm:p-6">
        <h2 class="text-lg sm:text-xl font-semibold text-gray-800 mb-4">Available Migrations</h2>
        
        <div class="space-y-4">
            <!-- Backup Columns Migration -->
            <div class="border border-purple-200 rounded-lg p-4 hover:bg-purple-50 transition bg-purple-25">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div class="flex-1 min-w-0">
                        <div class="flex flex-wrap items-center gap-2 mb-2">
                            <h3 class="text-base sm:text-lg font-medium text-gray-900">Backup Columns Migration</h3>
                            <span class="px-2 py-1 text-xs font-semibold bg-purple-100 text-purple-800 rounded whitespace-nowrap">RECOMMENDED</span>
                        </div>
                        <p class="text-sm text-gray-600 mt-1 break-words">
                            Adds backup_type and description columns to backups table. Required for backup statistics to work properly.
                        </p>
                        <p class="text-xs text-purple-700 mt-1 font-medium break-words">
                            ⚠️ Run this if backup statistics show all zeros
                        </p>
                    </div>
                    <a href="<?= BASE_URL_PATH ?>/dashboard/tools/run-backup-columns-migration" 
                       class="w-full sm:w-auto sm:flex-shrink-0 px-6 py-2 bg-purple-600 text-white rounded-md hover:bg-purple-700 transition text-center text-sm sm:text-base">
                        Run Migration
                    </a>
                </div>
            </div>

            <!-- Cloudinary URL Migration -->
            <div class="border border-blue-200 rounded-lg p-4 hover:bg-blue-50 transition bg-blue-25">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div class="flex-1 min-w-0">
                        <div class="flex flex-wrap items-center gap-2 mb-2">
                            <h3 class="text-base sm:text-lg font-medium text-gray-900">Cloudinary URL Migration</h3>
                            <span class="px-2 py-1 text-xs font-semibold bg-blue-100 text-blue-800 rounded whitespace-nowrap">NEW</span>
                        </div>
                        <p class="text-sm text-gray-600 mt-1 break-words">
                            Adds cloudinary_url column to backups table. Required for automatic backup uploads to Cloudinary.
                        </p>
                        <p class="text-xs text-blue-700 mt-1 font-medium break-words">
                            ⚠️ Run this to enable Cloudinary backup storage
                        </p>
                    </div>
                    <a href="<?= BASE_URL_PATH ?>/dashboard/tools/run-cloudinary-url-migration" 
                       class="w-full sm:w-auto sm:flex-shrink-0 px-6 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition text-center text-sm sm:text-base">
                        Run Migration
                    </a>
                </div>
            </div>

            <!-- Email Logs Migration -->
            <div class="border border-green-200 rounded-lg p-4 hover:bg-green-50 transition bg-green-25">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div class="flex-1 min-w-0">
                        <div class="flex flex-wrap items-center gap-2 mb-2">
                            <h3 class="text-base sm:text-lg font-medium text-gray-900">Email Logs Migration</h3>
                            <span class="px-2 py-1 text-xs font-semibold bg-green-100 text-green-800 rounded whitespace-nowrap">REQUIRED</span>
                        </div>
                        <p class="text-sm text-gray-600 mt-1 break-words">
                            Creates the email_logs table to track all emails sent by the system (automatic and manual).
                        </p>
                        <p class="text-xs text-green-700 mt-1 font-medium break-words">
                            ⚠️ Required for Email Logs page to work
                        </p>
                    </div>
                    <a href="<?= BASE_URL_PATH ?>/dashboard/tools/run-email-logs-migration" 
                       class="w-full sm:w-auto sm:flex-shrink-0 px-6 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 transition text-center text-sm sm:text-base">
                        Run Migration
                    </a>
                </div>
            </div>

            <!-- User Activity Logs Migration -->
            <div class="border border-indigo-200 rounded-lg p-4 hover:bg-indigo-50 transition bg-indigo-25">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div class="flex-1 min-w-0">
                        <div class="flex flex-wrap items-center gap-2 mb-2">
                            <h3 class="text-base sm:text-lg font-medium text-gray-900">User Activity Logs Migration</h3>
                            <span class="px-2 py-1 text-xs font-semibold bg-indigo-100 text-indigo-800 rounded whitespace-nowrap">REQUIRED</span>
                        </div>
                        <p class="text-sm text-gray-600 mt-1 break-words">
                            Creates the user_activity_logs table to track user login/logout activity, session duration, and IP addresses.
                        </p>
                        <p class="text-xs text-indigo-700 mt-1 font-medium break-words">
                            ⚠️ Required for User Activity Logs page to work
                        </p>
                    </div>
                    <a href="<?= BASE_URL_PATH ?>/dashboard/tools/run-user-activity-logs-migration" 
                       class="w-full sm:w-auto sm:flex-shrink-0 px-6 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 transition text-center text-sm sm:text-base">
                        Run Migration
                    </a>
                </div>
            </div>

            <!-- Laptop Category Migration -->
            <div class="border border-gray-200 rounded-lg p-4 hover:bg-gray-50 transition">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div class="flex-1 min-w-0">
                        <h3 class="text-base sm:text-lg font-medium text-gray-900 mb-2">Laptop Category & Brands</h3>
                        <p class="text-sm text-gray-600 mt-1 break-words">
                            Creates the "Laptops" category and seeds default laptop brands (Dell, HP, Lenovo, Apple, and more)
                        </p>
                    </div>
                    <a href="<?= BASE_URL_PATH ?>/dashboard/tools/run-laptop-migration" 
                       class="w-full sm:w-auto sm:flex-shrink-0 px-6 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition text-center text-sm sm:text-base">
                        Run Migration
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="mt-6 bg-yellow-50 border border-yellow-200 rounded-lg p-4">
        <div class="flex items-start">
            <svg class="w-5 h-5 text-yellow-600 mt-0.5 mr-2 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
            </svg>
            <div class="min-w-0 flex-1">
                <h4 class="font-medium text-yellow-900">Important Notes</h4>
                <ul class="mt-2 text-sm text-yellow-800 list-disc list-inside space-y-1 break-words">
                    <li>Migrations are idempotent - safe to run multiple times</li>
                    <li>Existing data will not be overwritten</li>
                    <li>Only system administrators can access these tools</li>
                </ul>
            </div>
        </div>
    </div>
</div>
