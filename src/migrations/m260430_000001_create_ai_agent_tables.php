<?php

use yii\db\Migration;

class m260430_000001_create_ai_agent_tables extends Migration
{
    public function safeUp(): void
    {
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
        $this->createIndex('idx-ai_agent_conversation-status', '{{%ai_agent_conversation}}', 'status');
        $this->createIndex('idx-ai_agent_conversation-created_by', '{{%ai_agent_conversation}}', 'created_by');
        $this->createIndex('idx-ai_agent_conversation-last_message_at', '{{%ai_agent_conversation}}', 'last_message_at');
        $this->createIndex('idx-ai_agent_conversation-last_response_id', '{{%ai_agent_conversation}}', 'last_response_id');

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
        $this->createIndex('idx-ai_agent_context-conversation_id', '{{%ai_agent_context}}', 'conversation_id');
        $this->createIndex('idx-ai_agent_context-type', '{{%ai_agent_context}}', 'type');
        $this->createIndex('idx-ai_agent_context-status', '{{%ai_agent_context}}', 'status');
        $this->addForeignKey('fk-ai_agent_context-conversation', '{{%ai_agent_context}}', 'conversation_id', '{{%ai_agent_conversation}}', 'id', 'CASCADE', 'CASCADE');

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
        $this->createIndex('idx-ai_agent_message-conversation_id', '{{%ai_agent_message}}', 'conversation_id');
        $this->createIndex('idx-ai_agent_message-response_id', '{{%ai_agent_message}}', 'response_id');
        $this->createIndex('idx-ai_agent_message-tool_call_id', '{{%ai_agent_message}}', 'tool_call_id');
        $this->createIndex('idx-ai_agent_message-tool_name', '{{%ai_agent_message}}', 'tool_name');
        $this->createIndex('idx-ai_agent_message-message_type', '{{%ai_agent_message}}', 'message_type');
        $this->addForeignKey('fk-ai_agent_message-conversation', '{{%ai_agent_message}}', 'conversation_id', '{{%ai_agent_conversation}}', 'id', 'CASCADE', 'CASCADE');

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
        $this->createIndex('idx-ai_agent_tool_snapshot-conversation_id', '{{%ai_agent_tool_snapshot}}', 'conversation_id');
        $this->createIndex('idx-ai_agent_tool_snapshot-response_id', '{{%ai_agent_tool_snapshot}}', 'response_id');
        $this->createIndex('idx-ai_agent_tool_snapshot-tool_name', '{{%ai_agent_tool_snapshot}}', 'tool_name');
        $this->createIndex('idx-ai_agent_tool_snapshot-provider_id', '{{%ai_agent_tool_snapshot}}', 'provider_id');
        $this->addForeignKey('fk-ai_agent_tool_snapshot-conversation', '{{%ai_agent_tool_snapshot}}', 'conversation_id', '{{%ai_agent_conversation}}', 'id', 'CASCADE', 'CASCADE');
    }

    public function safeDown(): void
    {
        $this->dropForeignKey('fk-ai_agent_tool_snapshot-conversation', '{{%ai_agent_tool_snapshot}}');
        $this->dropForeignKey('fk-ai_agent_message-conversation', '{{%ai_agent_message}}');
        $this->dropForeignKey('fk-ai_agent_context-conversation', '{{%ai_agent_context}}');
        $this->dropTable('{{%ai_agent_tool_snapshot}}');
        $this->dropTable('{{%ai_agent_message}}');
        $this->dropTable('{{%ai_agent_context}}');
        $this->dropTable('{{%ai_agent_conversation}}');
    }
}
