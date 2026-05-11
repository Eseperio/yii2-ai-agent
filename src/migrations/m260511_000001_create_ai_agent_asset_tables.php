<?php

use yii\db\Migration;

class m260511_000001_create_ai_agent_asset_tables extends Migration
{
    public function safeUp(): void
    {
        $this->createTable('{{%ai_agent_asset}}', [
            'id' => $this->primaryKey(),
            'type' => $this->string(20)->notNull(),
            'source' => $this->string(20)->notNull(),
            'status' => $this->string(20)->notNull()->defaultValue('ready'),
            'mime_type' => $this->string(128)->notNull(),
            'filename' => $this->string(255)->notNull(),
            'storage_path' => $this->string(512)->notNull(),
            'public_token' => $this->string(64)->notNull(),
            'size' => $this->integer()->null(),
            'width' => $this->integer()->null(),
            'height' => $this->integer()->null(),
            'hash' => $this->string(64)->null(),
            'metadata' => $this->text()->null(),
            'created_at' => $this->integer()->notNull(),
            'updated_at' => $this->integer()->notNull(),
        ]);
        $this->createIndex('idx-ai_agent_asset-token', '{{%ai_agent_asset}}', 'public_token', true);
        $this->createIndex('idx-ai_agent_asset-type-source', '{{%ai_agent_asset}}', ['type', 'source']);
        $this->createIndex('idx-ai_agent_asset-hash', '{{%ai_agent_asset}}', 'hash');

        $this->createTable('{{%ai_agent_message_asset}}', [
            'id' => $this->primaryKey(),
            'message_id' => $this->integer()->notNull(),
            'asset_id' => $this->integer()->notNull(),
            'usage' => $this->string(20)->notNull()->defaultValue('input'),
            'created_at' => $this->integer()->notNull(),
            'updated_at' => $this->integer()->notNull(),
        ]);
        $this->createIndex('idx-ai_agent_message_asset-message', '{{%ai_agent_message_asset}}', 'message_id');
        $this->createIndex('idx-ai_agent_message_asset-asset', '{{%ai_agent_message_asset}}', 'asset_id');
        $this->createIndex('idx-ai_agent_message_asset-unique', '{{%ai_agent_message_asset}}', ['message_id', 'asset_id', 'usage'], true);
        $this->addForeignKey('fk-ai_agent_message_asset-message', '{{%ai_agent_message_asset}}', 'message_id', '{{%ai_agent_message}}', 'id', 'CASCADE', 'CASCADE');
        $this->addForeignKey('fk-ai_agent_message_asset-asset', '{{%ai_agent_message_asset}}', 'asset_id', '{{%ai_agent_asset}}', 'id', 'CASCADE', 'CASCADE');

        $this->createTable('{{%ai_agent_asset_target}}', [
            'id' => $this->primaryKey(),
            'asset_id' => $this->integer()->notNull(),
            'target_type' => $this->string(128)->notNull(),
            'target_id' => $this->string(128)->notNull(),
            'handler' => $this->string(128)->null(),
            'result' => $this->text()->null(),
            'created_at' => $this->integer()->notNull(),
            'updated_at' => $this->integer()->notNull(),
        ]);
        $this->createIndex('idx-ai_agent_asset_target-asset', '{{%ai_agent_asset_target}}', 'asset_id');
        $this->createIndex('idx-ai_agent_asset_target-target', '{{%ai_agent_asset_target}}', ['target_type', 'target_id']);
        $this->addForeignKey('fk-ai_agent_asset_target-asset', '{{%ai_agent_asset_target}}', 'asset_id', '{{%ai_agent_asset}}', 'id', 'CASCADE', 'CASCADE');
    }

    public function safeDown(): void
    {
        $this->dropForeignKey('fk-ai_agent_asset_target-asset', '{{%ai_agent_asset_target}}');
        $this->dropTable('{{%ai_agent_asset_target}}');
        $this->dropForeignKey('fk-ai_agent_message_asset-asset', '{{%ai_agent_message_asset}}');
        $this->dropForeignKey('fk-ai_agent_message_asset-message', '{{%ai_agent_message_asset}}');
        $this->dropTable('{{%ai_agent_message_asset}}');
        $this->dropTable('{{%ai_agent_asset}}');
    }
}
