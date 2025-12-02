<?php
// Migration Tools Index Page
?>

<!-- Bulma CSS for this page -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bulma@0.9.4/css/bulma.min.css">
<link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>
<!-- Font Awesome for icons -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

<style>
    /* Custom styles for migration tools page */
    .migration-card {
        transition: all 0.3s ease;
    }
    
    .migration-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    }
    
    .badge-recommended {
        background-color: #f3e8ff;
        color: #6b21a8;
    }
    
    .badge-new {
        background-color: #dbeafe;
        color: #1e40af;
    }
    
    .badge-required {
        background-color: #d1fae5;
        color: #065f46;
    }
    
    /* Custom purple button for Bulma */
    .button.is-purple {
        background-color: #9333ea;
        border-color: transparent;
        color: #fff;
    }
    
    .button.is-purple:hover,
    .button.is-purple.is-hovered {
        background-color: #7e22ce;
        border-color: transparent;
        color: #fff;
    }
    
    .button.is-purple:active,
    .button.is-purple.is-active {
        background-color: #6b21a8;
        border-color: transparent;
        color: #fff;
    }
    
    .button.is-purple:focus:not(:active) {
        box-shadow: 0 0 0 0.125em rgba(147, 51, 234, 0.25);
    }
</style>

<div class="container is-max-desktop" style="max-width: 1024px; margin: 0 auto; padding: 1.5rem;">
    <!-- Page Header -->
    <div class="mb-5">
        <h1 class="title is-2 has-text-grey-dark">Migration Tools</h1>
        <p class="subtitle is-6 has-text-grey">Run database migrations and seed data</p>
    </div>

    <!-- Main Card -->
    <div class="card">
        <div class="card-content">
            <h2 class="title is-4 has-text-grey-dark mb-5">Available Migrations</h2>
            
            <div class="content">
                <!-- Backup Columns Migration -->
                <div class="box migration-card" style="border-left: 4px solid #9333ea; background-color: #faf5ff;">
                    <div class="is-flex is-align-items-center is-justify-content-space-between" style="flex-wrap: wrap; gap: 1rem;">
                        <div style="flex: 1; min-width: 250px;">
                            <div class="is-flex is-align-items-center mb-2" style="flex-wrap: wrap; gap: 0.5rem;">
                                <h3 class="title is-5 has-text-grey-dark mb-0">Backup Columns Migration</h3>
                                <span class="tag badge-recommended is-size-7 has-text-weight-semibold">RECOMMENDED</span>
                            </div>
                            <p class="has-text-grey mb-2">
                                Adds backup_type and description columns to backups table. Required for backup statistics to work properly.
                            </p>
                            <p class="has-text-purple has-text-weight-medium is-size-7">
                                <span class="icon-text">
                                    <span class="icon">
                                        <i class="fas fa-exclamation-triangle"></i>
                                    </span>
                                    <span>Run this if backup statistics show all zeros</span>
                                </span>
                            </p>
                        </div>
                        <div class="is-flex-shrink-0">
                            <a href="<?= BASE_URL_PATH ?>/dashboard/tools/run-backup-columns-migration" 
                               class="button is-purple">
                                <span class="icon">
                                    <i class="fas fa-play"></i>
                                </span>
                                <span>Run Migration</span>
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Cloudinary URL Migration -->
                <div class="box migration-card" style="border-left: 4px solid #2563eb; background-color: #eff6ff;">
                    <div class="is-flex is-align-items-center is-justify-content-space-between" style="flex-wrap: wrap; gap: 1rem;">
                        <div style="flex: 1; min-width: 250px;">
                            <div class="is-flex is-align-items-center mb-2" style="flex-wrap: wrap; gap: 0.5rem;">
                                <h3 class="title is-5 has-text-grey-dark mb-0">Cloudinary URL Migration</h3>
                                <span class="tag badge-new is-size-7 has-text-weight-semibold">NEW</span>
                            </div>
                            <p class="has-text-grey mb-2">
                                Adds cloudinary_url column to backups table. Required for automatic backup uploads to Cloudinary.
                            </p>
                            <p class="has-text-info has-text-weight-medium is-size-7">
                                <span class="icon-text">
                                    <span class="icon">
                                        <i class="fas fa-exclamation-triangle"></i>
                                    </span>
                                    <span>Run this to enable Cloudinary backup storage</span>
                                </span>
                            </p>
                        </div>
                        <div class="is-flex-shrink-0">
                            <a href="<?= BASE_URL_PATH ?>/dashboard/tools/run-cloudinary-url-migration" 
                               class="button is-info">
                                <span class="icon">
                                    <i class="fas fa-play"></i>
                                </span>
                                <span>Run Migration</span>
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Email Logs Migration -->
                <div class="box migration-card" style="border-left: 4px solid #10b981; background-color: #f0fdf4;">
                    <div class="is-flex is-align-items-center is-justify-content-space-between" style="flex-wrap: wrap; gap: 1rem;">
                        <div style="flex: 1; min-width: 250px;">
                            <div class="is-flex is-align-items-center mb-2" style="flex-wrap: wrap; gap: 0.5rem;">
                                <h3 class="title is-5 has-text-grey-dark mb-0">Email Logs Migration</h3>
                                <span class="tag badge-required is-size-7 has-text-weight-semibold">REQUIRED</span>
                            </div>
                            <p class="has-text-grey mb-2">
                                Creates the email_logs table to track all emails sent by the system (automatic and manual).
                            </p>
                            <p class="has-text-success has-text-weight-medium is-size-7">
                                <span class="icon-text">
                                    <span class="icon">
                                        <i class="fas fa-exclamation-triangle"></i>
                                    </span>
                                    <span>Required for Email Logs page to work</span>
                                </span>
                            </p>
                        </div>
                        <div class="is-flex-shrink-0">
                            <a href="<?= BASE_URL_PATH ?>/dashboard/tools/run-email-logs-migration" 
                               class="button is-success">
                                <span class="icon">
                                    <i class="fas fa-play"></i>
                                </span>
                                <span>Run Migration</span>
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Laptop Category Migration -->
                <div class="box migration-card" style="border-left: 4px solid #6b7280;">
                    <div class="is-flex is-align-items-center is-justify-content-space-between" style="flex-wrap: wrap; gap: 1rem;">
                        <div style="flex: 1; min-width: 250px;">
                            <h3 class="title is-5 has-text-grey-dark mb-2">Laptop Category & Brands</h3>
                            <p class="has-text-grey">
                                Creates the "Laptops" category and seeds default laptop brands (Dell, HP, Lenovo, Apple, and more)
                            </p>
                        </div>
                        <div class="is-flex-shrink-0">
                            <a href="<?= BASE_URL_PATH ?>/dashboard/tools/run-laptop-migration" 
                               class="button is-primary">
                                <span class="icon">
                                    <i class="fas fa-play"></i>
                                </span>
                                <span>Run Migration</span>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Important Notes -->
    <div class="notification is-warning is-light mt-5">
        <div class="is-flex is-align-items-start">
            <span class="icon is-medium mr-3">
                <i class="fas fa-exclamation-triangle"></i>
            </span>
            <div>
                <h4 class="title is-6 has-text-warning-dark mb-3">Important Notes</h4>
                <ul class="has-text-warning-dark" style="list-style: disc; margin-left: 1.5rem;">
                    <li>Migrations are idempotent - safe to run multiple times</li>
                    <li>Existing data will not be overwritten</li>
                    <li>Only system administrators can access these tools</li>
                </ul>
            </div>
        </div>
    </div>
</div>
