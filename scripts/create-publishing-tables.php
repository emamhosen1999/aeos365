<?php

/**
 * Create CMS Block Publishing & Versioning Tables
 * Direct PDO execution - bypasses Laravel Bootstrap issues
 */

// Database connection details - from .env
$db_host = '127.0.0.1';
$db_user = 'root';
$db_pass = '';
$db_name = 'aeos365';

try {
    $pdo = new PDO(
        "mysql:host=$db_host;charset=utf8mb4",
        $db_user,
        $db_pass
    );
    
    // Ensure database exists
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$db_name`");
    $pdo->exec("USE `$db_name`");
    
    echo "✅ Connected to database: $db_name\n\n";
    
    // Table 1: cms_block_versions
    echo "📦 Creating cms_block_versions table...\n";
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `cms_block_versions` (
            `id` bigint unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
            `cms_page_block_id` bigint unsigned NOT NULL,
            `version_number` int NOT NULL DEFAULT 1,
            `version_label` varchar(255) COLLATE utf8mb4_unicode_ci NULL,
            `block_data` longtext COLLATE utf8mb4_unicode_ci NULL,
            `metadata` longtext COLLATE utf8mb4_unicode_ci NULL,
            `change_summary` text COLLATE utf8mb4_unicode_ci NULL,
            `change_description` text COLLATE utf8mb4_unicode_ci NULL,
            `created_by_user_id` varchar(255) COLLATE utf8mb4_unicode_ci NULL,
            `edited_by_user_id` varchar(255) COLLATE utf8mb4_unicode_ci NULL,
            `created_at` timestamp NULL,
            `updated_at` timestamp NULL,
            
            UNIQUE KEY `cms_block_versions_cms_page_block_id_version_number_unique` (
                `cms_page_block_id`, 
                `version_number`
            ),
            KEY `cms_block_versions_cms_page_block_id_index` (`cms_page_block_id`),
            KEY `cms_block_versions_version_number_index` (`version_number`),
            KEY `cms_block_versions_created_at_index` (`created_at`),
            
            CONSTRAINT `cms_block_versions_cms_page_block_id_foreign` 
                FOREIGN KEY (`cms_page_block_id`) 
                REFERENCES `cms_page_blocks` (`id`) 
                ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "✅ Created cms_block_versions table\n";
    
    // Table 2: cms_block_publishes
    echo "📦 Creating cms_block_publishes table...\n";
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `cms_block_publishes` (
            `id` bigint unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
            `cms_page_block_id` bigint unsigned NOT NULL,
            `cms_block_version_id` bigint unsigned NULL,
            `status` enum('draft','scheduled','published','archived') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
            `scheduled_publish_at` datetime NULL,
            `published_at` datetime NULL,
            `archived_at` datetime NULL,
            `scheduled_unpublish_at` datetime NULL,
            `published_by_user_id` varchar(255) COLLATE utf8mb4_unicode_ci NULL,
            `archived_by_user_id` varchar(255) COLLATE utf8mb4_unicode_ci NULL,
            `visibility` enum('public','internal','private','draft_only') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft_only',
            `require_approval` tinyint(1) NOT NULL DEFAULT 0,
            `is_featured` tinyint(1) NOT NULL DEFAULT 0,
            `auto_publish` tinyint(1) NOT NULL DEFAULT 0,
            `auto_unpublish` tinyint(1) NOT NULL DEFAULT 0,
            `publish_duration_days` int NULL,
            `publish_notes` text COLLATE utf8mb4_unicode_ci NULL,
            `rejection_reason` text COLLATE utf8mb4_unicode_ci NULL,
            `workflow_state` enum('pending_review','approved','rejected','ready') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'ready',
            `view_count` int NOT NULL DEFAULT 0,
            `interaction_count` int NOT NULL DEFAULT 0,
            `created_at` timestamp NULL,
            `updated_at` timestamp NULL,
            
            KEY `cms_block_publishes_cms_page_block_id_index` (`cms_page_block_id`),
            KEY `cms_block_publishes_status_scheduled_publish_at_index` (`status`, `scheduled_publish_at`),
            KEY `cms_block_publishes_status_published_at_index` (`status`, `published_at`),
            KEY `cms_block_publishes_visibility_index` (`visibility`),
            KEY `cms_block_publishes_workflow_state_index` (`workflow_state`),
            
            CONSTRAINT `cms_block_publishes_cms_page_block_id_foreign` 
                FOREIGN KEY (`cms_page_block_id`) 
                REFERENCES `cms_page_blocks` (`id`) 
                ON DELETE CASCADE,
            CONSTRAINT `cms_block_publishes_cms_block_version_id_foreign`
                FOREIGN KEY (`cms_block_version_id`)
                REFERENCES `cms_block_versions` (`id`)
                ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "✅ Created cms_block_publishes table\n";
    
    // Table 3: cms_block_revisions
    echo "📦 Creating cms_block_revisions table...\n";
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `cms_block_revisions` (
            `id` bigint unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
            `cms_page_block_id` bigint unsigned NOT NULL,
            `cms_block_version_id` bigint unsigned NULL,
            `revision_type` enum('created','updated','published','archived','restored','reverted','scheduled','approved','rejected') COLLATE utf8mb4_unicode_ci NOT NULL,
            `change_details` text COLLATE utf8mb4_unicode_ci NULL,
            `diff_json` longtext COLLATE utf8mb4_unicode_ci NULL,
            `before_state` longtext COLLATE utf8mb4_unicode_ci NULL,
            `after_state` longtext COLLATE utf8mb4_unicode_ci NULL,
            `user_id` varchar(255) COLLATE utf8mb4_unicode_ci NULL,
            `user_name` varchar(255) COLLATE utf8mb4_unicode_ci NULL,
            `user_email` varchar(255) COLLATE utf8mb4_unicode_ci NULL,
            `reason` text COLLATE utf8mb4_unicode_ci NULL,
            `metadata` json NULL,
            `approved_by_user_id` varchar(255) COLLATE utf8mb4_unicode_ci NULL,
            `approved_at` datetime NULL,
            `approval_notes` text COLLATE utf8mb4_unicode_ci NULL,
            `created_at` timestamp NULL,
            `updated_at` timestamp NULL,
            
            KEY `cms_block_revisions_cms_page_block_id_index` (`cms_page_block_id`),
            KEY `cms_block_revisions_revision_type_index` (`revision_type`),
            KEY `cms_block_revisions_user_id_index` (`user_id`),
            KEY `cms_block_revisions_cms_page_block_id_revision_type_index` (`cms_page_block_id`, `revision_type`),
            KEY `cms_block_revisions_cms_page_block_id_created_at_index` (`cms_page_block_id`, `created_at`),
            
            CONSTRAINT `cms_block_revisions_cms_page_block_id_foreign` 
                FOREIGN KEY (`cms_page_block_id`) 
                REFERENCES `cms_page_blocks` (`id`) 
                ON DELETE CASCADE,
            CONSTRAINT `cms_block_revisions_cms_block_version_id_foreign`
                FOREIGN KEY (`cms_block_version_id`)
                REFERENCES `cms_block_versions` (`id`)
                ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "✅ Created cms_block_revisions table\n";
    
    echo "\n✅ All publishing workflow tables created successfully!\n";
    echo "📊 Tables created:\n";
    echo "  ✓ cms_block_versions\n";
    echo "  ✓ cms_block_publishes\n";
    echo "  ✓ cms_block_revisions\n";
    
} catch (PDOException $e) {
    echo "❌ Database error: " . $e->getMessage() . "\n";
    exit(1);
}
