<?php

namespace eseperio\aiagent\actions\chat;

use eseperio\aiagent\dto\ContextRenderContext;

class RenderContextsAction extends BaseChatAction
{
    public function run()
    {
        $conversationId = (int)$this->request()->get('conversation_id', $this->request()->post('conversation_id', 0));
        if (!$this->can('canRenderContext', $this->permissionContext('renderContexts', ['conversation_id' => $conversationId]))) {
            return $this->deny();
        }

        $conversation = $this->module()?->getConversationManager()->getConversation($conversationId);
        if (!$conversation) {
            return $this->json(['success' => false, 'error' => 'Conversation not found'], 404);
        }

        $contexts = $this->module()?->getContextManager()->listContexts($conversationId) ?? [];
        $renderContext = new ContextRenderContext(
            conversation: $conversation,
            user: $this->user(),
            request: $this->request(),
            metadata: [
                'conversation_id' => $conversationId,
            ]
        );

        $rendered = [];
        foreach ($contexts as $context) {
            $rendered[] = $this->module()?->getContextRenderer()->render($context, $renderContext);
        }

        $html = implode('', array_map(static function (array $context): string {
            $title = htmlspecialchars((string)($context['title'] ?? ''), ENT_QUOTES, 'UTF-8');
            $excerpt = htmlspecialchars((string)($context['excerpt'] ?? ''), ENT_QUOTES, 'UTF-8');
            $typeLabel = htmlspecialchars((string)($context['type_label'] ?? ''), ENT_QUOTES, 'UTF-8');
            $imageUrl = htmlspecialchars((string)($context['image_url'] ?? ''), ENT_QUOTES, 'UTF-8');
            $actionUrl = htmlspecialchars((string)($context['action_url'] ?? ''), ENT_QUOTES, 'UTF-8');
            $actionLabel = htmlspecialchars((string)($context['action_label'] ?? 'Open'), ENT_QUOTES, 'UTF-8');
            $secondaryUrl = htmlspecialchars((string)($context['secondary_url'] ?? ''), ENT_QUOTES, 'UTF-8');
            $secondaryLabel = htmlspecialchars((string)($context['secondary_label'] ?? 'View'), ENT_QUOTES, 'UTF-8');
            $badges = array_map(static fn($badge): string => htmlspecialchars((string)$badge, ENT_QUOTES, 'UTF-8'), (array)($context['badges'] ?? []));

            return '<article class="ai-agent-context" data-context-id="' . (int)($context['id'] ?? 0) . '">'
                . ($imageUrl !== '' ? '<img class="ai-agent-context-image" src="' . $imageUrl . '" alt="">' : '')
                . '<div class="ai-agent-context-content">'
                . '<div class="ai-agent-context-kicker">' . $typeLabel . '</div>'
                . '<h4 class="ai-agent-context-title">' . $title . '</h4>'
                . ($excerpt !== '' ? '<p class="ai-agent-context-excerpt">' . $excerpt . '</p>' : '')
                . ($badges ? '<div class="ai-agent-context-badges"><span>' . implode('</span><span>', $badges) . '</span></div>' : '')
                . (($actionUrl !== '' || $secondaryUrl !== '') ? '<div class="ai-agent-context-actions">'
                    . ($actionUrl !== '' ? '<a href="' . $actionUrl . '">' . $actionLabel . '</a>' : '')
                    . ($secondaryUrl !== '' ? '<a href="' . $secondaryUrl . '" target="_blank" rel="noopener noreferrer">' . $secondaryLabel . '</a>' : '')
                    . '</div>' : '')
                . '</div>'
                . '</article>';
        }, $rendered));

        return $this->json([
            'success' => true,
            'contexts' => $rendered,
            'html' => $html,
        ]);
    }
}
