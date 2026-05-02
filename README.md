# Yii2 AI Agent

`yii2-ai-agent` es una extension Yii2 para integrar un asistente IA con chat flotante o pantalla completa, historico de conversaciones, contexto persistente y ejecucion de tools basada en OpenAI Responses API.

## Instalacion

```bash
composer require eseperio/yii2-ai-agent
```

Tambien puedes montarlo como `path repository` durante el desarrollo local.

Ejecuta las migraciones que copie tu aplicacion:

```bash
php yii migrate --migrationPath=@vendor/eseperio/yii2-ai-agent/src/migrations
```

## Configuracion del modulo

```php
return [
    'modules' => [
        'aiAgent' => [
            'class' => \eseperio\aiagent\Module::class,
            'enabled' => true,
            'defaultModel' => 'gpt-5.2-2025-12-11',
            'autoExecutionMaxIterations' => 8,
            'clientConfig' => [
                'apiKey' => getenv('OPENAI_API_KEY'),
                'organization' => getenv('OPENAI_ORGANIZATION') ?: null,
                'project' => getenv('OPENAI_PROJECT') ?: null,
                'serviceTier' => getenv('OPENAI_SERVICE_TIER') ?: null,
            ],
        ],
    ],
];
```

`defaultModel` es el modelo por defecto del modulo. El widget puede sobreescribirlo con su parametro `model` cuando el permiso `canUseModel` lo permita.
Si prefieres otro ID de modulo, usa el mismo valor en la configuracion y en tus rutas; la libreria no depende de que el ID sea exactamente `aiAgent`. En los ejemplos siguientes se usa el ID `aiAgent`; si configuras el modulo como `ai-agent`, las rutas seran `/ai-agent/chat/...`.

Por defecto el modulo solicita a OpenAI una respuesta estructurada con `text.format` JSON schema. La respuesta debe traer
`response`, `conversation_title_suggestion` y `questionnaire`. Si `questionnaire.enabled` es `true`, el chat guarda un
mensaje separado de tipo `questionnaire` y el widget lo renderiza como formulario con opciones seleccionables. Puedes
desactivar o reemplazar ese contrato configurando `responseTextFormat`.

El modulo tambien antepone `baseInstructions` a las instrucciones de cada aplicacion. Ese bloque explica al LLM como
usar el contrato JSON, cuando debe usar `questionnaire`, como tratar las tools y que no debe exponer detalles internos
en el texto visible. Las aplicaciones Yii2 deben usar `instructionProviders` solo para anadir contexto de negocio,
reglas del dominio y guias especificas.

## Widget de chat

Modo flotante:

```php
echo \eseperio\aiagent\widgets\AiChat::widget([
    'conversationId' => $conversationId,
    'mode' => \eseperio\aiagent\widgets\AiChat::MODE_FLOATING,
    'position' => \eseperio\aiagent\widgets\AiChat::POSITION_BOTTOM_RIGHT,
    'model' => 'gpt-5.4-mini',
    'autoOpen' => true,
]);
```

Modo pantalla completa:

```php
echo \eseperio\aiagent\widgets\AiChat::widget([
    'conversationId' => $conversationId,
    'mode' => \eseperio\aiagent\widgets\AiChat::MODE_PAGE,
]);
```

`mode` admite `floating` o `page`. `position` solo aplica en modo flotante y acepta `bottom-right`, `bottom-left`, `top-right` o `top-left`.

## Permisos

Los permisos del modulo aceptan `bool` o `callable`. Si el valor es callable, recibe un `PermissionContext` con informacion de la accion, usuario, conversacion, mensajes, contextos y tool solicitada.

```php
'permissions' => [
    'canDeleteChat' => static function (\eseperio\aiagent\dto\PermissionContext $context): bool {
        return isset($context->user) && !$context->user->isGuest;
    },
],
```

Permisos disponibles:
`canViewChat`, `canCreateChat`, `canViewHistory`, `canContinueChat`, `canSendMessage`, `canRenameChat`, `canDeleteChat`, `canArchiveChat`, `canSetContext`, `canExecuteTool`, `canRenderContext`, `canUseModel`.

## Contextos

Cada aplicacion define sus propios tipos de contexto con constantes enteras.

```php
final class ProductContext
{
    public const TYPE_PRODUCT = 10;
    public const TYPE_CMS = 20;
}
```

Los contextos guardan `type` y `metadata`, de forma que cada app puede usar relaciones polimorficas o cualquier otra estructura interna.

Metadata recomendada para relaciones polimorficas:

```php
['class' => Product::class, 'id' => 123]
```

o

```php
['object_type' => ProductContext::TYPE_PRODUCT, 'object_id' => 123]
```

Los renderizadores de contexto se registran en `Module::$contextRenderers`. Pueden ser callables, clases o configuraciones Yii. El renderer recibe el `Context` y un `ContextRenderContext`, y debe devolver un array serializable con `type`, `id`, `type_label`, `title`, `excerpt`, `image_url`, `action_url`, `badges`, `locked` y `can_change`.

Ejemplo de instrucciones por aplicacion:

```php
'instructionProviders' => [
    static function (\eseperio\aiagent\dto\InstructionContext $context): string {
        return 'Eres un asistente general para la aplicacion.';
    },
],
```

Ejemplo de instrucciones por contexto:

```php
'instructionProviders' => [
    [
        'available' => static function (\eseperio\aiagent\dto\InstructionContext $context): bool {
            foreach ($context->contexts as $activeContext) {
                if ((int)($activeContext['type'] ?? 0) === ProductContext::TYPE_PRODUCT) {
                    return true;
                }
            }
            return false;
        },
        'class' => ProductInstructionProvider::class,
    ],
],
```

## Tools

Las tools globales se registran en `Module::$tools` y las dinamicas en `Module::$toolProviders`. Cada tool usa `ToolDefinition` con `name`, `description`, `parameters`, `handler`, `requiresApproval`, `providerId`, `contextTypes`, `available` y `metadata`.

Los handlers pueden ser:
- callable
- clase que implemente `ToolHandlerInterface`
- configuracion Yii

Las tools condicionadas por contexto pueden usar `available` y `contextTypes`. Si `requiresApproval` es `false`, la libreria autoejecuta la tool. Si es `true`, devuelve `pending_tools` para aprobacion manual.

Cuando la IA devuelve una `tool_call`, la libreria primero resuelve por snapshot de la conversacion. Si no hay snapshot, cae al registry actual y falla de forma explicita si el nombre es ambiguo.

La libreria guarda snapshots de tools antes de llamar a OpenAI y los asocia despues al `response_id` real para poder ejecutar la tool correcta aunque el nombre cambie en registros futuros.

## Endpoint falso para tests

La extension incluye un controlador fake para emular `POST /responses` de OpenAI en tests funcionales REST. El objetivo es probar el contrato JSON y el flujo de herramientas sin consumir API reales.

Respuestas fake soportadas en tests:
- respuesta simple
- cuestionario estructurado
- `tool_call`
- `function_call`
- escenario posterior a ejecucion de tool
- error controlado

El fake responde cuando el cliente usa `apiKey = 'test'`.

## Endpoints

Con el ID de modulo del ejemplo (`aiAgent`), los endpoints son:

- `GET /aiAgent/chat/index`
- `GET /aiAgent/chat/get-history`
- `GET /aiAgent/chat/list-conversations`
- `POST /aiAgent/chat/create-conversation`
- `POST /aiAgent/chat/continue-conversation`
- `POST /aiAgent/chat/rename-conversation`
- `POST /aiAgent/chat/archive-conversation`
- `POST /aiAgent/chat/delete-conversation`
- `POST /aiAgent/chat/send-message`
- `POST /aiAgent/chat/execute-tool`
- `GET /aiAgent/chat/list-contexts`
- `POST /aiAgent/chat/add-context`
- `POST /aiAgent/chat/remove-context`
- `GET /aiAgent/chat/render-contexts`

## Ejemplo completo

```php
echo \eseperio\aiagent\widgets\AiChat::widget([
    'mode' => \eseperio\aiagent\widgets\AiChat::MODE_PAGE,
    'model' => 'gpt-5.4-mini',
    'contexts' => [
        ['type' => 10, 'metadata' => ['class' => Product::class, 'id' => 123]],
    ],
]);
```
