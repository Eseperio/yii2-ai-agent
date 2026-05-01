<?php

namespace eseperio\aiagent\controllers\fakeopenai;

use yii\web\Controller;
use yii\web\Response;
use eseperio\aiagent\services\FakeOpenAiResponseFactory;

class V1Controller extends Controller
{
    public function actionResponses(): array
    {
        \Yii::$app->response->format = Response::FORMAT_JSON;
        return (new FakeOpenAiResponseFactory())->create(\Yii::$app->request->bodyParams);
    }
}
