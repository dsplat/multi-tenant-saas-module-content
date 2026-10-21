<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use MultiTenantSaas\Support\MySqlOnlyMigration;

return new class extends Migration
{
    public function up(): void
    {
        if (MySqlOnlyMigration::skip(__FILE__)) {
            return;
        }

        // 提升归位前由 scrm 同名迁移建表；存量库按 basename 判重不会重跑，
        // 此守卫只防「半途失败后重试」的重复建表。
        if (Schema::hasTable('materials')) {
            return;
        }

        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        // Table: material_events
        DB::statement(<<<'SQL'
CREATE TABLE `material_events` (
  `event_id` bigint unsigned NOT NULL,
  `tenant_id` bigint unsigned NOT NULL,
  `material_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  `event_type` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `duration` int unsigned NOT NULL DEFAULT '0',
  `occurred_at` timestamp NULL DEFAULT NULL,
  `metadata` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`event_id`),
  KEY `material_events_tenant_id_material_id_index` (`tenant_id`,`material_id`),
  KEY `material_events_tenant_id_user_id_index` (`tenant_id`,`user_id`),
  KEY `material_events_tenant_id_event_type_index` (`tenant_id`,`event_type`),
  KEY `material_events_tenant_id_occurred_at_index` (`tenant_id`,`occurred_at`),
  KEY `material_events_material_id_foreign` (`material_id`),
  CONSTRAINT `material_events_material_id_foreign` FOREIGN KEY (`material_id`) REFERENCES `materials` (`material_id`) ON DELETE CASCADE,
  CONSTRAINT `material_events_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`tenant_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        // Table: materials
        DB::statement(<<<'SQL'
CREATE TABLE `materials` (
  `material_id` bigint unsigned NOT NULL,
  `tenant_id` bigint unsigned NOT NULL,
  `title` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `content` text COLLATE utf8mb4_unicode_ci,
  `file_url` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `file_size` bigint NOT NULL DEFAULT '0',
  `mime_type` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `category` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `entity_type` varchar(32) DEFAULT NULL,
  `entity_id` varchar(32) DEFAULT NULL,
  PRIMARY KEY (`material_id`),
  KEY `materials_tenant_id_type_index` (`tenant_id`,`type`),
  KEY `materials_tenant_id_category_index` (`tenant_id`,`category`),
  KEY `materials_tenant_entity_index` (`tenant_id`,`entity_type`,`entity_id`),
  CONSTRAINT `materials_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`tenant_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        // Table: scripts
        DB::statement(<<<'SQL'
CREATE TABLE `scripts` (
  `script_id` bigint unsigned NOT NULL,
  `tenant_id` bigint unsigned NOT NULL,
  `title` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `category` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `content` text COLLATE utf8mb4_unicode_ci,
  `tags` json DEFAULT NULL,
  `variables` json DEFAULT NULL,
  `usage_count` int NOT NULL DEFAULT '0',
  `status` varchar(20) NOT NULL DEFAULT 'draft',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `entity_type` varchar(32) DEFAULT NULL,
  `entity_id` varchar(32) DEFAULT NULL,
  PRIMARY KEY (`script_id`),
  KEY `scripts_tenant_id_category_index` (`tenant_id`,`category`),
  KEY `scripts_tenant_entity_index` (`tenant_id`,`entity_type`,`entity_id`),
  KEY `scripts_tenant_status_index` (`tenant_id`,`status`),
  CONSTRAINT `scripts_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`tenant_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        // Table: sensitive_words
        DB::statement(<<<'SQL'
CREATE TABLE `sensitive_words` (
  `sensitive_word_id` bigint unsigned NOT NULL,
  `tenant_id` bigint unsigned NOT NULL,
  `word` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `category` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'general',
  `severity` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'medium',
  `is_regex` tinyint(1) NOT NULL DEFAULT '0',
  `status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`sensitive_word_id`),
  KEY `sensitive_words_tenant_id_status_index` (`tenant_id`,`status`),
  KEY `sensitive_words_tenant_id_category_index` (`tenant_id`,`category`),
  KEY `sensitive_words_tenant_id_severity_index` (`tenant_id`,`severity`),
  KEY `sensitive_words_tenant_id_word_index` (`tenant_id`,`word`),
  CONSTRAINT `sensitive_words_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`tenant_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        // Table: inspection_rules - 质检规则
        DB::statement(<<<'SQL'
CREATE TABLE `inspection_rules` (
  `inspection_rule_id` bigint unsigned NOT NULL,
  `tenant_id` bigint unsigned NOT NULL,
  `name` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'sensitive_word' COMMENT 'sensitive_word/over_promise/compliance/custom',
  `conditions` json DEFAULT NULL COMMENT '匹配条件',
  `action` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'warn' COMMENT 'warn/block/notify',
  `severity` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'medium' COMMENT 'low/medium/high/critical',
  `priority` int NOT NULL DEFAULT '0' COMMENT '优先级，越大越先执行',
  `status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`inspection_rule_id`),
  KEY `inspection_rules_tenant_id_status_index` (`tenant_id`,`status`),
  KEY `inspection_rules_tenant_id_type_index` (`tenant_id`,`type`),
  KEY `inspection_rules_tenant_id_severity_index` (`tenant_id`,`severity`),
  CONSTRAINT `inspection_rules_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`tenant_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        // Table: inspection_results - 质检结果
        DB::statement(<<<'SQL'
CREATE TABLE `inspection_results` (
  `inspection_result_id` bigint unsigned NOT NULL,
  `tenant_id` bigint unsigned NOT NULL,
  `source_type` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'chat_message/mass_push/moment',
  `source_id` bigint unsigned NOT NULL COMMENT '关联消息ID',
  `rule_id` bigint unsigned DEFAULT NULL,
  `matched_content` text COLLATE utf8mb4_unicode_ci COMMENT '命中的内容片段',
  `severity` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'medium',
  `action_taken` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'warn/block/notify',
  `reviewed_by` bigint unsigned DEFAULT NULL,
  `reviewed_at` timestamp NULL DEFAULT NULL,
  `status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending' COMMENT 'pending/confirmed/dismissed',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`inspection_result_id`),
  KEY `inspection_results_tenant_id_status_index` (`tenant_id`,`status`),
  KEY `inspection_results_tenant_id_source_index` (`tenant_id`,`source_type`,`source_id`),
  KEY `inspection_results_tenant_id_severity_index` (`tenant_id`,`severity`),
  KEY `inspection_results_tenant_id_created_at_index` (`tenant_id`,`created_at`),
  CONSTRAINT `inspection_results_rule_id_foreign` FOREIGN KEY (`rule_id`) REFERENCES `inspection_rules` (`inspection_rule_id`) ON DELETE SET NULL,
  CONSTRAINT `inspection_results_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`tenant_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        // Table: inspection_alerts - 预警通知
        DB::statement(<<<'SQL'
CREATE TABLE `inspection_alerts` (
  `alert_id` bigint unsigned NOT NULL,
  `tenant_id` bigint unsigned NOT NULL,
  `inspection_result_id` bigint unsigned NOT NULL,
  `notify_channel` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'system' COMMENT 'system/sms/webhook',
  `notify_to` json DEFAULT NULL COMMENT '通知对象列表',
  `notified_at` timestamp NULL DEFAULT NULL,
  `status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending' COMMENT 'pending/sent/handled',
  `handled_by` bigint unsigned DEFAULT NULL,
  `handled_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`alert_id`),
  KEY `inspection_alerts_tenant_id_status_index` (`tenant_id`,`status`),
  KEY `inspection_alerts_tenant_id_created_at_index` (`tenant_id`,`created_at`),
  CONSTRAINT `inspection_alerts_result_id_foreign` FOREIGN KEY (`inspection_result_id`) REFERENCES `inspection_results` (`inspection_result_id`) ON DELETE CASCADE,
  CONSTRAINT `inspection_alerts_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`tenant_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    public function down(): void
    {
        if (MySqlOnlyMigration::skip(__FILE__)) {
            return;
        }

        Schema::dropIfExists('inspection_alerts');
        Schema::dropIfExists('inspection_results');
        Schema::dropIfExists('inspection_rules');
        Schema::dropIfExists('material_events');
        Schema::dropIfExists('materials');
        Schema::dropIfExists('scripts');
        Schema::dropIfExists('sensitive_words');
    }
};
