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
            return '<article class="ai-agent-context" data-context-id="' . (int)($context['id'] ?? 0) . '">'
                . '<h4>' . $title . '</h4>'
                . ($excerpt !== '' ? '<p>' . $excerpt . '</p>' : '')
                . '</article>';
        }, $rendered));

        return $this->json([
            'success' => true,
            'contexts' => $rendered,
            'html' => $html,
        ]);
    }
}
