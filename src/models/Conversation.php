<?php

namespace eseperio\aiagent\models;

use yii\db\ActiveRecord;
use yii\behaviors\TimestampBehavior;

class Conversation extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%ai_agent_conversation}}';
    }

    public function behaviors(): array
    {
        return [TimestampBehavior::class];
    }
}
