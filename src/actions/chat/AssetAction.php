<?php

namespace eseperio\aiagent\actions\chat;

use yii\web\NotFoundHttpException;
use yii\web\Response;

class AssetAction extends BaseChatAction
{
    public function run(int $id, string $token)
    {
        if (!$this->can('canViewHistory', $this->permissionContext('asset', ['asset_id' => $id]))) {
            return $this->deny();
        }

        $class = $this->module()->assetClass;
        $asset = $class::findOne(['id' => $id, 'public_token' => $token]);
        if (!$asset) {
            throw new NotFoundHttpException('Asset not found');
        }

        $contents = $this->module()->getAssetService()->read($asset);
        \Yii::$app->response->format = Response::FORMAT_RAW;
        return \Yii::$app->response->sendContentAsFile($contents, $asset->filename, [
            'mimeType' => $asset->mime_type,
            'inline' => true,
        ]);
    }
}
