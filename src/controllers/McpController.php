<?php

namespace eseperio\aiagent\controllers;

use eseperio\aiagent\dto\McpAuthContext;
use yii\filters\VerbFilter;
use yii\web\Controller;
use yii\web\ForbiddenHttpException;
use yii\web\NotFoundHttpException;
use yii\web\Response;

class McpController extends Controller
{
    public $enableCsrfValidation = false;

    public function behaviors(): array
    {
        return [
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => [
                    'endpoint' => ['POST', 'OPTIONS'],
                    'resource-metadata' => ['GET'],
                    'authorization-server-metadata' => ['GET'],
                    'authorize' => ['GET'],
                    'token' => ['POST'],
                    'register' => ['POST'],
                ],
            ],
        ];
    }

    public function beforeAction($action)
    {
        if (!$this->module instanceof \eseperio\aiagent\Module || !$this->module->mcpEnabled) {
            throw new NotFoundHttpException();
        }

        if (is_callable($this->module->mcpAvailabilityCallback)
            && !call_user_func($this->module->mcpAvailabilityCallback, \Yii::$app->request, $this->module, $action)
        ) {
            throw new NotFoundHttpException();
        }

        $this->applyCors();

        if (!$this->isOriginAllowed()) {
            throw new ForbiddenHttpException('Origin is not allowed');
        }

        if (\Yii::$app->request->method === 'OPTIONS') {
            \Yii::$app->response->statusCode = 204;
            return false;
        }

        return parent::beforeAction($action);
    }

    public function actionEndpoint()
    {
        $auth = $this->authenticateMcp();
        if (!$auth instanceof McpAuthContext) {
            return $auth;
        }

        $payload = \Yii::$app->request->bodyParams;
        if (!is_array($payload) || $payload === []) {
            $raw = \Yii::$app->request->rawBody;
            $payload = json_decode((string)$raw, true);
        }
        if (!is_array($payload)) {
            return $this->jsonRpcError(null, -32700, 'Parse error');
        }

        return $this->handleJsonRpc($payload, $auth);
    }

    public function actionResourceMetadata(): array
    {
        $module = $this->module;
        $this->asJsonResponse();

        return [
            'resource' => $module->buildMcpUrl('', true),
            'authorization_servers' => [
                $module->buildMcpUrl('.well-known/oauth-authorization-server', true),
            ],
            'bearer_methods_supported' => ['header'],
            'scopes_supported' => $module->getMcpSupportedScopes(),
        ];
    }

    public function actionAuthorizationServerMetadata(): array
    {
        $module = $this->module;
        $this->asJsonResponse();

        $metadata = [
            'issuer' => $module->resolveMcpIssuer(),
            'authorization_endpoint' => $module->buildMcpUrl('oauth/authorize', true),
            'token_endpoint' => $module->buildMcpUrl('oauth/token', true),
            'response_types_supported' => ['code'],
            'grant_types_supported' => ['authorization_code', 'refresh_token'],
            'code_challenge_methods_supported' => ['S256'],
            'token_endpoint_auth_methods_supported' => ['none', 'client_secret_post'],
            'scopes_supported' => $module->getMcpSupportedScopes(),
        ];
        if (is_callable($module->mcpRegistrationHandler)) {
            $metadata['registration_endpoint'] = $module->buildMcpUrl('oauth/register', true);
        }

        return $metadata;
    }

    public function actionAuthorize()
    {
        return $this->delegateOAuthHandler($this->module->mcpAuthorizationHandler, 'MCP authorization endpoint is not configured');
    }

    public function actionToken()
    {
        return $this->delegateOAuthHandler($this->module->mcpTokenHandler, 'MCP token endpoint is not configured');
    }

    public function actionRegister()
    {
        return $this->delegateOAuthHandler($this->module->mcpRegistrationHandler, 'MCP dynamic client registration is not configured');
    }

    private function handleJsonRpc(array $payload, McpAuthContext $auth)
    {
        $id = $payload['id'] ?? null;
        $method = isset($payload['method']) ? (string)$payload['method'] : '';
        $params = is_array($payload['params'] ?? null) ? $payload['params'] : [];

        if (($payload['jsonrpc'] ?? null) !== '2.0' || $method === '') {
            return $this->jsonRpcError($id, -32600, 'Invalid Request');
        }

        if ($method === 'notifications/initialized' && !array_key_exists('id', $payload)) {
            \Yii::$app->response->statusCode = 204;
            return null;
        }

        return match ($method) {
            'initialize' => $this->jsonRpcResult($id, [
                'protocolVersion' => $this->module->mcpProtocolVersion,
                'capabilities' => [
                    'tools' => ['listChanged' => false],
                ],
                'serverInfo' => [
                    'name' => $this->module->mcpServerName,
                    'version' => '1.0.0',
                ],
            ]),
            'notifications/initialized', 'ping' => $this->jsonRpcResult($id, new \stdClass()),
            'tools/list' => $this->jsonRpcResult($id, $this->module->getMcpServer()->listTools($auth, \Yii::$app->request)),
            'tools/call' => $this->handleToolCall($id, $params, $auth),
            default => $this->jsonRpcError($id, -32601, 'Method not found'),
        };
    }

    private function handleToolCall(mixed $id, array $params, McpAuthContext $auth): array
    {
        $name = isset($params['name']) ? (string)$params['name'] : '';
        $arguments = is_array($params['arguments'] ?? null) ? $params['arguments'] : [];
        if ($name === '') {
            return $this->jsonRpcError($id, -32602, 'Invalid params');
        }

        $result = $this->module->getMcpServer()->callTool($name, $arguments, $auth, \Yii::$app->request);
        if (($result['isError'] ?? false) && ($result['errorCode'] ?? null) === 'forbidden') {
            return $this->jsonRpcError($id, -32003, $result['content'][0]['text'] ?? 'Forbidden', null, 403);
        }

        return $this->jsonRpcResult($id, $result);
    }

    private function authenticateMcp(): McpAuthContext|array
    {
        try {
            return $this->module->getMcpTokenValidator()->validate(\Yii::$app->request->headers->get('Authorization'));
        } catch (\Throwable $exception) {
            $this->setAuthenticateHeader();
            return $this->json([
                'error' => 'unauthorized',
                'error_description' => $exception->getMessage(),
            ], 401);
        }
    }

    private function delegateOAuthHandler(mixed $handler, string $fallback)
    {
        if (is_callable($handler)) {
            return call_user_func($handler, \Yii::$app->request, \Yii::$app->response, $this->module);
        }

        return $this->json([
            'error' => 'not_configured',
            'error_description' => $fallback,
        ], 501);
    }

    private function jsonRpcResult(mixed $id, mixed $result): array
    {
        return $this->json([
            'jsonrpc' => '2.0',
            'id' => $id,
            'result' => $result,
        ]);
    }

    private function jsonRpcError(mixed $id, int $code, string $message, mixed $data = null, int $statusCode = 200): array
    {
        $error = [
            'code' => $code,
            'message' => $message,
        ];
        if ($data !== null) {
            $error['data'] = $data;
        }

        return $this->json([
            'jsonrpc' => '2.0',
            'id' => $id,
            'error' => $error,
        ], $statusCode);
    }

    private function json(array $data, int $statusCode = 200): array
    {
        $this->asJsonResponse();
        \Yii::$app->response->statusCode = $statusCode;
        return $data;
    }

    private function asJsonResponse(): void
    {
        \Yii::$app->response->format = Response::FORMAT_JSON;
    }

    private function setAuthenticateHeader(): void
    {
        $scope = implode(' ', $this->module->getMcpSupportedScopes());
        \Yii::$app->response->headers->set(
            'WWW-Authenticate',
            'Bearer resource_metadata="' . $this->module->buildMcpUrl('.well-known/oauth-protected-resource', true) . '" scope="' . $scope . '"'
        );
    }

    private function applyCors(): void
    {
        $origin = \Yii::$app->request->headers->get('Origin');
        if (!is_string($origin) || $origin === '') {
            return;
        }

        if ($this->isOriginAllowed()) {
            $headers = \Yii::$app->response->headers;
            $headers->set('Access-Control-Allow-Origin', $origin);
            $headers->set('Access-Control-Allow-Methods', 'POST, GET, OPTIONS');
            $headers->set('Access-Control-Allow-Headers', 'Authorization, Content-Type, MCP-Protocol-Version');
            $headers->set('Access-Control-Max-Age', '3600');
            $headers->set('Vary', 'Origin');
        }
    }

    private function isOriginAllowed(): bool
    {
        $allowed = $this->module->mcpAllowedOrigins;
        if ($allowed === []) {
            return true;
        }

        $origin = \Yii::$app->request->headers->get('Origin');
        return !is_string($origin) || $origin === '' || in_array($origin, $allowed, true);
    }
}
