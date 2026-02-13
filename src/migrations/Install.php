<?php

namespace justinholtweb\garrison\migrations;

use craft\db\Migration;

class Install extends Migration
{
    public function safeUp(): bool
    {
        $this->createTables();
        $this->createIndexes();
        $this->addForeignKeys();

        return true;
    }

    public function safeDown(): bool
    {
        $this->dropTableIfExists('{{%garrison_blocked_requests}}');
        $this->dropTableIfExists('{{%garrison_login_attempts}}');
        $this->dropTableIfExists('{{%garrison_access_rules}}');
        $this->dropTableIfExists('{{%garrison_file_baselines}}');
        $this->dropTableIfExists('{{%garrison_audit_log}}');
        $this->dropTableIfExists('{{%garrison_scan_results}}');
        $this->dropTableIfExists('{{%garrison_scans}}');

        return true;
    }

    private function createTables(): void
    {
        // Scans
        $this->createTable('{{%garrison_scans}}', [
            'id' => $this->primaryKey(),
            'siteId' => $this->integer()->notNull(),
            'status' => $this->string(20)->notNull()->defaultValue('passed'),
            'riskScore' => $this->integer()->notNull()->defaultValue(0),
            'totalChecks' => $this->integer()->notNull()->defaultValue(0),
            'passedChecks' => $this->integer()->notNull()->defaultValue(0),
            'warningChecks' => $this->integer()->notNull()->defaultValue(0),
            'failedChecks' => $this->integer()->notNull()->defaultValue(0),
            'criticalChecks' => $this->integer()->notNull()->defaultValue(0),
            'duration' => $this->float()->notNull()->defaultValue(0),
            'triggeredBy' => $this->string(50)->notNull()->defaultValue('manual'),
            'userId' => $this->integer()->null(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        // Scan Results
        $this->createTable('{{%garrison_scan_results}}', [
            'id' => $this->primaryKey(),
            'scanId' => $this->integer()->notNull(),
            'checkHandle' => $this->string(100)->notNull(),
            'checkName' => $this->string(255)->notNull(),
            'category' => $this->string(50)->notNull(),
            'status' => $this->string(20)->notNull(),
            'severity' => $this->string(20)->notNull(),
            'message' => $this->text()->null(),
            'details' => $this->json()->null(),
            'remediation' => $this->text()->null(),
            'dateCreated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        // Audit Log
        $this->createTable('{{%garrison_audit_log}}', [
            'id' => $this->primaryKey(),
            'siteId' => $this->integer()->null(),
            'userId' => $this->integer()->null(),
            'userName' => $this->string(255)->null(),
            'action' => $this->string(50)->notNull(),
            'category' => $this->string(50)->notNull(),
            'targetType' => $this->string(255)->null(),
            'targetId' => $this->integer()->null(),
            'targetTitle' => $this->string(255)->null(),
            'details' => $this->json()->null(),
            'ipAddress' => $this->string(45)->null(),
            'userAgent' => $this->string(500)->null(),
            'dateCreated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        // File Baselines
        $this->createTable('{{%garrison_file_baselines}}', [
            'id' => $this->primaryKey(),
            'filePath' => $this->string(1024)->notNull(),
            'fileHash' => $this->string(64)->notNull(),
            'fileSize' => $this->bigInteger()->notNull(),
            'filePermissions' => $this->string(10)->notNull(),
            'status' => $this->string(20)->notNull()->defaultValue('ok'),
            'lastChecked' => $this->dateTime()->null(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        // Access Rules
        $this->createTable('{{%garrison_access_rules}}', [
            'id' => $this->primaryKey(),
            'type' => $this->string(20)->notNull()->defaultValue('block'),
            'scope' => $this->string(20)->notNull()->defaultValue('all'),
            'ipPattern' => $this->string(100)->notNull(),
            'countryCode' => $this->char(2)->null(),
            'label' => $this->string(255)->null(),
            'enabled' => $this->boolean()->notNull()->defaultValue(true),
            'expiresAt' => $this->dateTime()->null(),
            'createdBy' => $this->integer()->null(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        // Blocked Requests
        $this->createTable('{{%garrison_blocked_requests}}', [
            'id' => $this->primaryKey(),
            'ipAddress' => $this->string(45)->notNull(),
            'reason' => $this->string(50)->notNull(),
            'details' => $this->json()->null(),
            'requestUri' => $this->string(2048)->null(),
            'requestMethod' => $this->string(10)->null(),
            'userAgent' => $this->string(500)->null(),
            'countryCode' => $this->char(2)->null(),
            'dateCreated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        // Login Attempts
        $this->createTable('{{%garrison_login_attempts}}', [
            'id' => $this->primaryKey(),
            'ipAddress' => $this->string(45)->notNull(),
            'username' => $this->string(255)->null(),
            'successful' => $this->boolean()->notNull()->defaultValue(false),
            'dateCreated' => $this->dateTime()->notNull(),
        ]);
    }

    private function createIndexes(): void
    {
        // Scans
        $this->createIndex(null, '{{%garrison_scans}}', ['siteId']);
        $this->createIndex(null, '{{%garrison_scans}}', ['status']);
        $this->createIndex(null, '{{%garrison_scans}}', ['dateCreated']);

        // Scan Results
        $this->createIndex(null, '{{%garrison_scan_results}}', ['scanId']);
        $this->createIndex(null, '{{%garrison_scan_results}}', ['checkHandle']);
        $this->createIndex(null, '{{%garrison_scan_results}}', ['status']);

        // Audit Log
        $this->createIndex(null, '{{%garrison_audit_log}}', ['userId']);
        $this->createIndex(null, '{{%garrison_audit_log}}', ['action']);
        $this->createIndex(null, '{{%garrison_audit_log}}', ['category']);
        $this->createIndex(null, '{{%garrison_audit_log}}', ['dateCreated']);
        $this->createIndex(null, '{{%garrison_audit_log}}', ['ipAddress']);

        // File Baselines
        $this->createIndex(null, '{{%garrison_file_baselines}}', ['filePath'], true);
        $this->createIndex(null, '{{%garrison_file_baselines}}', ['status']);

        // Access Rules
        $this->createIndex(null, '{{%garrison_access_rules}}', ['type']);
        $this->createIndex(null, '{{%garrison_access_rules}}', ['enabled']);
        $this->createIndex(null, '{{%garrison_access_rules}}', ['ipPattern']);

        // Blocked Requests
        $this->createIndex(null, '{{%garrison_blocked_requests}}', ['ipAddress']);
        $this->createIndex(null, '{{%garrison_blocked_requests}}', ['reason']);
        $this->createIndex(null, '{{%garrison_blocked_requests}}', ['dateCreated']);

        // Login Attempts
        $this->createIndex(null, '{{%garrison_login_attempts}}', ['ipAddress']);
        $this->createIndex(null, '{{%garrison_login_attempts}}', ['dateCreated']);
        $this->createIndex(null, '{{%garrison_login_attempts}}', ['ipAddress', 'successful']);
    }

    private function addForeignKeys(): void
    {
        // Scans → sites
        $this->addForeignKey(null, '{{%garrison_scans}}', ['siteId'], '{{%sites}}', ['id'], 'CASCADE', null);
        $this->addForeignKey(null, '{{%garrison_scans}}', ['userId'], '{{%users}}', ['id'], 'SET NULL', null);

        // Scan Results → scans
        $this->addForeignKey(null, '{{%garrison_scan_results}}', ['scanId'], '{{%garrison_scans}}', ['id'], 'CASCADE', null);

        // Audit Log → sites, users
        $this->addForeignKey(null, '{{%garrison_audit_log}}', ['siteId'], '{{%sites}}', ['id'], 'CASCADE', null);

        // Access Rules → users
        $this->addForeignKey(null, '{{%garrison_access_rules}}', ['createdBy'], '{{%users}}', ['id'], 'SET NULL', null);
    }
}
