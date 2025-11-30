<?php
// Migration Tools Index Page
?>

<div class="max-w-4xl mx-auto">
    <div class="mb-6">
        <h1 class="text-3xl font-bold text-gray-800">Migration Tools</h1>
        <p class="text-gray-600 mt-2">Run database migrations and seed data</p>
    </div>

    <div class="bg-white rounded-lg shadow-sm border p-6">
        <h2 class="text-xl font-semibold text-gray-800 mb-4">Available Migrations</h2>
        
        <div class="space-y-4">
            <!-- Backup Columns Migration -->
            <div class="border border-purple-200 rounded-lg p-4 hover:bg-purple-50 transition bg-purple-25">
                <div class="flex items-center justify-between">
                    <div class="flex-1">
                        <div class="flex items-center">
                            <h3 class="text-lg font-medium text-gray-900">Backup Columns Migration</h3>
                            <span class="ml-2 px-2 py-1 text-xs font-semibold bg-purple-100 text-purple-800 rounded">RECOMMENDED</span>
                        </div>
                        <p class="text-sm text-gray-600 mt-1">
                            Adds backup_type and description columns to backups table. Required for backup statistics to work properly.
                        </p>
                        <p class="text-xs text-purple-700 mt-1 font-medium">
                            ⚠️ Run this if backup statistics show all zeros
                        </p>
                    </div>
                    <a href="<?= BASE_URL_PATH ?>/dashboard/tools/run-backup-columns-migration" 
                       class="ml-4 px-6 py-2 bg-purple-600 text-white rounded-md hover:bg-purple-700 transition flex-shrink-0">
                        Run Migration
                    </a>
                </div>
            </div>

            <!-- Cloudinary URL Migration -->
            <div class="border border-blue-200 rounded-lg p-4 hover:bg-blue-50 transition bg-blue-25">
                <div class="flex items-center justify-between">
                    <div class="flex-1">
                        <div class="flex items-center">
                            <h3 class="text-lg font-medium text-gray-900">Cloudinary URL Migration</h3>
                            <span class="ml-2 px-2 py-1 text-xs font-semibold bg-blue-100 text-blue-800 rounded">NEW</span>
                        </div>
                        <p class="text-sm text-gray-600 mt-1">
                            Adds cloudinary_url column to backups table. Required for automatic backup uploads to Cloudinary.
                        </p>
                        <p class="text-xs text-blue-700 mt-1 font-medium">
                            ⚠️ Run this to enable Cloudinary backup storage
                        </p>
                    </div>
                    <a href="<?= BASE_URL_PATH ?>/dashboard/tools/run-cloudinary-url-migration" 
                       class="ml-4 px-6 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition flex-shrink-0">
                        Run Migration
                    </a>
                </div>
            </div>

            <!-- Laptop Category Migration -->
            <div class="border border-gray-200 rounded-lg p-4 hover:bg-gray-50 transition">
                <div class="flex items-center justify-between">
                    <div class="flex-1">
                        <h3 class="text-lg font-medium text-gray-900">Laptop Category & Brands</h3>
                        <p class="text-sm text-gray-600 mt-1">
                            Creates the "Laptops" category and seeds default laptop brands (Dell, HP, Lenovo, Apple, and more)
                        </p>
                    </div>
                    <a href="<?= BASE_URL_PATH ?>/dashboard/tools/run-laptop-migration" 
                       class="ml-4 px-6 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition flex-shrink-0">
                        Run Migration
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="mt-6 bg-yellow-50 border border-yellow-200 rounded-lg p-4">
        <div class="flex items-start">
            <svg class="w-5 h-5 text-yellow-600 mt-0.5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
            </svg>
            <div>
                <h4 class="font-medium text-yellow-900">Important Notes</h4>
                <ul class="mt-2 text-sm text-yellow-800 list-disc list-inside space-y-1">
                    <li>Migrations are idempotent - safe to run multiple times</li>
                    <li>Existing data will not be overwritten</li>
                    <li>Only system administrators can access these tools</li>
                </ul>
            </div>
        </div>
    </div>
</div>

