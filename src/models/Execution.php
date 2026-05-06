<?php

namespace eseperio\aiagent\models;

use yii\behaviors\TimestampBehavior;
use yii\db\ActiveRecord;

class Execution extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%ai_agent_execution}}';
    }

    public function behaviors(): array
    {
        return [
            TimestampBehavior::class,
        ];
    }
}
