<?php

namespace eseperio\aiagent\models;

use eseperio\aiagent\Module;
use yii\behaviors\TimestampBehavior;
use yii\db\ActiveRecord;
use yii\helpers\Json;
use yii\helpers\Url;

class Asset extends ActiveRecord
{
    public const TYPE_IMAGE = 'image';
    public const TYPE_FILE = 'file';

    public const SOURCE_UPLOAD = 'upload';
    public const SOURCE_GENERATED = 'generated';

    public const STATUS_READY = 'ready';
    public const STATUS_FAILED = 'failed';

    public static function tableName(): string
    {
        return '{{%ai_agent_asset}}';
    }

    public function behaviors(): array
    {
        return [TimestampBehavior::class];
    }

    public function rules(): array
    {
        return [
            [['type', 'source', 'mime_type', 'filename', 'storage_path', 'public_token'], 'required'],
            [['size', 'width', 'height', 'created_at', 'updated_at'], 'integer'],
            [['metadata'], 'string'],
            [['type', 'source', 'status'], 'string', 'max' => 20],
            [['mime_type'], 'string', 'max' => 128],
            [['filename'], 'string', 'max' => 255],
            [['storage_path'], 'string', 'max' => 512],
            [['public_token', 'hash'], 'string', 'max' => 64],
            [['type'], 'in', 'range' => [self::TYPE_IMAGE, self::TYPE_FILE]],
            [['source'], 'in', 'range' => [self::SOURCE_UPLOAD, self::SOURCE_GENERATED]],
            [['status'], 'in', 'range' => [self::STATUS_READY, self::STATUS_FAILED]],
            [['status'], 'default', 'value' => self::STATUS_READY],
        ];
    }

    public function getMessageLinks()
    {
        return $this->hasMany(MessageAsset::class, ['asset_id' => 'id']);
    }

    public function getMetadataArray(): array
    {
        if (empty($this->metadata)) {
            return [];
        }

        $metadata = Json::decode($this->metadata, true);
        return is_array($metadata) ? $metadata : [];
    }

    public function setMetadataArray(array $metadata): void
    {
        $this->metadata = $metadata === [] ? null : Json::encode($metadata);
    }

    public function getUrl(): string
    {
        $moduleId = Module::resolveActive()?->id ?: 'aiAgent';
        return Url::to([
            '/' . $moduleId . '/chat/asset',
            'id' => $this->id,
            'token' => $this->public_token,
        ], true);
    }

    public function toDisplayArray(): array
    {
        return [
            'id' => (int)$this->id,
            'type' => $this->type,
            'source' => $this->source,
            'status' => $this->status,
            'mime_type' => $this->mime_type,
            'filename' => $this->filename,
            'size' => $this->size === null ? null : (int)$this->size,
            'width' => $this->width === null ? null : (int)$this->width,
            'height' => $this->height === null ? null : (int)$this->height,
            'url' => $this->getUrl(),
            'preview_url' => $this->type === self::TYPE_IMAGE ? $this->getUrl() : null,
            'metadata' => $this->getMetadataArray(),
            'created_at' => (int)$this->created_at,
        ];
    }

    public static function generateToken(): string
    {
        return \Yii::$app->security->generateRandomString(48);
    }
}
