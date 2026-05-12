<?php

use yii\db\Migration;

class m260430_000001_create_ai_agent_tables extends Migration
{
    public function safeUp(): void
    {
        if (!$this->tableExists('{{%ai_agent_conversation}}')) {
            $this->createTable('{{%ai_agent_conversation}}', [
                'id' => $this->primaryKey(),
                'title' => $this->string(255)->null(),
                'status' => $this->string(20)->notNull()->defaultValue('active'),
                'model' => $this->string(100)->null(),
                'created_by' => $this->string(64)->null(),
                'metadata' => $this->text()->null(),
                'last_response_id' => $this->string(255)->null(),
                'last_message_at' => $this->integer()->null(),
                'input_tokens_total' => $this->integer()->notNull()->defaultValue(0),
                'output_tokens_total' => $this->integer()->notNull()->defaultValue(0),
                'total_tokens_total' => $this->integer()->notNull()->defaultValue(0),
                'created_at' => $this->integer()->notNull(),
                'updated_at' => $this->integer()->notNull(),
            ]);
        }
        $this->createIndexIfMissing('idx-ai_agent_conversation-status', '{{%ai_agent_conversation}}', 'status');
        $this->createIndexIfMissing('idx-ai_agent_conversation-created_by', '{{%ai_agent_conversation}}', 'created_by');
        $this->createIndexIfMissing('idx-ai_agent_conversation-last_message_at', '{{%ai_agent_conversation}}', 'last_message_at');
        $this->createIndexIfMissing('idx-ai_agent_conversation-last_response_id', '{{%ai_agent_conversation}}', 'last_response_id');

        if (!$this->tableExists('{{%ai_agent_context}}')) {
            $this->createTable('{{%ai_agent_context}}', [
                'id' => $this->primaryKey(),
                'conversation_id' => $this->integer()->notNull(),
                'type' => $this->integer()->notNull(),
                'metadata' => $this->text()->notNull(),
                'label' => $this->string(255)->null(),
                'status' => $this->string(20)->notNull()->defaultValue('active'),
                'sort_order' => $this->integer()->notNull()->defaultValue(0),
                'created_at' => $this->integer()->notNull(),
                'updated_at' => $this->integer()->notNull(),
            ]);
        }
        $this->createIndexIfMissing('idx-ai_agent_context-conversation_id', '{{%ai_agent_context}}', 'conversation_id');
        $this->createIndexIfMissing('idx-ai_agent_context-type', '{{%ai_agent_context}}', 'type');
        $this->createIndexIfMissing('idx-ai_agent_context-status', '{{%ai_agent_context}}', 'status');
        $this->addForeignKeyIfMissing('fk-ai_agent_context-conversation', '{{%ai_agent_context}}', 'conversation_id', '{{%ai_agent_conversation}}', 'id', 'CASCADE');

        if (!$this->tableExists('{{%ai_agent_message}}')) {
            $this->createTable('{{%ai_agent_message}}', [
                'id' => $this->primaryKey(),
                'conversation_id' => $this->integer()->notNull(),
                'role' => $this->string(20)->notNull(),
                'message_type' => $this->string(30)->notNull()->defaultValue('message'),
                'content' => $this->text()->notNull(),
                'response_id' => $this->string(255)->null(),
                'tool_call_id' => $this->string(255)->null(),
                'tool_name' => $this->string(128)->null(),
                'tool_payload' => $this->text()->null(),
                'input_tokens' => $this->integer()->notNull()->defaultValue(0),
                'output_tokens' => $this->integer()->notNull()->defaultValue(0),
                'total_tokens' => $this->integer()->notNull()->defaultValue(0),
                'created_at' => $this->integer()->notNull(),
                'updated_at' => $this->integer()->notNull(),
            ]);
        }
        $this->createIndexIfMissing('idx-ai_agent_message-conversation_id', '{{%ai_agent_message}}', 'conversation_id');
        $this->createIndexIfMissing('idx-ai_agent_message-response_id', '{{%ai_agent_message}}', 'response_id');
        $this->createIndexIfMissing('idx-ai_agent_message-tool_call_id', '{{%ai_agent_message}}', 'tool_call_id');
        $this->createIndexIfMissing('idx-ai_agent_message-tool_name', '{{%ai_agent_message}}', 'tool_name');
        $this->createIndexIfMissing('idx-ai_agent_message-message_type', '{{%ai_agent_message}}', 'message_type');
        $this->addForeignKeyIfMissing('fk-ai_agent_message-conversation', '{{%ai_agent_message}}', 'conversation_id', '{{%ai_agent_conversation}}', 'id', 'CASCADE');

        if (!$this->tableExists('{{%ai_agent_tool_snapshot}}')) {
            $this->createTable('{{%ai_agent_tool_snapshot}}', [
                'id' => $this->primaryKey(),
                'conversation_id' => $this->integer()->notNull(),
                'response_id' => $this->string(255)->null(),
                'tool_name' => $this->string(128)->notNull(),
                'provider_id' => $this->string(128)->null(),
                'context_fingerprint' => $this->string(64)->null(),
                'definition_json' => $this->text()->notNull(),
                'created_at' => $this->integer()->notNull(),
                'updated_at' => $this->integer()->notNull(),
            ]);
        }
        $this->createIndexIfMissing('idx-ai_agent_tool_snapshot-conversation_id', '{{%ai_agent_tool_snapshot}}', 'conversation_id');
        $this->createIndexIfMissing('idx-ai_agent_tool_snapshot-response_id', '{{%ai_agent_tool_snapshot}}', 'response_id');
        $this->createIndexIfMissing('idx-ai_agent_tool_snapshot-tool_name', '{{%ai_agent_tool_snapshot}}', 'tool_name');
        $this->createIndexIfMissing('idx-ai_agent_tool_snapshot-provider_id', '{{%ai_agent_tool_snapshot}}', 'provider_id');
        $this->addForeignKeyIfMissing('fk-ai_agent_tool_snapshot-conversation', '{{%ai_agent_tool_snapshot}}', 'conversation_id', '{{%ai_agent_conversation}}', 'id', 'CASCADE');

        if (!$this->tableExists('{{%ai_agent_execution}}')) {
            $this->createTable('{{%ai_agent_execution}}', [
                'id' => $this->primaryKey(),
                'conversation_id' => $this->integer()->null(),
                'snapshot_id' => $this->integer()->null(),
                'response_id' => $this->string(255)->null(),
                'tool_call_id' => $this->string(255)->null(),
                'tool_name' => $this->string(128)->notNull(),
                'actor_id' => $this->string(64)->null(),
                'status' => $this->string(30)->notNull()->defaultValue('running'),
                'effect' => $this->string(30)->notNull()->defaultValue('write'),
                'risk_level' => $this->string(30)->notNull()->defaultValue('medium'),
                'resource_type' => $this->string(128)->null(),
                'resource_id' => $this->string(128)->null(),
                'idempotency_key' => $this->string(128)->null(),
                'expected_version' => $this->string(64)->null(),
                'observed_version' => $this->string(64)->null(),
                'arguments_json' => $this->text()->null(),
                'result_json' => $this->text()->null(),
                'error_json' => $this->text()->null(),
                'started_at' => $this->integer()->null(),
                'finished_at' => $this->integer()->null(),
                'created_at' => $this->integer()->notNull(),
                'updated_at' => $this->integer()->notNull(),
            ]);
        }
        $this->createIndexIfMissing('idx-ai_agent_execution-conversation_id', '{{%ai_agent_execution}}', 'conversation_id');
        $this->createIndexIfMissing('idx-ai_agent_execution-snapshot_id', '{{%ai_agent_execution}}', 'snapshot_id');
        $this->createIndexIfMissing('idx-ai_agent_execution-tool_name', '{{%ai_agent_execution}}', 'tool_name');
        $this->createIndexIfMissing('idx-ai_agent_execution-status', '{{%ai_agent_execution}}', 'status');
        $this->createIndexIfMissing('idx-ai_agent_execution-idempotency_key', '{{%ai_agent_execution}}', 'idempotency_key');
        $this->addForeignKeyIfMissing('fk-ai_agent_execution-conversation', '{{%ai_agent_execution}}', 'conversation_id', '{{%ai_agent_conversation}}', 'id', 'SET NULL');
        $this->addForeignKeyIfMissing('fk-ai_agent_execution-snapshot', '{{%ai_agent_execution}}', 'snapshot_id', '{{%ai_agent_tool_snapshot}}', 'id', 'SET NULL');
    }

    public function safeDown(): void
    {
        if ($this->tableExists('{{%ai_agent_execution}}')) {
            $this->dropForeignKeyIfExists('fk-ai_agent_execution-snapshot', '{{%ai_agent_execution}}');
            $this->dropForeignKeyIfExists('fk-ai_agent_execution-conversation', '{{%ai_agent_execution}}');
            $this->dropTable('{{%ai_agent_execution}}');
        }
        if ($this->tableExists('{{%ai_agent_tool_snapshot}}')) {
            $this->dropForeignKeyIfExists('fk-ai_agent_tool_snapshot-conversation', '{{%ai_agent_tool_snapshot}}');
            $this->dropTable('{{%ai_agent_tool_snapshot}}');
        }
        if ($this->tableExists('{{%ai_agent_message}}')) {
            $this->dropForeignKeyIfExists('fk-ai_agent_message-conversation', '{{%ai_agent_message}}');
            $this->dropTable('{{%ai_agent_message}}');
        }
        if ($this->tableExists('{{%ai_agent_context}}')) {
            $this->dropForeignKeyIfExists('fk-ai_agent_context-conversation', '{{%ai_agent_context}}');
            $this->dropTable('{{%ai_agent_context}}');
        }
        if ($this->tableExists('{{%ai_agent_conversation}}')) {
            $this->dropTable('{{%ai_agent_conversation}}');
        }
    }

    private function tableExists(string $table): bool
    {
        return $this->db->schema->getTableSchema($table, true) !== null;
    }

    private function createIndexIfMissing(string $name, string $table, string|array $columns, bool $unique = false): void
    {
        if (!$this->indexExists($table, $name)) {
            $this->createIndex($name, $table, $columns, $unique);
        }
    }

    private function addForeignKeyIfMissing(string $name, string $table, string $column, string $refTable, string $refColumn, string $delete = 'CASCADE'): void
    {
        if (!$this->foreignKeyExists($table, $name)) {
            $this->addForeignKey($name, $table, $column, $refTable, $refColumn, $delete, 'CASCADE');
        }
    }

    private function dropForeignKeyIfExists(string $name, string $table): void
    {
        if ($this->foreignKeyExists($table, $name)) {
            $this->dropForeignKey($name, $table);
        }
    }

    private function indexExists(string $table, string $indexName): bool
    {
        return (bool)$this->db->createCommand(
            'SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table AND INDEX_NAME = :index',
            [
                ':table' => $this->rawTableName($table),
                ':index' => $indexName,
            ]
        )->queryScalar();
    }

    private function foreignKeyExists(string $table, string $constraintName): bool
    {
        return (bool)$this->db->createCommand(
            'SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = :table AND CONSTRAINT_NAME = :constraint AND CONSTRAINT_TYPE = :type',
            [
                ':table' => $this->rawTableName($table),
                ':constraint' => $constraintName,
                ':type' => 'FOREIGN KEY',
            ]
        )->queryScalar();
    }

    private function rawTableName(string $table): string
    {
        return str_replace(['{{%', '}}'], [$this->db->tablePrefix, ''], $table);
    }
}
