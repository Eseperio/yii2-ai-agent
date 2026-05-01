<?php

namespace eseperio\aiagent\services;

use eseperio\aiagent\models\Conversation;
use eseperio\aiagent\models\Message;
use yii\base\Component;
use yii\db\ActiveRecord;

class ConversationManager extends Component
{
    public function createConversation(?string $title = null, ?string $model = null, array $metadata = [], array $contexts = [], mixed $createdBy = null): Conversation
    {
        $module = $this->getModule();
        if ($module?->reuseLastEmptyConversation && $createdBy !== null && $createdBy !== '') {
            $query = Conversation::find()
                ->alias('conversation')
                ->leftJoin(Message::tableName() . ' message', 'message.conversation_id = conversation.id')
                ->where(['conversation.status' => 'active'])
                ->andWhere(['message.id' => null])
                ->orderBy(['conversation.updated_at' => SORT_DESC, 'conversation.id' => SORT_DESC]);
            if ($createdBy !== null && $createdBy !== '') {
                $query->andWhere(['conversation.created_by' => (string)$createdBy]);
            }
            $existing = $query->one();
            if ($existing instanceof Conversation) {
                return $existing;
            }
        }

        $conversation = new Conversation();
        $conversation->title = $title;
        $conversation->model = $model;
        $conversation->metadata = $metadata ? json_encode($metadata) : null;
        $conversation->created_by = $createdBy !== null && $createdBy !== '' ? (string)$createdBy : null;
        $conversation->status = 'active';
        $conversation->last_message_at = time();
        $conversation->save(false);

        $contextManager = $module?->getContextManager();
        if ($contextManager) {
            foreach ($contexts as $context) {
                if (!is_array($context)) {
                    continue;
                }
                $type = (int)($context['type'] ?? 0);
                $contextMetadata = is_array($context['metadata'] ?? null) ? $context['metadata'] : [];
                if ($type <= 0) {
                    continue;
                }
                $contextManager->addContext(
                    (int)$conversation->id,
                    $type,
                    $contextMetadata,
                    isset($context['label']) ? (string)$context['label'] : null,
                    (int)($context['sort_order'] ?? 0)
                );
            }
        }

        return $conversation;
    }

    public function listConversations(): array
    {
        return Conversation::find()->where(['<>', 'status', 'deleted'])->orderBy(['updated_at' => SORT_DESC, 'id' => SORT_DESC])->all();
    }

    public function getConversation(int $id): ?Conversation
    {
        return Conversation::findOne($id);
    }

    public function renameConversation(int $id, ?string $title): bool
    {
        $conversation = $this->getConversation($id);
        if (!$conversation) {
            return false;
        }
        $conversation->title = $title;
        return $conversation->save(false, ['title', 'updated_at']);
    }

    public function archiveConversation(int $id): bool
    {
        $conversation = $this->getConversation($id);
        if (!$conversation) {
            return false;
        }
        $conversation->status = 'archived';
        return $conversation->save(false, ['status', 'updated_at']);
    }

    public function deleteConversation(int $id): bool
    {
        $conversation = $this->getConversation($id);
        if (!$conversation) {
            return false;
        }
        $conversation->status = 'deleted';
        return $conversation->save(false, ['status', 'updated_at']);
    }

    public function continueConversation(int $id): ?Conversation
    {
        $conversation = $this->getConversation($id);
        if (!$conversation || $conversation->status === 'deleted') {
            return null;
        }

        if ($conversation->status !== 'active') {
            $conversation->status = 'active';
            $conversation->save(false, ['status', 'updated_at']);
        }

        return $conversation;
    }

    public function addMessage(
        int $conversationId,
        string $role,
        string $messageType,
        string $content,
        ?string $responseId = null,
        ?string $toolCallId = null,
        ?string $toolName = null,
        ?array $toolPayload = null,
        array $tokenUsage = []
    ): Message {
        $message = new Message();
        $message->conversation_id = $conversationId;
        $message->role = $role;
        $message->message_type = $messageType;
        $message->content = $content;
        $message->response_id = $responseId;
        $message->tool_call_id = $toolCallId;
        $message->tool_name = $toolName;
        $message->tool_payload = $toolPayload ? json_encode($toolPayload) : null;
        $message->input_tokens = (int)($tokenUsage['input_tokens'] ?? 0);
        $message->output_tokens = (int)($tokenUsage['output_tokens'] ?? 0);
        $message->total_tokens = (int)($tokenUsage['total_tokens'] ?? 0);
        $message->save(false);

        $conversation = Conversation::findOne($conversationId);
        if ($conversation instanceof ActiveRecord) {
            $conversation->last_message_at = time();
            if ($responseId !== null) {
                $conversation->last_response_id = $responseId;
            }
            $conversation->input_tokens_total += $message->input_tokens;
            $conversation->output_tokens_total += $message->output_tokens;
            $conversation->total_tokens_total += $message->total_tokens;
            $conversation->save(false, ['last_message_at', 'last_response_id', 'input_tokens_total', 'output_tokens_total', 'total_tokens_total', 'updated_at']);
        }

        return $message;
    }

    public function getMessagesForDisplay(int $conversationId): array
    {
        return Message::find()
            ->where(['conversation_id' => $conversationId])
            ->orderBy(['created_at' => SORT_ASC, 'id' => SORT_ASC])
            ->asArray()
            ->all();
    }

    public function findLastResponseIdForContinuation(int $conversationId): ?string
    {
        $message = Message::find()
            ->where(['conversation_id' => $conversationId])
            ->andWhere(['not', ['response_id' => null]])
            ->andWhere(['<>', 'response_id', ''])
            ->orderBy(['id' => SORT_DESC])
            ->one();

        if ($message instanceof Message && is_string($message->response_id) && $message->response_id !== '') {
            return $message->response_id;
        }

        $conversation = $this->getConversation($conversationId);
        return $conversation && is_string($conversation->last_response_id) && $conversation->last_response_id !== ''
            ? $conversation->last_response_id
            : null;
    }

    private function getModule(): ?\eseperio\aiagent\Module
    {
        return \eseperio\aiagent\Module::resolveActive();
    }
}
