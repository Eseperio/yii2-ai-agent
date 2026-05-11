<?php

namespace eseperio\aiagent\models;

use yii\behaviors\TimestampBehavior;
use yii\db\ActiveRecord;
use yii\helpers\Json;

class AssetTarget extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%ai_agent_asset_target}}';
    }

    public function behaviors(): array
    {
        return [TimestampBehavior::class];
    }

    public function rules(): array
    {
        return [
            [['asset_id', 'target_type', 'target_id'], 'required'],
            [['asset_id', 'created_at', 'updated_at'], 'integer'],
            [['result'], 'string'],
            [['target_type', 'target_id', 'handler'], 'string', 'max' => 128],
        ];
    }

    public function setResultArray(array $result): void
    {
        $this->result = $result === [] ? null : Json::encode($result);
    }

    public function getResultArray(): array
    {
        if (!$this->result) {
            return [];
        }
        $decoded = Json::decode($this->result, true);
        return is_array($decoded) ? $decoded : [];
    }
}
