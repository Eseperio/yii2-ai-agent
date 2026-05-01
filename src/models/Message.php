<?php

namespace eseperio\aiagent\models;

use yii\db\ActiveRecord;
use yii\behaviors\TimestampBehavior;

class Message extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%ai_agent_message}}';
    }

    public function behaviors(): array
    {
        return [TimestampBehavior::class];
    }
}
