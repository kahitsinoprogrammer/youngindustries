@extends('layouts/default')

@section('title')
    {{ trans('general.asset_chatbot') }}
    @parent
@stop

@push('css')
    <style>
        .asset-chat-shell {
            display: grid;
            gap: 20px;
            grid-template-columns: minmax(240px, 320px) minmax(0, 1fr);
        }

        .asset-chat-sidebar,
        .asset-chat-panel {
            background: var(--box-bg);
            border-radius: 8px;
        }

        .asset-chat-thread {
            background: linear-gradient(180deg, rgba(95, 164, 204, 0.08), rgba(95, 164, 204, 0.02));
            border: 1px solid var(--table-border-row-color);
            border-radius: 8px;
            display: flex;
            flex-direction: column;
            gap: 12px;
            max-height: 520px;
            min-height: 380px;
            overflow-y: auto;
            padding: 18px;
        }

        .asset-chat-message {
            display: flex;
        }

        .asset-chat-message-user {
            justify-content: flex-end;
        }

        .asset-chat-bubble {
            background: var(--box-bg);
            border: 1px solid var(--table-border-row-color);
            border-radius: 16px;
            color: var(--color-fg);
            max-width: 88%;
            padding: 12px 14px;
            word-break: break-word;
        }

        .asset-chat-message-user .asset-chat-bubble {
            background: var(--main-theme-color);
            border-color: var(--main-theme-color);
            color: #fff;
        }

        .asset-chat-message-assistant .asset-chat-bubble {
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.05);
        }

        .asset-chat-list,
        .asset-chat-breakdown,
        .asset-chat-suggestions {
            margin: 12px 0 0;
            padding: 0;
        }

        .asset-chat-list li,
        .asset-chat-breakdown li {
            border-top: 1px solid var(--table-border-row-color);
            list-style: none;
            padding: 10px 0 0;
            margin-top: 10px;
        }

        .asset-chat-list li:first-child,
        .asset-chat-breakdown li:first-child {
            border-top: 0;
            margin-top: 0;
            padding-top: 0;
        }

        .asset-chat-meta {
            color: var(--text-help);
            display: block;
            margin-top: 4px;
        }

        .asset-chat-form {
            margin-top: 16px;
        }

        .asset-chat-examples {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 14px;
        }

        .asset-chat-examples .btn,
        .asset-chat-suggestions .btn {
            white-space: normal;
        }

        .asset-chat-suggestions {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }

        .asset-chat-typing {
            color: var(--text-help);
            font-style: italic;
        }

        @media (max-width: 991px) {
            .asset-chat-shell {
                grid-template-columns: 1fr;
            }

            .asset-chat-bubble {
                max-width: 100%;
            }
        }
    </style>
@endpush

@section('content')
    <div class="row">
        <div class="col-md-12">
            <div class="box box-default">
                <div class="box-header with-border">
                    <h3 class="box-title">{{ trans('general.asset_chatbot') }}</h3>
                    <p class="text-muted" style="margin: 8px 0 0;">
                        {{ trans('general.asset_chatbot_description') }}
                    </p>
                </div>
                <div class="box-body">
                    @if ($assistantStatus['available'])
                        <div class="callout callout-success">
                            <h4 style="margin-top: 0;">Ollama connected</h4>
                            <p style="margin-bottom: 0;">
                                Model <strong>{{ $assistantStatus['model'] }}</strong> is available at <code>{{ $assistantStatus['base_url'] }}</code>.
                            </p>
                        </div>
                    @else
                        <div class="callout callout-warning">
                            <h4 style="margin-top: 0;">Ollama not ready yet</h4>
                            <p style="margin-bottom: 8px;">
                                {{ $assistantStatus['message'] }}
                            </p>
                            <p style="margin-bottom: 0;">
                                Set <code>OLLAMA_ENABLED=true</code>, make sure the local Ollama server is running, and pull the configured model <code>{{ $assistantStatus['model'] }}</code>.
                            </p>
                        </div>
                    @endif

                    <div class="asset-chat-shell">
                        <div class="asset-chat-sidebar">
                            <div class="callout callout-info" style="margin: 0;">
                                <h4 style="margin-top: 0;">Try questions like:</h4>
                                <div class="asset-chat-examples">
                                    @foreach ($exampleQuestions as $exampleQuestion)
                                        <button class="btn btn-default btn-sm asset-chat-example" data-question="{{ $exampleQuestion }}" type="button">
                                            {{ $exampleQuestion }}
                                        </button>
                                    @endforeach
                                </div>
                            </div>
                        </div>

                        <div class="asset-chat-panel">
                            <div class="asset-chat-thread" id="asset-chat-thread">
                                <div class="asset-chat-message asset-chat-message-assistant">
                                    <div class="asset-chat-bubble">
                                        Ask naturally about your asset inventory. Ollama interprets the question, then the app queries the database and returns grounded results.
                                    </div>
                                </div>
                            </div>

                            <form class="asset-chat-form" id="asset-chat-form">
                                <div class="input-group">
                                    <input
                                        autocomplete="off"
                                        class="form-control"
                                        id="asset-chat-question"
                                        maxlength="500"
                                        placeholder="Ask about asset inventory..."
                                        type="text"
                                    >
                                    <span class="input-group-btn">
                                        <button class="btn btn-primary" id="asset-chat-submit" type="submit">
                                            Ask
                                        </button>
                                    </span>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@stop

@section('moar_scripts')
    @parent
    <script nonce="{{ csrf_token() }}">
        $(function () {
            var askUrl = @json(route('asset-request.chatbot.ask'));
            var $form = $('#asset-chat-form');
            var $input = $('#asset-chat-question');
            var $submit = $('#asset-chat-submit');
            var $thread = $('#asset-chat-thread');

            function scrollThread() {
                $thread.scrollTop($thread[0].scrollHeight);
            }

            function appendSuggestions($bubble, suggestions) {
                if (!suggestions || !suggestions.length) {
                    return;
                }

                var $suggestions = $('<div class="asset-chat-suggestions"></div>');

                suggestions.forEach(function (question) {
                    $suggestions.append(
                        $('<button type="button" class="btn btn-default btn-xs asset-chat-suggestion"></button>')
                            .attr('data-question', question)
                            .text(question)
                    );
                });

                $bubble.append($suggestions);
            }

            function appendBreakdown($bubble, rows) {
                if (!rows || !rows.length) {
                    return;
                }

                var $list = $('<ul class="asset-chat-breakdown"></ul>');

                rows.forEach(function (row) {
                    $list.append(
                        $('<li></li>').text(row.label + ': ' + row.count)
                    );
                });

                $bubble.append($list);
            }

            function appendItems($bubble, items) {
                if (!items || !items.length) {
                    return;
                }

                var $list = $('<ul class="asset-chat-list"></ul>');

                items.forEach(function (item) {
                    var $entry = $('<li></li>');
                    var $link = $('<a></a>')
                        .attr('href', item.url)
                        .text(item.asset_tag + ' - ' + item.asset_name);

                    $entry.append($link);

                    var metaParts = [];

                    if (item.model) {
                        metaParts.push('Model: ' + item.model);
                    }

                    if (item.assigned_to) {
                        metaParts.push('Assigned to: ' + item.assigned_to);
                    }

                    if (metaParts.length) {
                        $entry.append(
                            $('<span class="asset-chat-meta"></span>').text(metaParts.join(' | '))
                        );
                    }

                    $list.append($entry);
                });

                $bubble.append($list);
            }

            function appendMessage(role, text, payload) {
                var $message = $('<div></div>').addClass('asset-chat-message asset-chat-message-' + role);
                var $bubble = $('<div class="asset-chat-bubble"></div>');

                $bubble.append($('<div></div>').text(text));

                if (payload) {
                    appendBreakdown($bubble, payload.status_breakdown);
                    appendItems($bubble, payload.items);

                    if ((!payload.items || !payload.items.length) && (!payload.status_breakdown || !payload.status_breakdown.length)) {
                        appendSuggestions($bubble, payload.suggestions);
                    }
                }

                $message.append($bubble);
                $thread.append($message);
                scrollThread();
            }

            function removeTyping() {
                $('#asset-chat-typing').remove();
            }

            function setLoading(isLoading) {
                $submit.prop('disabled', isLoading);
                $input.prop('disabled', isLoading);
            }

            function submitQuestion(question) {
                var trimmed = $.trim(question);

                if (!trimmed) {
                    return;
                }

                appendMessage('user', trimmed);
                setLoading(true);

                var $typing = $('<div class="asset-chat-message asset-chat-message-assistant" id="asset-chat-typing"></div>');
                $typing.append(
                    $('<div class="asset-chat-bubble asset-chat-typing"></div>').text('Interpreting your question and searching the inventory...')
                );
                $thread.append($typing);
                scrollThread();

                $.ajax({
                    url: askUrl,
                    method: 'POST',
                    data: {
                        question: trimmed
                    },
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                    }
                }).done(function (response) {
                    removeTyping();
                    appendMessage('assistant', response.reply, response);
                }).fail(function (xhr) {
                    removeTyping();

                    var errorMessage = 'I could not process that question just now.';

                    if (xhr.status === 422 && xhr.responseJSON && xhr.responseJSON.message) {
                        errorMessage = xhr.responseJSON.message;
                    }

                    appendMessage('assistant', errorMessage, null);
                }).always(function () {
                    setLoading(false);
                    $input.trigger('focus');
                });
            }

            $form.on('submit', function (event) {
                event.preventDefault();

                var question = $input.val();
                $input.val('');

                submitQuestion(question);
            });

            $('.asset-chat-example').on('click', function () {
                submitQuestion($(this).data('question'));
            });

            $thread.on('click', '.asset-chat-suggestion', function () {
                submitQuestion($(this).data('question'));
            });
        });
    </script>
@endsection
