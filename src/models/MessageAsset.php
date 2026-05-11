<?php

namespace eseperio\aiagent\models;

use yii\behaviors\TimestampBehavior;
use yii\db\ActiveRecord;

class MessageAsset extends ActiveRecord
{
    public const USAGE_INPUT = 'input';
    public const USAGE_OUTPUT = 'output';
    public const USAGE_REFERENCE = 'reference';

    public static function tableName(): string
    {
        return '{{%ai_agent_message_asset}}';
    }

    public function behaviors(): array
    {
        return [TimestampBehavior::class];
    }

    public function rules(): array
    {
        return [
            [['message_id', 'asset_id'], 'required'],
            [['message_id', 'asset_id', 'created_at', 'updated_at'], 'integer'],
            [['usage'], 'string', 'max' => 20],
            [['usage'], 'default', 'value' => self::USAGE_INPUT],
            [['usage'], 'in', 'range' => [self::USAGE_INPUT, self::USAGE_OUTPUT, self::USAGE_REFERENCE]],
        ];
    }

    public function getAsset()
    {
        return $this->hasOne(Asset::class, ['id' => 'asset_id']);
    }

    public function getMessage()
    {
        return $this->hasOne(Message::class, ['id' => 'message_id']);
    }
}
