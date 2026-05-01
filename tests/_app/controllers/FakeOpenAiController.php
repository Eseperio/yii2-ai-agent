<?php

namespace eseperio\aiagent\tests\controllers;

use yii\web\Controller;
use yii\web\Response;
use eseperio\aiagent\services\FakeOpenAiResponseFactory;

class FakeOpenAiController extends Controller
{
    public function actionResponses(): array
    {
        \Yii::$app->response->format = Response::FORMAT_JSON;
        return (new FakeOpenAiResponseFactory())->create(\Yii::$app->request->bodyParams);
    }
}
