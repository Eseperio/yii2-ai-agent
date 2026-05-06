<?php

namespace eseperio\aiagent\services;

use eseperio\aiagent\dto\McpAuthContext;
use yii\base\Component;

class McpTokenValidator extends Component
{
    public function validate(?string $authorizationHeader): McpAuthContext
    {
        $module = $this->getModule();
        if (!$module) {
            throw new \RuntimeException('MCP module is not available');
        }

        if (!$module->mcpRequireAuth) {
            return new McpAuthContext();
        }

        $token = $this->extractBearerToken($authorizationHeader);
        if ($token === null) {
            throw new \RuntimeException('Missing bearer token');
        }

        if (is_callable($module->mcpAccessTokenValidator)) {
            $result = call_user_func($module->mcpAccessTokenValidator, $token, $module);
            if ($result instanceof McpAuthContext) {
                return $result;
            }
            if (is_array($result)) {
                return $this->contextFromArray($result, $token);
            }
            throw new \RuntimeException('Invalid bearer token');
        }

        return $this->validateJwt($token, $module);
    }

    private function validateJwt(string $token, \eseperio\aiagent\Module $module): McpAuthContext
    {
        if (!is_string($module->mcpJwtSecret) || $module->mcpJwtSecret === '') {
            throw new \RuntimeException('MCP JWT secret is not configured');
        }

        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            throw new \RuntimeException('Invalid bearer token');
        }

        [$encodedHeader, $encodedPayload, $encodedSignature] = $parts;
        $header = json_decode($this->base64UrlDecode($encodedHeader), true);
        $claims = json_decode($this->base64UrlDecode($encodedPayload), true);
        if (!is_array($header) || !is_array($claims) || ($header['alg'] ?? null) !== 'HS256') {
            throw new \RuntimeException('Invalid bearer token');
        }

        $expectedSignature = hash_hmac('sha256', $encodedHeader . '.' . $encodedPayload, $module->mcpJwtSecret, true);
        if (!hash_equals($expectedSignature, $this->base64UrlDecode($encodedSignature))) {
            throw new \RuntimeException('Invalid bearer token');
        }

        $now = time();
        if (!isset($claims['exp']) || (int)$claims['exp'] <= $now) {
            throw new \RuntimeException('Bearer token has expired');
        }
        if (($claims['type'] ?? null) !== 'access') {
            throw new \RuntimeException('Bearer token type is not valid for MCP');
        }
        if (($claims['aud'] ?? null) !== $module->mcpAudience) {
            throw new \RuntimeException('Bearer token audience is not valid for MCP');
        }
        if (($claims['iss'] ?? null) !== $module->resolveMcpIssuer()) {
            throw new \RuntimeException('Bearer token issuer is not valid for MCP');
        }

        $user = null;
        if (is_callable($module->mcpUserResolver)) {
            $user = call_user_func($module->mcpUserResolver, $claims, $token, $module);
        }

        return new McpAuthContext(
            scopes: $this->normalizeScopes($claims['scope'] ?? []),
            user: $user,
            subject: isset($claims['sub']) ? (string)$claims['sub'] : null,
            clientId: isset($claims['client_id']) ? (string)$claims['client_id'] : null,
            tokenId: isset($claims['jti']) ? (string)$claims['jti'] : null,
            rawToken: $token,
            claims: $claims
        );
    }

    private function contextFromArray(array $data, string $token): McpAuthContext
    {
        return new McpAuthContext(
            scopes: $this->normalizeScopes($data['scopes'] ?? ($data['scope'] ?? [])),
            user: $data['user'] ?? null,
            subject: isset($data['subject']) ? (string)$data['subject'] : (isset($data['sub']) ? (string)$data['sub'] : null),
            clientId: isset($data['clientId']) ? (string)$data['clientId'] : (isset($data['client_id']) ? (string)$data['client_id'] : null),
            tokenId: isset($data['tokenId']) ? (string)$data['tokenId'] : (isset($data['jti']) ? (string)$data['jti'] : null),
            rawToken: $token,
            claims: is_array($data['claims'] ?? null) ? $data['claims'] : $data
        );
    }

    private function extractBearerToken(?string $header): ?string
    {
        if (!is_string($header) || !preg_match('/^Bearer\s+(.+)$/i', trim($header), $matches)) {
            return null;
        }

        $token = trim($matches[1]);
        return $token !== '' ? $token : null;
    }

    private function normalizeScopes(mixed $scope): array
    {
        if (is_string($scope)) {
            $scope = preg_split('/\s+/', trim($scope)) ?: [];
        }
        if (!is_array($scope)) {
            return [];
        }

        return array_values(array_unique(array_filter(array_map('strval', $scope))));
    }

    private function base64UrlDecode(string $value): string
    {
        $remainder = strlen($value) % 4;
        if ($remainder > 0) {
            $value .= str_repeat('=', 4 - $remainder);
        }

        $decoded = base64_decode(strtr($value, '-_', '+/'), true);
        return $decoded === false ? '' : $decoded;
    }

    private function getModule(): ?\eseperio\aiagent\Module
    {
        return \eseperio\aiagent\Module::resolveActive();
    }
}
