<?php

namespace eseperio\aiagent\tests;

use yii\web\IdentityInterface;

class DummyIdentity implements IdentityInterface
{
    public int|string $id = 1;

    public static function findIdentity($id): ?self
    {
        return new self();
    }

    public static function findIdentityByAccessToken($token, $type = null): ?self
    {
        return new self();
    }

    public function getId(): int|string
    {
        return $this->id;
    }

    public function getAuthKey(): ?string
    {
        return 'test';
    }

    public function validateAuthKey($authKey): bool
    {
        return true;
    }
}
