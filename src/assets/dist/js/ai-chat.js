(function () {
    'use strict';

    function parseProps(node) {
        try {
            return JSON.parse(node.getAttribute('data-props') || '{}');
        } catch (error) {
            return {};
        }
    }

    function appendFormData(formData, key, value) {
        if (Array.isArray(value)) {
            value.forEach(function (item, index) {
                appendFormData(formData, key + '[' + index + ']', item);
            });
            return;
        }
        if (value && typeof value === 'object') {
            Object.keys(value).forEach(function (childKey) {
                appendFormData(formData, key + '[' + childKey + ']', value[childKey]);
            });
            return;
        }
        formData.append(key, value == null ? '' : value);
    }

    function post(url, payload) {
        var formData = new FormData();
        Object.keys(payload || {}).forEach(function (key) {
            appendFormData(formData, key, payload[key]);
        });
        return fetch(url, {
            method: 'POST',
            headers: {'X-Requested-With': 'XMLHttpRequest'},
            body: formData
        }).then(function (response) {
            return response.json().then(function (data) {
                if (!response.ok) {
                    data.success = false;
                }
                return data;
            });
        });
    }

    function get(url, params) {
        var target = new URL(url, window.location.href);
        Object.keys(params || {}).forEach(function (key) {
            if (params[key] !== null && params[key] !== undefined && params[key] !== '') {
                target.searchParams.set(key, params[key]);
            }
        });
        return fetch(target.toString(), {headers: {'X-Requested-With': 'XMLHttpRequest'}}).then(function (response) {
            return response.json();
        });
    }

    function text(value) {
        return document.createTextNode(value == null ? '' : String(value));
    }

    function el(tag, className, content) {
        var node = document.createElement(tag);
        if (className) {
            node.className = className;
        }
        if (content !== undefined && content !== null) {
            node.appendChild(text(content));
        }
        return node;
    }

    function renderMessage(message) {
        var node = el('article', 'ai-agent-message ai-agent-message-' + (message.role || 'assistant'));
        var body = el('div', 'ai-agent-message-body');
        if (message.message_type === 'questionnaire') {
            node.classList.add('ai-agent-message-questionnaire');
            try {
                var questionnaire = JSON.parse(message.content || '{}');
                body.appendChild(el('strong', '', questionnaire.title || 'Cuestionario'));
                (questionnaire.questions || []).forEach(function (question) {
                    body.appendChild(el('div', 'ai-agent-question', question.label || question.text || question.name || 'Pregunta'));
                });
            } catch (error) {
                body.textContent = message.content || '';
            }
        } else {
            body.textContent = message.content || '';
        }
        node.appendChild(body);
        if (message.message_type && message.message_type !== 'message') {
            node.setAttribute('data-message-type', message.message_type);
        }
        return node;
    }

    function init(node) {
        var props = parseProps(node);
        var urls = props.apiUrls || {};
        var permissions = props.permissions || {};
        var state = {conversationId: props.conversationId || null, busy: false};

        node.classList.add('ai-agent-' + (props.mode || 'floating'));
        if (props.position) {
            node.classList.add('ai-agent-' + props.position);
        }

        var shell = el('section', 'ai-agent-shell');
        var header = el('header', 'ai-agent-header');
        var title = el('strong', 'ai-agent-title', 'AI Assistant');
        var actions = el('div', 'ai-agent-header-actions');
        var newButton = el('button', 'ai-agent-icon-button', '+');
        newButton.type = 'button';
        newButton.title = 'Nueva conversacion';
        var toggleButton = el('button', 'ai-agent-icon-button ai-agent-toggle', props.mode === 'floating' && !props.autoOpen ? 'AI' : 'x');
        toggleButton.type = 'button';
        toggleButton.title = 'Abrir chat';
        actions.appendChild(newButton);
        if (props.mode === 'floating') {
            actions.appendChild(toggleButton);
        }
        header.appendChild(title);
        header.appendChild(actions);

        var layout = el('div', 'ai-agent-layout');
        var sidebar = el('aside', 'ai-agent-sidebar');
        var conversations = el('div', 'ai-agent-conversations');
        var contexts = el('div', 'ai-agent-contexts');
        sidebar.appendChild(conversations);
        sidebar.appendChild(contexts);

        var main = el('main', 'ai-agent-main');
        var messages = el('div', 'ai-agent-messages');
        var pending = el('div', 'ai-agent-pending-tools');
        var form = el('form', 'ai-agent-form');
        var input = document.createElement('textarea');
        input.className = 'ai-agent-input';
        input.rows = 2;
        input.placeholder = 'Escribe un mensaje';
        var send = el('button', 'ai-agent-send', 'Enviar');
        send.type = 'submit';
        form.appendChild(input);
        form.appendChild(send);
        main.appendChild(messages);
        main.appendChild(pending);
        main.appendChild(form);

        layout.appendChild(sidebar);
        layout.appendChild(main);
        shell.appendChild(header);
        shell.appendChild(layout);
        node.appendChild(shell);

        if (props.mode === 'floating' && !props.autoOpen) {
            node.classList.add('is-collapsed');
        }

        function api(name, fallback) {
            return urls[name] || fallback || '';
        }

        function setBusy(value) {
            state.busy = value;
            send.disabled = value;
            newButton.disabled = value || permissions.canCreateChat === false;
        }

        function renderMessages(items) {
            messages.replaceChildren();
            (items || []).forEach(function (message) {
                messages.appendChild(renderMessage(message));
            });
            messages.scrollTop = messages.scrollHeight;
        }

        function loadHistory() {
            if (!state.conversationId || !api('getHistory')) {
                renderMessages([]);
                return Promise.resolve();
            }
            return get(api('getHistory'), {conversation_id: state.conversationId}).then(function (data) {
                renderMessages(data.messages || []);
            });
        }

        function loadConversations() {
            if (!props.showConversationList || !api('listConversations') || permissions.canViewHistory === false) {
                sidebar.hidden = true;
                return Promise.resolve();
            }
            return get(api('listConversations'), {}).then(function (data) {
                conversations.replaceChildren();
                (data.conversations || []).forEach(function (conversation) {
                    var row = el('div', 'ai-agent-conversation-row');
                    var open = el('button', 'ai-agent-conversation', conversation.title || ('Conversacion #' + conversation.id));
                    open.type = 'button';
                    open.dataset.id = conversation.id;
                    if (Number(conversation.id) === Number(state.conversationId)) {
                        open.classList.add('is-active');
                    }
                    open.addEventListener('click', function () {
                        state.conversationId = conversation.id;
                        loadHistory();
                        loadContexts();
                        loadConversations();
                    });
                    row.appendChild(open);
                    if (permissions.canRenameChat !== false && api('renameConversation')) {
                        var rename = el('button', 'ai-agent-mini-action', 'R');
                        rename.type = 'button';
                        rename.title = 'Renombrar';
                        rename.addEventListener('click', function () {
                            var title = window.prompt('Nuevo titulo', conversation.title || '');
                            if (title === null) {
                                return;
                            }
                            post(api('renameConversation'), {conversation_id: conversation.id, title: title}).then(loadConversations);
                        });
                        row.appendChild(rename);
                    }
                    if (permissions.canArchiveChat !== false && api('archiveConversation')) {
                        var archive = el('button', 'ai-agent-mini-action', 'A');
                        archive.type = 'button';
                        archive.title = 'Archivar';
                        archive.addEventListener('click', function () {
                            post(api('archiveConversation'), {conversation_id: conversation.id}).then(loadConversations);
                        });
                        row.appendChild(archive);
                    }
                    if (permissions.canDeleteChat !== false && api('deleteConversation')) {
                        var remove = el('button', 'ai-agent-mini-action', 'D');
                        remove.type = 'button';
                        remove.title = 'Eliminar';
                        remove.addEventListener('click', function () {
                            if (!window.confirm('Eliminar conversacion?')) {
                                return;
                            }
                            post(api('deleteConversation'), {conversation_id: conversation.id}).then(function () {
                                if (Number(state.conversationId) === Number(conversation.id)) {
                                    state.conversationId = null;
                                    renderMessages([]);
                                    contexts.replaceChildren();
                                }
                                loadConversations();
                            });
                        });
                        row.appendChild(remove);
                    }
                    conversations.appendChild(row);
                });
            });
        }

        function loadContexts() {
            if (!state.conversationId || !api('renderContexts') || permissions.canRenderContext === false) {
                contexts.replaceChildren();
                return Promise.resolve();
            }
            return get(api('renderContexts'), {conversation_id: state.conversationId}).then(function (data) {
                contexts.innerHTML = data.html || '';
            });
        }

        function createConversation() {
            if (!api('createConversation') || permissions.canCreateChat === false) {
                return Promise.resolve();
            }
            setBusy(true);
            return post(api('createConversation'), {
                model: props.model || null,
                contexts: props.contexts || []
            }).then(function (data) {
                if (data.success && data.conversation) {
                    state.conversationId = data.conversation.id;
                    return Promise.all([loadConversations(), loadHistory(), loadContexts()]);
                }
                return null;
            }).finally(function () {
                setBusy(false);
            });
        }

        function renderPendingTools(items) {
            pending.replaceChildren();
            (items || []).forEach(function (tool) {
                var card = el('article', 'ai-agent-tool-card');
                card.appendChild(el('strong', '', tool.name || 'tool'));
                if (tool.requires_approval && api('executeTool') && permissions.canExecuteTool !== false) {
                    var approve = el('button', 'ai-agent-tool-approve', 'Ejecutar');
                    approve.type = 'button';
                    approve.addEventListener('click', function () {
                        setBusy(true);
                        post(api('executeTool'), {
                            conversation_id: state.conversationId,
                            tool_name: tool.name,
                            tool_call_id: tool.tool_call_id,
                            arguments: tool.arguments || {}
                        }).then(function () {
                            return Promise.all([loadHistory(), loadContexts()]);
                        }).finally(function () {
                            setBusy(false);
                        });
                    });
                    card.appendChild(approve);
                }
                pending.appendChild(card);
            });
        }

        form.addEventListener('submit', function (event) {
            event.preventDefault();
            var value = input.value.trim();
            if (!value || state.busy || permissions.canSendMessage === false) {
                return;
            }
            var ensureConversation = state.conversationId ? Promise.resolve() : createConversation();
            ensureConversation.then(function () {
                if (!state.conversationId || !api('sendMessage')) {
                    return;
                }
                setBusy(true);
                return post(api('sendMessage'), {
                    conversation_id: state.conversationId,
                    message: value
                }).then(function (data) {
                    input.value = '';
                    renderMessages(data.messages || []);
                    renderPendingTools(data.pending_tools || []);
                    return Promise.all([loadConversations(), loadContexts()]);
                }).finally(function () {
                    setBusy(false);
                });
            });
        });

        newButton.addEventListener('click', createConversation);
        toggleButton.addEventListener('click', function () {
            node.classList.toggle('is-collapsed');
            toggleButton.textContent = node.classList.contains('is-collapsed') ? 'AI' : 'x';
        });

        Promise.resolve()
            .then(function () {
                return state.conversationId ? null : createConversation();
            })
            .then(function () {
                return Promise.all([loadConversations(), loadHistory(), loadContexts()]);
            });
    }

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('.ai-assistant-mount').forEach(init);
    });
}());
