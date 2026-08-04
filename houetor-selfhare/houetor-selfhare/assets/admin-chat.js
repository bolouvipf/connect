(function ($) {
    'use strict';

    if (!window.HouetorSelfHare) return;

    var state = {
        lastToolCall: null,
        lastToolResult: null,
        lastUserMessage: null,
    };

    var LABELS = {
        post_title: 'Titre',
        post_content: 'Contenu',
        post_status: 'Statut',
        page_id: 'Page',
        html: 'Contenu HTML',
        position: 'Position',
        post_type: 'Type',
        name: 'Nom',
        price: 'Prix',
        stock_quantity: 'Stock',
    };

    var STATUS_LABELS = {
        draft: 'Brouillon',
        publish: 'Publié',
        pending: 'En attente',
        trash: 'Corbeille',
        private: 'Privé',
    };

    function formatStatus(v) {
        return STATUS_LABELS[v] || v;
    }

    function formatLabel(key) {
        return LABELS[key] || key.replace(/_/g, ' ');
    }

    function formatValue(key, val) {
        if (val === null || val === undefined) return '<em>vide</em>';
        if (key === 'post_status') return formatStatus(val);
        if (typeof val === 'string' && val.length > 300) return val.substring(0, 300) + '…';
        return $('<span>').text(String(val)).html();
    }

    function describeToolCall(tc) {
        if (!tc || !tc.name) return 'Action inconnue';
        var parts = [];
        var p = tc.params || {};

        if (tc.name === 'inject_page') {
            parts.push('Injecter du contenu');
            if (p.position === 'header') parts.push('en haut de');
            else if (p.position === 'footer') parts.push('en bas de');
            else parts.push('dans');
            parts.push('la page');
            if (p.page_id) parts.push('#' + p.page_id);
            if (p.ref) parts.push('(ref: ' + p.ref + ')');
        } else if (tc.name === 'get_wp_pages') {
            return 'Lister toutes les pages du site';
        } else if (tc.name.indexOf('create_') === 0) {
            var type = tc.name.replace('create_', '');
            var label = { posts: 'article', pages: 'page', products: 'produit' }[type] || type;
            parts.push('Créer un ' + label);
            if (p.post_title) parts.push('« ' + p.post_title + ' »');
        } else if (tc.name.indexOf('update_') === 0) {
            var type2 = tc.name.replace('update_', '');
            var label2 = { posts: 'article', pages: 'page', products: 'produit' }[type2] || type2;
            parts.push('Modifier un ' + label2);
            if (p.post_id) parts.push('#' + p.post_id);
            else if (p.id) parts.push('#' + p.id);
            var changes = [];
            if (p.post_title) changes.push('titre');
            if (p.post_content) changes.push('contenu');
            if (p.post_status) changes.push('statut → ' + formatStatus(p.post_status));
            if (changes.length) parts.push('(' + changes.join(', ') + ')');
        } else if (tc.name === 'revert_to_revision') {
            parts.push('Restaurer une révision');
            if (p.revision_id) parts.push('#' + p.revision_id);
            if (p.post_id) parts.push('sur la publication #' + p.post_id);
        } else if (tc.name === 'delete_block') {
            parts.push('Supprimer un bloc injecté');
            if (p.ref) parts.push('(ref: ' + p.ref + ')');
            if (p.page_id) parts.push('de la page #' + p.page_id);
        } else if (tc.name.indexOf('delete_') === 0) {
            var type3 = tc.name.replace('delete_', '');
            var label3 = { posts: 'article', pages: 'page', products: 'produit' }[type3] || type3;
            parts.push('Supprimer un ' + label3);
            if (p.post_id) parts.push('#' + p.post_id);
            else if (p.id) parts.push('#' + p.id);
        } else {
            parts.push(tc.name);
            if (Object.keys(p).length) {
                var details = [];
                for (var k in p) {
                    if (p.hasOwnProperty(k)) details.push(formatLabel(k) + ': ' + formatValue(k, p[k]));
                }
                parts.push('(' + details.join(', ') + ')');
            }
        }

        return parts.join(' ');
    }

    function renderDiffTable(before, after) {
        if (!before && !after) return '';
        if (!before) before = {};
        if (!after) after = {};

        var allKeys = {};
        for (var k in before) if (before.hasOwnProperty(k)) allKeys[k] = true;
        for (var k in after) if (after.hasOwnProperty(k)) allKeys[k] = true;

        var rows = '';
        for (var key in allKeys) {
            if (!allKeys.hasOwnProperty(key)) continue;
            var b = before.hasOwnProperty(key) ? before[key] : undefined;
            var a = after.hasOwnProperty(key) ? after[key] : undefined;
            var changed = String(b) !== String(a);

            var bHtml = formatValue(key, b);
            var aHtml = formatValue(key, a);
            var cls = changed ? 'changed' : 'unchanged';

            rows +=
                '<tr>' +
                '<td class="field-label">' + formatLabel(key) + '</td>' +
                '<td class="value-before ' + cls + '">' + bHtml + '</td>' +
                '<td class="value-after ' + cls + '">' + aHtml + '</td>' +
                '</tr>';
        }

        return '<table><thead><tr><th>Champ</th><th>Avant</th><th>Après</th></tr></thead><tbody>' + rows + '</tbody></table>';
    }

    $(function () {
        var $messages = $('#houetor-selfhare-messages');
        var $form = $('#houetor-selfhare-form');
        var $input = $('#houetor-selfhare-input');
        var $loading = $('#houetor-selfhare-loading');
        var $tool = $('#houetor-selfhare-tool');
        var $toolPreview = $('#houetor-selfhare-tool-preview');
        var $previewSummary = $('#houetor-selfhare-preview-summary');
        var $executeBtn = $('#houetor-selfhare-execute');
        var $confirmBtn = $('#houetor-selfhare-confirm');
        var $dismissBtn = $('#houetor-selfhare-dismiss');
        var $result = $('#houetor-selfhare-execute-result');

        var $modal = $('#houetor-selfhare-modal');
        var $modalSummary = $('#houetor-selfhare-modal-summary');
        var $modalDiff = $('#houetor-selfhare-modal-diff');
        var $modalConfirm = $('#houetor-selfhare-modal-confirm');
        var $modalCancel = $('#houetor-selfhare-modal-cancel');

        var $fileInput = $('#houetor-selfhare-file-input');
        var $filePreview = $('#houetor-selfhare-file-preview');
        var $fileName = $('#houetor-selfhare-file-name');
        var $fileRemove = $('#houetor-selfhare-file-remove');
        var $actionSelect = $('#houetor-selfhare-action');
        var $pageSelect = $('#houetor-selfhare-page');

        function buildEffectiveToolCall() {
            var selectedAction = $actionSelect.val();
            if (!selectedAction || !state.lastToolCall) return state.lastToolCall;

            if (state.lastToolCall.name === 'get_page_blocks') return state.lastToolCall;

            var tc = { name: selectedAction, params: {} };
            var aiParams = state.lastToolCall.params || {};
            for (var k in aiParams) {
                if (aiParams.hasOwnProperty(k)) tc.params[k] = aiParams[k];
            }

            var selectedPage = $pageSelect.val();
            if (selectedPage) {
                if (selectedAction === 'inject_page' || selectedAction === 'delete_block') {
                    tc.params.page_id = selectedPage;
                } else if (selectedAction.indexOf('update_') === 0 || selectedAction.indexOf('delete_') === 0) {
                    tc.params.post_id = selectedPage;
                }
            }

            return tc;
        }

        var previewData = null;
        var previewToken = null;
        var previewNonce = null;
        var uploadedFileUrl = null;
        var uploadingFile = false;

        function appendMessage(text, cls) {
            var $msg = $('<div>').addClass('houetor-message ' + cls).text(text);
            $messages.append($msg);
            $messages.scrollTop($messages[0].scrollHeight);
        }

        function sendChat(message, lastToolResult, lastToolName, opts) {
            opts = opts || {};
            if (!opts.silent) {
                appendMessage(message, 'user');
            }
            $loading.show();
            $tool.hide();

            $.ajax({
                url: HouetorSelfHare.ajax_url,
                method: 'POST',
                data: {
                    action: 'houetor_selfhare_chat',
                    message: message,
                    attachment_url: uploadedFileUrl || '',
                    selected_action: $actionSelect.val() || '',
                    selected_page: $pageSelect.val() || '',
                    last_tool_result: lastToolResult
                        ? JSON.stringify(lastToolResult)
                        : null,
                    last_tool_name: lastToolName || '',
                    _ajax_nonce: HouetorSelfHare.nonce,
                },
                success: function (res) {
                    $loading.hide();
                    if (res.success) {
                        var steps = res.data.steps || [];
                        for (var i = 0; i < steps.length; i++) {
                            appendMessage(steps[i], 'step');
                        }
                        if (res.data.reply) appendMessage(res.data.reply, 'assistant');
                        if (res.data.tool_call) {
                            state.lastToolCall = res.data.tool_call;
                            state.lastToolResult = null;
                            var effective = buildEffectiveToolCall();
                            $toolPreview.html(
                                '<span style="font-weight:600;color:#2ECC8A;">' +
                                $('<span>').text(describeToolCall(effective || res.data.tool_call)).html() +
                                '</span>'
                            );
                            $executeBtn.show();
                            $confirmBtn.hide();
                            $previewSummary.hide().empty();
                            $tool.show();
                            $result.empty();
                        }
                    } else {
                        appendMessage(
                            res.data || 'Erreur inconnue',
                            'error'
                        );
                    }
                },
                error: function () {
                    $loading.hide();
                    appendMessage(
                        'Erreur réseau. Veuillez réessayer.',
                        'error'
                    );
                },
            });
        }

        function closeModal() {
            $modal.hide();
            previewData = null;
            previewToken = null;
        }

        function clearAttachment() {
            uploadedFileUrl = null;
            $filePreview.removeClass('show');
            $fileInput.val('');
            $fileName.text('');
        }

        $fileInput.on('change', function () {
            var file = this.files[0];
            if (!file) return;

            if (!file.type.match(/^image\/(jpeg|png|webp)$/)) {
                appendMessage('Format non supporté. Utilisez JPG, PNG ou WebP.', 'error');
                return;
            }
            if (file.size > 5 * 1024 * 1024) {
                appendMessage('Image trop volumineuse. Maximum 5 Mo.', 'error');
                return;
            }

            uploadingFile = true;
            $loading.show();

            var fd = new FormData();
            fd.append('action', 'houetor_selfhare_upload');
            fd.append('file', file);
            fd.append('_ajax_nonce', HouetorSelfHare.nonce);

            $.ajax({
                url: HouetorSelfHare.ajax_url,
                method: 'POST',
                data: fd,
                processData: false,
                contentType: false,
                success: function (res) {
                    uploadingFile = false;
                    $loading.hide();
                    if (res.success) {
                        uploadedFileUrl = res.data.url;
                        $fileName.text(res.data.filename);
                        $filePreview.addClass('show');
                    } else {
                        appendMessage(res.data || 'Erreur upload', 'error');
                    }
                },
                error: function () {
                    uploadingFile = false;
                    $loading.hide();
                    appendMessage('Erreur réseau lors de l\'upload.', 'error');
                },
            });
        });

        $fileRemove.on('click', function () {
            clearAttachment();
        });

        $form.on('submit', function (e) {
            e.preventDefault();
            var msg = $input.val().trim();
            if (!msg && !uploadedFileUrl) return;

            state.lastUserMessage = msg;
            $input.val('');
            clearAttachment();
            sendChat(msg, state.lastToolResult, state.lastToolCall ? state.lastToolCall.name : '', {});
        });

        $executeBtn.on('click', function () {
            var toolCall = buildEffectiveToolCall();
            if (!toolCall) return;

            var $btn = $(this);
            $btn.prop('disabled', true).text('Prévisualisation…');
            $result.html(
                '<span class="spinner is-active" style="float:none;"></span>'
            );

            $.ajax({
                url: HouetorSelfHare.ajax_url,
                method: 'POST',
                data: {
                    action: 'houetor_selfhare_preview',
                    tool_call: JSON.stringify(toolCall),
                    _ajax_nonce: HouetorSelfHare.nonce,
                },
                success: function (res) {
                    $btn.prop('disabled', false).text('Exécuter');
                    if (res.success) {
                        previewData = toolCall;
                        previewToken = res.data.preview_token || null;
                        $modalSummary.text(res.data.summary);
                        var diffHtml = '';
                        if (res.data.before || res.data.after) {
                            diffHtml = renderDiffTable(res.data.before, res.data.after);
                        }
                        $modalDiff.html(diffHtml);
                        $modal.show();
                        $result.empty();
                    } else {
                        $result.html(
                            '<div class="notice notice-error inline" style="margin:0;"><p>' +
                                $('<span>')
                                    .text(res.data || 'Erreur de prévisualisation')
                                    .html() +
                                '</p></div>'
                        );
                    }
                },
                error: function () {
                    $btn.prop('disabled', false).text('Exécuter');
                    $result.html(
                        '<div class="notice notice-error inline" style="margin:0;"><p>Erreur réseau.</p></div>'
                    );
                },
            });
        });

        $modalConfirm.on('click', function () {
            if (!previewData) return;

            var $btn = $(this);
            $btn.prop('disabled', true).text('Exécution…');

            var tc = JSON.parse(JSON.stringify(previewData));
            tc.preview_token = previewToken;

            $.ajax({
                url: HouetorSelfHare.ajax_url,
                method: 'POST',
                data: {
                    action: 'houetor_selfhare_dispatch',
                    tool_call: JSON.stringify(tc),
                    _ajax_nonce: HouetorSelfHare.nonce,
                },
                success: function (res) {
                    $btn.prop('disabled', false).text('Confirmer et exécuter');
                    closeModal();
                    if (res.success) {
                        state.lastToolResult = res.data;
                        var executedName = state.lastToolCall ? state.lastToolCall.name : '';
                        var userMsg = state.lastUserMessage || '';
                        var msg = res.data.message || 'Action exécutée.';
                        $result.html(
                            '<div class="notice notice-success inline" style="margin:0;"><p>' +
                                $('<span>').text(msg).html() +
                                '</p></div>'
                        );
                        appendMessage('✓ ' + msg, 'result');
                        $tool.hide();
                        state.lastToolCall = null;
                        if (userMsg) {
                            sendChat(userMsg, res.data, executedName, { silent: true });
                        }
                    } else {
                        $result.html(
                            '<div class="notice notice-error inline" style="margin:0;"><p>' +
                                $('<span>')
                                    .text(res.data || 'Erreur')
                                    .html() +
                                '</p></div>'
                        );
                    }
                },
                error: function () {
                    $btn.prop('disabled', false).text('Confirmer et exécuter');
                    closeModal();
                    $result.html(
                        '<div class="notice notice-error inline" style="margin:0;"><p>Erreur réseau.</p></div>'
                    );
                },
            });
        });

        $modalCancel.on('click', function () {
            closeModal();
        });

        $dismissBtn.on('click', function () {
            state.lastToolCall = null;
            state.lastToolResult = null;
            $tool.hide();
        });
    });
})(jQuery);
