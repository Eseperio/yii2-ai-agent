<?php

namespace eseperio\aiagent\services;

use eseperio\aiagent\contracts\ToolHandlerInterface;
use eseperio\aiagent\dto\McpAuthContext;
use eseperio\aiagent\dto\ToolContext;
use eseperio\aiagent\dto\ToolDefinition;
use eseperio\aiagent\dto\ToolExecutionContext;
use eseperio\aiagent\dto\ToolResult;
use yii\base\Component;

class McpServer extends Component
{
    public function listTools(McpAuthContext $auth, mixed $request = null): array
    {
        $tools = [];
        foreach ($this->getMcpTools($auth, $request) as $tool) {
            $tools[] = [
                'name' => $tool->name,
                'description' => $tool->description,
                'inputSchema' => $tool->parameters,
            ];
        }

        return ['tools' => $tools];
    }

    public function callTool(string $name, array $arguments, McpAuthContext $auth, mixed $request = null): array
    {
        $definition = null;
        $missingScope = false;
        foreach ($this->getMcpTools($auth, $request) as $tool) {
            if ($tool->name === $name) {
                $definition = $tool;
                break;
            }
        }

        if (!$definition instanceof ToolDefinition) {
            foreach ($this->getMcpTools($auth, $request, false) as $tool) {
                if ($tool->name === $name) {
                    $missingScope = true;
                    break;
                }
            }
            if ($missingScope) {
                return $this->toolError('Tool scope is not allowed for this token', null, 'forbidden');
            }
            return $this->toolError('Tool not found or not allowed');
        }

        $context = new ToolExecutionContext(
            conversation: null,
            message: null,
            toolCallId: 'mcp-' . bin2hex(random_bytes(8)),
            responseId: null,
            contexts: [],
            user: $auth->user,
            request: $request,
            toolSnapshot: [],
            metadata: [
                'source' => 'mcp',
                'mcp' => [
                    'subject' => $auth->subject,
                    'client_id' => $auth->clientId,
                    'scopes' => $auth->scopes,
                    'token_id' => $auth->tokenId,
                ],
            ]
        );

        $module = $this->getModule();
        $policy = $module?->getToolPolicy()->decide($definition, $context, $arguments);
        if ($policy && !$policy->allowed) {
            return $this->toolError($policy->reason ?? 'Tool execution denied by policy');
        }

        $execution = $module?->getExecutionJournal()->start($definition, $context, $arguments, [
            'effect' => $policy?->effect,
            'riskLevel' => $policy?->riskLevel,
            'source' => 'mcp',
            'client_id' => $auth->clientId,
        ]);

        try {
            $result = $this->executeHandler($definition, $context, $arguments);
        } catch (\Throwable $exception) {
            \Yii::error($exception, __METHOD__);
            $result = new ToolResult(false, null, 'Tool execution failed', [], [], 'Tool execution failed');
        }

        $module?->getExecutionJournal()->finish($execution, $result);

        if (!$result->success) {
            return $this->toolError($result->message ?? $result->error ?? 'Tool execution failed', $result->data);
        }

        return [
            'content' => [[
                'type' => 'text',
                'text' => $result->message ?? $this->stringifyResult($result->data),
            ]],
            'structuredContent' => $result->data,
            'isError' => false,
        ];
    }

    public function hasScope(McpAuthContext $auth, string $requiredScope): bool
    {
        if ($requiredScope === '') {
            return true;
        }

        foreach ($auth->scopes as $scope) {
            if ($scope === $requiredScope) {
                return true;
            }
            if (str_ends_with($requiredScope, '.read')) {
                $prefix = substr($requiredScope, 0, -5);
                if ($scope === $prefix . '.write' || $scope === $prefix . '.manage') {
                    return true;
                }
            }
        }

        return false;
    }

    private function getMcpTools(McpAuthContext $auth, mixed $request, bool $filterScopes = true): array
    {
        $module = $this->getModule();
        if (!$module) {
            return [];
        }

        $context = new ToolContext(
            conversation: null,
            contexts: [],
            user: $auth->user,
            request: $request,
            metadata: [
                'source' => 'mcp',
                'mcp' => [
                    'subject' => $auth->subject,
                    'client_id' => $auth->clientId,
                    'scopes' => $auth->scopes,
                    'token_id' => $auth->tokenId,
                ],
            ]
        );

        $tools = [];
        foreach ($module->getToolRegistry()->getResolvedTools($context) as $tool) {
            if (!$tool instanceof ToolDefinition || !$this->isMcpEnabled($tool)) {
                continue;
            }
            if ($filterScopes && !$this->hasRequiredScopes($tool, $auth)) {
                continue;
            }
            $tools[] = $tool;
        }

        return $tools;
    }

    private function isMcpEnabled(ToolDefinition $tool): bool
    {
        return ($tool->metadata['mcp'] ?? false) === true || ($tool->metadata['mcpEnabled'] ?? false) === true;
    }

    private function hasRequiredScopes(ToolDefinition $tool, McpAuthContext $auth): bool
    {
        $scopes = $tool->metadata['mcpScopes'] ?? ($tool->metadata['scopes'] ?? ($tool->metadata['scope'] ?? []));
        if (is_string($scopes)) {
            $scopes = preg_split('/\s+/', trim($scopes)) ?: [];
        }
        if (!is_array($scopes) || $scopes === []) {
            return true;
        }

        foreach ($scopes as $scope) {
            if (!$this->hasScope($auth, (string)$scope)) {
                return false;
            }
        }

        return true;
    }

    private function executeHandler(ToolDefinition $definition, ToolExecutionContext $context, array $arguments): ToolResult
    {
        $handler = $definition->handler;
        if (is_callable($handler)) {
            $result = call_user_func($handler, $context, $arguments);
            return $result instanceof ToolResult ? $result : new ToolResult(true, $result);
        }

        if (is_object($handler) && $handler instanceof ToolHandlerInterface) {
            return $handler->execute($context, $arguments);
        }

        if (is_string($handler) && class_exists($handler)) {
            $handler = \Yii::createObject($handler);
            if ($handler instanceof ToolHandlerInterface) {
                return $handler->execute($context, $arguments);
            }
        }

        return new ToolResult(false, null, 'Tool handler not configured', [], [], 'Tool handler not configured');
    }

    private function toolError(string $message, mixed $data = null, ?string $errorCode = null): array
    {
        $result = [
            'content' => [[
                'type' => 'text',
                'text' => $message,
            ]],
            'structuredContent' => $data,
            'isError' => true,
        ];
        if ($errorCode !== null) {
            $result['errorCode'] = $errorCode;
        }

        return $result;
    }

    private function stringifyResult(mixed $data): string
    {
        if (is_scalar($data) || $data === null) {
            return (string)$data;
        }

        return json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '';
    }

    private function getModule(): ?\eseperio\aiagent\Module
    {
        return \eseperio\aiagent\Module::resolveActive();
    }
}
