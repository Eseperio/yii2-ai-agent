<?php

namespace eseperio\aiagent\actions\chat;

use yii\base\InvalidArgumentException;
use yii\web\UploadedFile;

class UploadAssetAction extends BaseChatAction
{
    public function run()
    {
        $conversationId = (int)$this->request()->post('conversation_id', 0);
        if (!$this->can('canSendMessage', $this->permissionContext('uploadAsset', ['conversation_id' => $conversationId]))) {
            return $this->deny();
        }

        $file = UploadedFile::getInstanceByName('file');
        if (!$file) {
            return $this->json(['success' => false, 'error' => 'No file uploaded'], 400);
        }

        try {
            $asset = $this->module()->getAssetService()->createFromUpload($file, [
                'conversation_id' => $conversationId > 0 ? $conversationId : null,
                'uploaded_by' => \Yii::$app->has('user') ? (string)(\Yii::$app->user->id ?? '') : null,
            ]);
        } catch (InvalidArgumentException $exception) {
            return $this->json(['success' => false, 'error' => $exception->getMessage()], 400);
        } catch (\Throwable $exception) {
            \Yii::error($exception, __METHOD__);
            return $this->json(['success' => false, 'error' => 'No se pudo guardar el archivo.'], 500);
        }

        return $this->json(['success' => true, 'asset' => $asset->toDisplayArray()]);
    }
}
