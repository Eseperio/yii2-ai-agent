<?php

namespace eseperio\aiagent\services;

use eseperio\aiagent\dto\ToolDefinition;
use eseperio\aiagent\dto\ToolExecutionContext;
use eseperio\aiagent\dto\ToolPolicyDecision;
use yii\base\Component;

class ToolPolicy extends Component
{
    /**
     * Effects that may never be executed by the agent runtime. Integrators can
     * remove an effect from this list only when a domain has a stronger gate.
     */
    public array $blockedEffects = ['delete', 'publish', 'activate'];

    public array $autonomousEffects = ['read', 'preview'];

    public function decide(ToolDefinition $definition, ?ToolExecutionContext $context = null, array $arguments = []): ToolPolicyDecision
    {
        $metadata = $definition->metadata;
        $effect = strtolower((string)($metadata['effect'] ?? ($metadata['readOnly'] ?? false ? 'read' : 'write')));
        $riskLevel = strtolower((string)($metadata['riskLevel'] ?? ($effect === 'read' ? 'low' : 'medium')));

        if (in_array($effect, $this->blockedEffects, true) || ($metadata['forbidden'] ?? false)) {
            return new ToolPolicyDecision(false, true, false, $effect, $riskLevel, 'tool_effect_blocked');
        }

        $allowAutonomous = (bool)($metadata['allowAutonomous'] ?? in_array($effect, $this->autonomousEffects, true));
        $requiresApproval = (bool)($definition->requiresApproval || ($metadata['requiresApproval'] ?? false));
        if (!$allowAutonomous && $effect !== 'read' && $effect !== 'preview') {
            $requiresApproval = true;
        }

        if (!empty($metadata['expectedVersionRequired']) && !array_key_exists('expected_version', $arguments)) {
            return new ToolPolicyDecision(false, true, false, $effect, $riskLevel, 'expected_version_required');
        }

        $callback = $this->getModule()?->toolPolicyCallback;
        if (is_callable($callback)) {
            $result = call_user_func($callback, $definition, $context, $arguments, [
                'effect' => $effect,
                'riskLevel' => $riskLevel,
                'requiresApproval' => $requiresApproval,
                'allowAutonomous' => $allowAutonomous,
            ]);
            if ($result === false) {
                return new ToolPolicyDecision(false, $requiresApproval, false, $effect, $riskLevel, 'policy_callback_denied');
            }
            if (is_array($result)) {
                $requiresApproval = (bool)($result['requiresApproval'] ?? $requiresApproval);
                $allowAutonomous = (bool)($result['allowAutonomous'] ?? $allowAutonomous);
            }
        }

        return new ToolPolicyDecision(true, $requiresApproval, $allowAutonomous, $effect, $riskLevel);
    }

    private function getModule(): ?\eseperio\aiagent\Module
    {
        return \eseperio\aiagent\Module::resolveActive();
    }
}
