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

    function csrfParam() {
        if (window.yii && typeof window.yii.getCsrfParam === 'function') {
            return window.yii.getCsrfParam();
        }
        var meta = document.querySelector('meta[name="csrf-param"]');
        return meta ? meta.getAttribute('content') : '';
    }

    function csrfToken() {
        if (window.yii && typeof window.yii.getCsrfToken === 'function') {
            return window.yii.getCsrfToken();
        }
        var meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute('content') : '';
    }

    function parseJsonResponse(response) {
        return response.text().then(function (payload) {
            var data = {};
            if (payload) {
                try {
                    data = JSON.parse(payload);
                } catch (error) {
                    data = {
                        success: false,
                        error: payload
                    };
                }
            }
            if (!response.ok) {
                data.success = false;
            }
            return data;
        });
    }

    function post(url, payload) {
        var formData = new FormData();
        Object.keys(payload || {}).forEach(function (key) {
            appendFormData(formData, key, payload[key]);
        });
        var param = csrfParam();
        var token = csrfToken();
        if (param && token) {
            formData.append(param, token);
        }
        return fetch(url, {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-Token': token || ''
            },
            body: formData
        }).then(function (response) {
            return parseJsonResponse(response);
        });
    }

    function get(url, params) {
        var target = new URL(url, window.location.href);
        Object.keys(params || {}).forEach(function (key) {
            if (params[key] !== null && params[key] !== undefined && params[key] !== '') {
                target.searchParams.set(key, params[key]);
            }
        });
        return fetch(target.toString(), {
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        }).then(function (response) {
            return parseJsonResponse(response);
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

    var processingMessages = [
        '•••',
        'pensando',
        '•••',
        'revisando el contexto',
        '•••',
        'analizando referencias',
        '•••',
        'procesando informacion'
    ];

    var processingTimings = [
        10000,
        6000,
        6000,
        6000,
        6000,
        6000,
        6000,
        6000
    ];

    function normalizeQuestionOptions(question) {
        var options = Array.isArray(question.options) ? question.options.slice() : [];
        var hasOther = options.some(function (option) {
            var value = typeof option === 'object' ? option.value : option;
            var label = typeof option === 'object' ? option.label : option;
            return String(value || '').toLowerCase() === '__other__' || String(label || '').toLowerCase() === 'otro';
        });
        if (!hasOther && question.type !== 'text') {
            options.push({value: '__other__', label: 'Otro'});
        }
        return options;
    }

    function responseMarkerKey(responseId) {
        var key = String(responseId || '').trim();
        return key !== '' ? key : 'no_response_id';
    }

    function questionnaireMarker(responseId, status) {
        return '[ai_questionnaire:' + responseMarkerKey(responseId) + ':' + (status === 'skipped' ? 'skipped' : 'submitted') + ']';
    }

    function parseQuestionnaireMarker(content) {
        var match = String(content || '').match(/\[ai_questionnaire:([^:\]]+):(submitted|skipped)\]/);
        if (!match) {
            return null;
        }
        return {marker: match[0], responseId: match[1], status: match[2]};
    }

    function stripQuestionnaireMarker(content) {
        var marker = parseQuestionnaireMarker(content);
        return marker ? String(content || '').replace(marker.marker, '').trim() : String(content || '');
    }

    function stripInternalUserPrefix(content) {
        var marker = '\n\nMensaje del usuario: ';
        var textContent = String(content || '');
        var index = textContent.indexOf(marker);
        return index === -1 ? textContent : textContent.slice(index + marker.length).trim();
    }

    function questionnaireKey(message) {
        return responseMarkerKey(message.response_id || message.id);
    }

    function toolActionKey(message) {
        return responseMarkerKey(message.tool_call_id || message.id || message.response_id);
    }

    function normalizeQuestions(questions) {
        return (Array.isArray(questions) ? questions : []).map(function (question, index) {
            var type = question.type || 'text';
            if (['text', 'single_choice', 'multiple_choice'].indexOf(type) === -1) {
                type = 'text';
            }
            return {
                id: String(question.id || ('question_' + index)),
                label: String(question.label || question.text || question.name || 'Pregunta'),
                type: type,
                required: question.required === true,
                placeholder: question.placeholder || '',
                options: normalizeQuestionOptions(question).map(function (option, optionIndex) {
                    var value = typeof option === 'object' ? option.value : option;
                    var label = typeof option === 'object' ? option.label : option;
                    return {
                        value: value == null ? ('option_' + optionIndex) : String(value),
                        label: label == null ? String(value || ('Opcion ' + (optionIndex + 1))) : String(label)
                    };
                })
            };
        }).filter(function (question) {
            return question.label !== '';
        });
    }

    function ensureQuestionnaireState(uiState, message) {
        var key = questionnaireKey(message);
        if (!uiState[key]) {
            uiState[key] = {step: 0, answers: {}};
        }
        if (!uiState[key].answers) {
            uiState[key].answers = {};
        }
        return uiState[key];
    }

    function ensureQuestionAnswer(cardState, question) {
        if (!cardState.answers[question.id]) {
            cardState.answers[question.id] = {value: question.type === 'multiple_choice' ? [] : '', other: ''};
        }
        if (question.type === 'multiple_choice' && !Array.isArray(cardState.answers[question.id].value)) {
            cardState.answers[question.id].value = [];
        }
        return cardState.answers[question.id];
    }

    function questionHasAnswer(cardState, question) {
        if (!question || !question.required) {
            return true;
        }
        var answer = ensureQuestionAnswer(cardState, question);
        if (question.type === 'text') {
            return String(answer.value || '').trim() !== '';
        }
        if (question.type === 'single_choice') {
            if (answer.value === '__other__') {
                return String(answer.other || '').trim() !== '';
            }
            return String(answer.value || '').trim() !== '';
        }
        if (question.type === 'multiple_choice') {
            var values = Array.isArray(answer.value) ? answer.value : [];
            if (values.length === 0) {
                return false;
            }
            return values.indexOf('__other__') === -1 || String(answer.other || '').trim() !== '';
        }
        return true;
    }

    function renderQuestionInput(question, message, cardState, locked, handlers) {
        var answer = ensureQuestionAnswer(cardState, question);
        var field = el('fieldset', 'ai-agent-question');
        var legend = el('legend', 'ai-agent-question-label');
        legend.appendChild(text(question.label));
        if (question.required) {
            legend.appendChild(el('span', 'ai-agent-question-required', '*'));
        }
        field.appendChild(legend);

        if (question.type === 'text') {
            var textarea = document.createElement('textarea');
            textarea.className = 'ai-agent-question-input';
            textarea.rows = 2;
            textarea.placeholder = question.placeholder || '';
            textarea.disabled = locked;
            textarea.value = typeof answer.value === 'string' ? answer.value : '';
            textarea.addEventListener('input', function () {
                answer.value = textarea.value;
                field.classList.remove('has-error');
            });
            field.appendChild(textarea);
            return field;
        }

        var optionType = question.type === 'multiple_choice' ? 'checkbox' : 'radio';
        var optionName = 'aiq-' + questionnaireKey(message) + '-' + question.id;
        question.options.forEach(function (option) {
            var optionLabel = el('label', 'ai-agent-question-option');
            var input = document.createElement('input');
            input.type = optionType;
            input.name = optionName;
            input.value = option.value;
            input.disabled = locked;
            input.checked = question.type === 'multiple_choice'
                ? (Array.isArray(answer.value) && answer.value.indexOf(option.value) !== -1)
                : answer.value === option.value;
            input.addEventListener('change', function () {
                if (question.type === 'multiple_choice') {
                    var values = Array.isArray(answer.value) ? answer.value.slice() : [];
                    var index = values.indexOf(option.value);
                    if (input.checked && index === -1) {
                        values.push(option.value);
                    } else if (!input.checked && index !== -1) {
                        values.splice(index, 1);
                    }
                    answer.value = values;
                    if (values.indexOf('__other__') === -1) {
                        answer.other = '';
                    }
                } else {
                    answer.value = option.value;
                    if (option.value !== '__other__') {
                        answer.other = '';
                    }
                }
                handlers.refresh();
            });
            optionLabel.appendChild(input);
            optionLabel.appendChild(el('span', '', option.label));
            field.appendChild(optionLabel);
        });

        var usesOther = question.type === 'multiple_choice'
            ? (Array.isArray(answer.value) && answer.value.indexOf('__other__') !== -1)
            : answer.value === '__other__';
        var otherInput = document.createElement('input');
        otherInput.type = 'text';
        otherInput.className = 'ai-agent-question-other-input';
        otherInput.placeholder = 'Especifica otra opcion';
        otherInput.disabled = locked || !usesOther;
        otherInput.value = answer.other || '';
        otherInput.addEventListener('input', function () {
            answer.other = otherInput.value;
        });
        field.appendChild(otherInput);

        return field;
    }

    function buildQuestionnaireSummary(message, questions, cardState) {
        var structured = {};
        var lines = ['Respuestas al formulario:'];
        questions.forEach(function (question) {
            var answer = ensureQuestionAnswer(cardState, question);
            var value = '';
            if (question.type === 'text') {
                value = String(answer.value || '').trim();
            } else if (question.type === 'single_choice') {
                value = answer.value === '__other__' ? String(answer.other || '').trim() : String(answer.value || '').trim();
            } else if (question.type === 'multiple_choice') {
                var values = Array.isArray(answer.value) ? answer.value.slice() : [];
                value = values.map(function (item) {
                    return item === '__other__' ? String(answer.other || '').trim() : item;
                }).filter(Boolean).join(', ');
            }
            structured[question.id] = question.type === 'multiple_choice'
                ? (value ? value.split(', ') : [])
                : value;
            lines.push('- ' + question.label + ': ' + (value || 'Sin respuesta'));
        });

        return questionnaireMarker(message.response_id || message.id, 'submitted')
            + '\n'
            + lines.join('\n')
            + '\n\nDatos estructurados:\n'
            + JSON.stringify(structured);
    }

    function renderQuestionnaire(message, handlers) {
        var questionnaire = JSON.parse(message.content || '{}');
        var questions = normalizeQuestions(questionnaire.questions);
        var cardState = ensureQuestionnaireState(handlers.uiState, message);
        var locked = message.submitted || message.skipped || !message.isLatest;
        cardState.step = Math.max(0, Math.min(Number(cardState.step || 0), Math.max(questions.length - 1, 0)));
        var currentQuestion = questions[cardState.step] || null;

        var wrapper = el('div', 'ai-agent-questionnaire');
        var header = el('div', 'ai-agent-questionnaire-header');
        header.appendChild(el('strong', 'ai-agent-questionnaire-title', questionnaire.title || 'Cuestionario'));
        if (questions.length > 0) {
            header.appendChild(el('span', 'ai-agent-questionnaire-step', (cardState.step + 1) + ' / ' + questions.length));
        }
        wrapper.appendChild(header);
        if (questionnaire.description) {
            wrapper.appendChild(el('p', 'ai-agent-questionnaire-description', questionnaire.description));
        }

        var form = el('form', 'ai-agent-questionnaire-form');
        if (currentQuestion) {
            form.appendChild(renderQuestionInput(currentQuestion, message, cardState, locked, handlers));
        }

        var footer = el('div', 'ai-agent-questionnaire-footer');
        var skip = el('button', 'ai-agent-questionnaire-skip', 'Saltar');
        skip.type = 'button';
        skip.disabled = locked;
        skip.addEventListener('click', function () {
            if (window.confirm('Seguro que quieres saltar este formulario?')) {
                message.skipped = true;
                cardState.skipped = true;
                handlers.refresh();
                handlers.submit(questionnaireMarker(message.response_id || message.id, 'skipped') + '\nHe decidido saltar el formulario y continuar sin esos datos.');
            }
        });
        footer.appendChild(skip);

        var nav = el('div', 'ai-agent-questionnaire-nav');
        var prev = el('button', 'ai-agent-questionnaire-prev', 'Anterior');
        prev.type = 'button';
        prev.disabled = locked || cardState.step === 0;
        prev.addEventListener('click', function () {
            cardState.step = Math.max(0, cardState.step - 1);
            handlers.refresh();
        });
        nav.appendChild(prev);

        if (cardState.step < questions.length - 1) {
            var next = el('button', 'ai-agent-questionnaire-next', 'Siguiente');
            next.type = 'button';
            next.disabled = locked;
            next.addEventListener('click', function () {
                if (!questionHasAnswer(cardState, currentQuestion)) {
                    var invalidField = form.querySelector('.ai-agent-question');
                    if (invalidField) {
                        invalidField.classList.add('has-error');
                    }
                    return;
                }
                cardState.step = Math.min(questions.length - 1, cardState.step + 1);
                handlers.refresh();
            });
            nav.appendChild(next);
        } else {
            var submit = el('button', 'ai-agent-questionnaire-submit', 'Confirmar');
            submit.type = 'submit';
            submit.disabled = locked;
            nav.appendChild(submit);
        }
        footer.appendChild(nav);
        form.appendChild(footer);

        form.addEventListener('submit', function (event) {
            event.preventDefault();
            if (locked || !questionHasAnswer(cardState, currentQuestion)) {
                var invalidField = form.querySelector('.ai-agent-question');
                if (invalidField) {
                    invalidField.classList.add('has-error');
                }
                return;
            }
            message.submitted = true;
            cardState.submitted = true;
            handlers.refresh();
            handlers.submit(buildQuestionnaireSummary(message, questions, cardState));
        });

        if (message.submitted) {
            wrapper.appendChild(el('div', 'ai-agent-questionnaire-status is-success', 'Respuestas enviadas'));
        } else if (message.skipped) {
            wrapper.appendChild(el('div', 'ai-agent-questionnaire-status', 'Formulario saltado'));
        }

        wrapper.appendChild(form);
        return wrapper;
    }

    function parseMessageJson(content) {
        try {
            return JSON.parse(content || '{}');
        } catch (error) {
            return {};
        }
    }

    function parseMessageMetadata(message) {
        if (!message) {
            return {};
        }
        var raw = message.metadata || message.tool_payload;
        if (!raw) {
            return {};
        }
        if (typeof raw === 'object') {
            return raw;
        }
        try {
            return JSON.parse(raw || '{}');
        } catch (error) {
            return {};
        }
    }

    function isHiddenToolMessage(message) {
        var metadata = parseMessageMetadata(message);
        var toolMetadata = metadata.tool_metadata || {};
        var payload = parseMessageJson(message.content);
        var toolName = String(payload.name || message.tool_name || '');
        var hiddenNames = {
            list_agent_manuals: true,
            read_agent_manual: true,
            search_entities: true,
            get_active_context: true,
            list_taxes: true
        };
        return toolMetadata.hidden === true || toolMetadata.internal === true || metadata.hidden === true || metadata.internal === true || hiddenNames[toolName] === true;
    }

    function humanizeToolName(name) {
        return String(name || 'tool').replace(/[_-]+/g, ' ').replace(/\b\w/g, function (letter) {
            return letter.toUpperCase();
        });
    }

    function renderToolAction(message, handlers) {
        var payload = parseMessageJson(message.content);
        var wrapper = el('div', 'ai-agent-action-card');
        var header = el('div', 'ai-agent-action-header');
        header.appendChild(el('strong', '', 'Accion propuesta'));
        if (message.executed) {
            header.appendChild(el('span', 'ai-agent-action-status is-success', 'Ejecutada'));
        } else if (message.rejected) {
            header.appendChild(el('span', 'ai-agent-action-status', 'Rechazada'));
        }
        wrapper.appendChild(header);
        wrapper.appendChild(el('div', 'ai-agent-action-title', humanizeToolName(payload.name || message.tool_name)));

        var argumentKeys = Object.keys(payload.arguments || {});
        if (argumentKeys.length > 0) {
            var detail = el('div', 'ai-agent-action-detail');
            detail.appendChild(text(argumentKeys.length + ' parametros configurados'));
            wrapper.appendChild(detail);
        }

        if (!message.executed && !message.rejected && message.isLatestAction) {
            var metadata = parseMessageMetadata(message);
            var footer = el('div', 'ai-agent-action-footer');
            var reject = el('button', 'ai-agent-action-reject', 'Rechazar');
            reject.type = 'button';
            reject.disabled = handlers.busy();
            reject.addEventListener('click', function () {
                handlers.rejectTool(message, humanizeToolName(payload.name || message.tool_name));
                handlers.refresh();
            });
            var execute = el('button', 'ai-agent-action-execute', 'Ejecutar');
            execute.type = 'button';
            execute.disabled = handlers.busy();
            execute.addEventListener('click', function () {
                handlers.executeTool({
                    name: payload.name || message.tool_name,
                    tool_call_id: message.tool_call_id || message.id,
                    snapshot_id: metadata.snapshot_id,
                    arguments: payload.arguments || {}
                });
            });
            var autoApprove = el('label', 'ai-agent-action-auto-approve');
            var autoApproveInput = document.createElement('input');
            autoApproveInput.type = 'checkbox';
            autoApproveInput.checked = handlers.autoApproveEnabled();
            autoApproveInput.disabled = handlers.busy();
            autoApproveInput.addEventListener('change', function () {
                handlers.setAutoApprove(autoApproveInput.checked);
                handlers.refresh();
            });
            autoApprove.appendChild(autoApproveInput);
            autoApprove.appendChild(el('span', '', 'Auto-aprobar comandos en esta sesion'));
            footer.appendChild(autoApprove);
            footer.appendChild(reject);
            footer.appendChild(execute);
            wrapper.appendChild(footer);
        }

        return wrapper;
    }

    function renderContextPreview(message) {
        var payload = parseMessageJson(message.content);
        var wrapper = el('article', 'ai-agent-context ai-agent-context-inline');
        if (payload.image_url) {
            var image = document.createElement('img');
            image.className = 'ai-agent-context-image';
            image.src = payload.image_url;
            image.alt = '';
            image.addEventListener('load', function () {
                window.setTimeout(function () {
                    var container = wrapper.closest('.ai-agent-messages');
                    if (container) {
                        container.scrollTop = container.scrollHeight;
                    }
                }, 0);
            });
            wrapper.appendChild(image);
        }

        var content = el('div', 'ai-agent-context-content');
        if (payload.type_label) {
            content.appendChild(el('div', 'ai-agent-context-kicker', payload.type_label));
        }
        content.appendChild(el('h4', 'ai-agent-context-title', payload.title || 'Contexto'));
        if (payload.excerpt) {
            content.appendChild(el('p', 'ai-agent-context-excerpt', payload.excerpt));
        }
        if (Array.isArray(payload.badges) && payload.badges.length > 0) {
            var badges = el('div', 'ai-agent-context-badges');
            payload.badges.forEach(function (badge) {
                badges.appendChild(el('span', '', badge));
            });
            content.appendChild(badges);
        }
        if (payload.action_url || payload.secondary_url) {
            var actions = el('div', 'ai-agent-context-actions');
            if (payload.action_url) {
                var action = el('a', '', payload.action_label || 'Abrir');
                action.href = payload.action_url;
                actions.appendChild(action);
            }
            if (payload.secondary_url) {
                var secondary = el('a', '', payload.secondary_label || 'Ver');
                secondary.href = payload.secondary_url;
                secondary.target = '_blank';
                secondary.rel = 'noopener noreferrer';
                actions.appendChild(secondary);
            }
            content.appendChild(actions);
        }

        wrapper.appendChild(content);
        return wrapper;
    }

    function renderMessage(message, handlers) {
        var node = el('article', 'ai-agent-message ai-agent-message-' + (message.role || 'assistant'));
        if (message.virtual) {
            node.classList.add('ai-agent-message-virtual');
        }
        var body = el('div', 'ai-agent-message-body');
        if (message.message_type === 'questionnaire') {
            node.classList.add('ai-agent-message-questionnaire');
            try {
                body.appendChild(renderQuestionnaire(message, handlers));
            } catch (error) {
                body.textContent = message.content || '';
            }
        } else if (message.message_type === 'tool_call') {
            node.classList.add('ai-agent-message-action');
            body.appendChild(renderToolAction(message, handlers));
        } else if (message.message_type === 'context') {
            node.classList.add('ai-agent-message-context');
            body.appendChild(renderContextPreview(message));
        } else {
            body.textContent = message.role === 'user' ? stripQuestionnaireMarker(message.content) : (message.content || '');
        }
        node.appendChild(body);
        if (message.message_type && message.message_type !== 'message') {
            node.setAttribute('data-message-type', message.message_type);
        }
        return node;
    }

    function resolveWelcomeMessage(props, conversationId) {
        var items = Array.isArray(props.welcomeMessages) ? props.welcomeMessages.filter(function (message) {
            return typeof message === 'string' && message.trim() !== '';
        }) : [];
        if (!items.length) {
            return '';
        }

        var id = Number(conversationId || 0);
        var index = id > 0 ? (id - 1) % items.length : 0;
        return items[index];
    }

    function init(node) {
        var props = parseProps(node);
        var urls = props.apiUrls || {};
        var permissions = props.permissions || {};
        var conversationUrlParam = String(props.conversationUrlParam || 'conversation_id').trim();
        var initialConversationId = props.conversationId || readConversationIdFromUrl(conversationUrlParam);
        var state = {
            conversationId: initialConversationId || null,
            busy: false,
            questionnaireBlocked: false,
            questionnaireUi: {},
            actionUi: {},
            pendingRejectionNote: '',
            autoApproveTools: false,
            autoApprovingActions: {},
            lastMessages: [],
            processing: {
                visible: false,
                index: 0,
                timer: null
            }
        };

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

        function readConversationIdFromUrl(paramName) {
            if (!paramName || !window.location || !window.URLSearchParams) {
                return null;
            }
            var value = new URLSearchParams(window.location.search).get(paramName);
            var id = Number(value || 0);
            return id > 0 ? id : null;
        }

        function syncConversationIdToUrl(conversationId) {
            if (!conversationUrlParam || !window.history || !window.location || !window.URL) {
                return;
            }
            var url = new URL(window.location.href);
            var id = Number(conversationId || 0);
            if (id > 0) {
                url.searchParams.set(conversationUrlParam, String(id));
            } else {
                url.searchParams.delete(conversationUrlParam);
            }
            window.history.replaceState(window.history.state, '', url.toString());
        }

        function scrollMessagesToBottom() {
            var scroll = function () {
                messages.scrollTop = messages.scrollHeight;
            };
            scroll();
            if (window.requestAnimationFrame) {
                window.requestAnimationFrame(function () {
                    scroll();
                    window.requestAnimationFrame(scroll);
                });
            }
            window.setTimeout(scroll, 80);
        }

        function setBusy(value) {
            var changed = state.busy !== value;
            state.busy = value;
            updateComposerState();
            newButton.disabled = value || permissions.canCreateChat === false;
            if (changed && state.lastMessages.length > 0) {
                renderMessages(state.lastMessages);
            }
            if (!value) {
                scheduleAutoApprovePendingAction();
            }
        }

        function renderProcessingIndicator() {
            messages.querySelectorAll('.ai-agent-processing-message').forEach(function (existing) {
                existing.remove();
            });
            if (!state.processing.visible) {
                return;
            }

            var message = processingMessages[state.processing.index] || processingMessages[0];
            var indicator = el('article', 'ai-agent-message ai-agent-message-assistant ai-agent-processing-message');
            var body = el('div', 'ai-agent-message-body ai-agent-processing-body');
            if (message === '•••') {
                var dots = el('span', 'ai-agent-processing-dots');
                dots.appendChild(el('span', '', '.'));
                dots.appendChild(el('span', '', '.'));
                dots.appendChild(el('span', '', '.'));
                body.appendChild(dots);
            } else {
                body.appendChild(el('span', 'ai-agent-processing-text', message));
            }
            indicator.appendChild(body);
            messages.appendChild(indicator);
            scrollMessagesToBottom();
        }

        function scheduleProcessingIndicator() {
            if (state.processing.timer) {
                window.clearTimeout(state.processing.timer);
            }
            var timeout = processingTimings[state.processing.index] || 6000;
            state.processing.timer = window.setTimeout(function () {
                if (!state.processing.visible) {
                    return;
                }
                state.processing.index = (state.processing.index + 1) % processingMessages.length;
                renderProcessingIndicator();
                scheduleProcessingIndicator();
            }, timeout);
        }

        function startProcessingIndicator() {
            state.processing.visible = true;
            state.processing.index = 0;
            renderProcessingIndicator();
            scheduleProcessingIndicator();
        }

        function stopProcessingIndicator() {
            state.processing.visible = false;
            state.processing.index = 0;
            if (state.processing.timer) {
                window.clearTimeout(state.processing.timer);
                state.processing.timer = null;
            }
            renderProcessingIndicator();
        }

        function updateComposerState() {
            var disabled = state.busy || state.questionnaireBlocked || permissions.canSendMessage === false;
            input.disabled = disabled;
            input.placeholder = state.questionnaireBlocked ? 'Responde primero al formulario pendiente' : 'Escribe un mensaje';
            send.disabled = disabled;
        }

        function prepareMessagesForRender(items) {
            var questionnaireStates = {};
            var completedToolCalls = {};
            (items || []).forEach(function (message) {
                if (message.role === 'user' && message.message_type === 'message') {
                    var marker = parseQuestionnaireMarker(message.content);
                    if (marker) {
                        questionnaireStates[marker.responseId] = marker.status;
                    }
                }
                if (message.message_type === 'tool_result' && message.tool_call_id) {
                    completedToolCalls[String(message.tool_call_id)] = true;
                }
            });

            var prepared = (items || []).map(function (message) {
                var clone = Object.assign({}, message);
                if (clone.role === 'user') {
                    clone.isInternalQuestionnaireReply = !!parseQuestionnaireMarker(clone.content);
                    clone.content = stripInternalUserPrefix(stripQuestionnaireMarker(clone.content));
                }
                if (clone.message_type === 'questionnaire') {
                    var key = responseMarkerKey(clone.response_id || clone.id);
                    var localQuestionnaireState = state.questionnaireUi[key] || {};
                    clone.submitted = questionnaireStates[key] === 'submitted' || localQuestionnaireState.submitted === true;
                    clone.skipped = questionnaireStates[key] === 'skipped' || localQuestionnaireState.skipped === true;
                    clone.isLatest = false;
                }
                if (clone.message_type === 'tool_call') {
                    clone.executed = !!completedToolCalls[String(clone.tool_call_id || clone.id || '')];
                    clone.rejected = !!(state.actionUi[toolActionKey(clone)] && state.actionUi[toolActionKey(clone)].rejected);
                    clone.hiddenTool = isHiddenToolMessage(clone);
                    clone.isLatestAction = false;
                }
                return clone;
            });

            var pendingQuestionnaires = prepared.filter(function (message) {
                return message.message_type === 'questionnaire' && !message.submitted && !message.skipped;
            });
            pendingQuestionnaires.forEach(function (message) {
                message.isLatest = false;
            });
            if (pendingQuestionnaires.length > 0) {
                pendingQuestionnaires[pendingQuestionnaires.length - 1].isLatest = true;
            }

            var pendingActions = prepared.filter(function (message) {
                return message.message_type === 'tool_call' && !message.executed && !message.rejected && !message.hiddenTool;
            });
            pendingActions.forEach(function (message) {
                message.isLatestAction = false;
            });
            if (pendingActions.length > 0) {
                pendingActions[pendingActions.length - 1].isLatestAction = true;
            }

            state.questionnaireBlocked = pendingQuestionnaires.length > 0;
            updateComposerState();
            return prepared.filter(function (message) {
                if (message.isInternalQuestionnaireReply) {
                    return false;
                }
                if (message.message_type === 'tool_result') {
                    return false;
                }
                if (message.message_type === 'tool_call' && message.hiddenTool) {
                    return false;
                }
                return !(message.role === 'user' && message.message_type === 'message' && String(message.content || '').trim() === '');
            });
        }

        function renderMessages(items) {
            var sourceMessages = items || [];
            var welcomeMessage = sourceMessages.length === 0 ? resolveWelcomeMessage(props, state.conversationId) : '';
            state.lastMessages = sourceMessages;
            var preparedMessages = prepareMessagesForRender(state.lastMessages);
            if (welcomeMessage !== '') {
                preparedMessages.unshift({
                    id: 'welcome-' + (state.conversationId || 'new'),
                    role: 'assistant',
                    message_type: 'message',
                    content: welcomeMessage,
                    virtual: true
                });
            }
            var interactionHandlers = {
                uiState: state.questionnaireUi,
                refresh: function () {
                    renderMessages(state.lastMessages);
                },
                submit: function (value) {
                    return sendMessageValue(value, true);
                },
                executeTool: executeTool,
                rejectTool: function (message, label) {
                    var key = toolActionKey(message);
                    state.actionUi[key] = {rejected: true};
                    var note = 'He rechazado ejecutar: ' + label + '.';
                    state.pendingRejectionNote = state.pendingRejectionNote ? state.pendingRejectionNote + ' ' + note : note;
                },
                autoApproveEnabled: function () {
                    return state.autoApproveTools === true;
                },
                setAutoApprove: function (enabled) {
                    state.autoApproveTools = enabled === true;
                    if (state.autoApproveTools) {
                        scheduleAutoApprovePendingAction();
                    }
                },
                busy: function () {
                    return state.busy;
                }
            };
            messages.replaceChildren();
            preparedMessages.forEach(function (message) {
                messages.appendChild(renderMessage(message, interactionHandlers));
            });
            renderProcessingIndicator();
            scrollMessagesToBottom();
            scheduleAutoApprovePendingAction();
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
                        syncConversationIdToUrl(state.conversationId);
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
                                    syncConversationIdToUrl(null);
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
                    syncConversationIdToUrl(state.conversationId);
                    return Promise.all([loadConversations(), loadHistory(), loadContexts()]);
                }
                return null;
            }).finally(function () {
                setBusy(false);
            });
        }

        function renderPendingTools(items) {
            pending.replaceChildren();
            if (items && items.length) {
                return;
            }
            (items || []).forEach(function (tool) {
                var card = el('article', 'ai-agent-tool-card');
                card.appendChild(el('strong', '', tool.name || 'tool'));
                if (tool.requires_approval && api('executeTool') && permissions.canExecuteTool !== false) {
                    var approve = el('button', 'ai-agent-tool-approve', 'Ejecutar');
                    approve.type = 'button';
                    approve.addEventListener('click', function () {
                        executeTool(tool);
                    });
                    card.appendChild(approve);
                }
                pending.appendChild(card);
            });
        }

        function executeTool(tool) {
            if (!tool || !tool.name || !api('executeTool') || permissions.canExecuteTool === false || state.busy) {
                return Promise.resolve();
            }

            setBusy(true);
            startProcessingIndicator();
            return post(api('executeTool'), {
                conversation_id: state.conversationId,
                tool_name: tool.name,
                tool_call_id: tool.tool_call_id,
                snapshot_id: tool.snapshot_id,
                arguments: tool.arguments || {}
            }).then(function () {
                return Promise.all([loadHistory(), loadContexts()]);
            }).finally(function () {
                setBusy(false);
                stopProcessingIndicator();
            });
        }

        function findLatestPendingToolAction() {
            var preparedMessages = prepareMessagesForRender(state.lastMessages || []);
            var pendingActions = preparedMessages.filter(function (message) {
                return message.message_type === 'tool_call'
                    && !message.executed
                    && !message.rejected
                    && message.isLatestAction;
            });
            if (!pendingActions.length) {
                return null;
            }

            var message = pendingActions[pendingActions.length - 1];
            var payload = parseMessageJson(message.content);
            var metadata = parseMessageMetadata(message);
            return {
                key: toolActionKey(message),
                name: payload.name || message.tool_name,
                tool_call_id: message.tool_call_id || message.id,
                snapshot_id: metadata.snapshot_id,
                arguments: payload.arguments || {}
            };
        }

        function scheduleAutoApprovePendingAction() {
            if (!state.autoApproveTools || state.busy || permissions.canExecuteTool === false) {
                return;
            }

            window.setTimeout(function () {
                if (!state.autoApproveTools || state.busy || permissions.canExecuteTool === false) {
                    return;
                }
                var tool = findLatestPendingToolAction();
                if (!tool || !tool.name || state.autoApprovingActions[tool.key]) {
                    return;
                }
                state.autoApprovingActions[tool.key] = true;
                executeTool(tool);
            }, 0);
        }

        function sendMessageValue(rawValue, bypassQuestionnaireBlock) {
            var value = String(rawValue || '').trim();
            if (!value || state.busy || permissions.canSendMessage === false || (state.questionnaireBlocked && bypassQuestionnaireBlock !== true)) {
                return Promise.resolve();
            }
            var payloadValue = value;
            if (state.pendingRejectionNote && bypassQuestionnaireBlock !== true) {
                payloadValue = state.pendingRejectionNote + '\n\nMensaje del usuario: ' + value;
                state.pendingRejectionNote = '';
            }
            var ensureConversation = state.conversationId ? Promise.resolve() : createConversation();
            return ensureConversation.then(function () {
                if (!state.conversationId || !api('sendMessage')) {
                    return;
                }
                setBusy(true);
                startProcessingIndicator();
                return post(api('sendMessage'), {
                    conversation_id: state.conversationId,
                    message: payloadValue
                }).then(function (data) {
                    if (input.value.trim() === value) {
                        input.value = '';
                    }
                    renderMessages(data.messages || []);
                    renderPendingTools(data.pending_tools || []);
                    return Promise.all([loadConversations(), loadContexts()]);
                }).finally(function () {
                    setBusy(false);
                    stopProcessingIndicator();
                });
            });
        }

        form.addEventListener('submit', function (event) {
            event.preventDefault();
            sendMessageValue(input.value);
        });
        input.addEventListener('keydown', function (event) {
            if (event.key !== 'Enter' || event.shiftKey) {
                return;
            }
            event.preventDefault();
            sendMessageValue(input.value);
        });

        newButton.addEventListener('click', createConversation);
        toggleButton.addEventListener('click', function () {
            node.classList.toggle('is-collapsed');
            toggleButton.textContent = node.classList.contains('is-collapsed') ? 'AI' : 'x';
        });

        if (state.conversationId) {
            syncConversationIdToUrl(state.conversationId);
        }

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
