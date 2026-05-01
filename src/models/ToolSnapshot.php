<?php

namespace eseperio\aiagent\models;

use yii\db\ActiveRecord;
use yii\behaviors\TimestampBehavior;

class ToolSnapshot extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%ai_agent_tool_snapshot}}';
    }

    public function behaviors(): array
    {
        return [TimestampBehavior::class];
    }
}
