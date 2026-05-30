@extends('layouts/default')

@section('title')
    {{ trans('general.tech_support_jira_diagnostics') }}
    @parent
@stop

@section('content')
    @php
        $checks = $diagnostics['checks'] ?? [];
        $configCheck = $checks['config'] ?? ['ok' => false, 'message' => 'Missing diagnostic data.'];
        $currentUserCheck = $checks['current_user'] ?? ['ok' => false, 'message' => 'Missing diagnostic data.', 'data' => []];
        $projectCheck = $checks['project'] ?? ['ok' => false, 'message' => 'Missing diagnostic data.', 'data' => []];
        $permissionsCheck = $checks['permissions'] ?? ['ok' => false, 'message' => 'Missing diagnostic data.', 'data' => []];
        $issueTypesCheck = $checks['issue_types'] ?? ['ok' => false, 'message' => 'Missing diagnostic data.', 'data' => []];
        $hasBlockingError = !($configCheck['ok'] ?? false) || !($currentUserCheck['ok'] ?? false) || !($projectCheck['ok'] ?? false) || !($permissionsCheck['ok'] ?? false) || !($issueTypesCheck['ok'] ?? false);
        $statusClass = $hasBlockingError ? 'alert-danger' : 'alert-success';
        $statusMessage = $hasBlockingError
            ? 'One or more Jira diagnostics failed. Review the checks below to find the blocker.'
            : 'All Jira diagnostics passed. The Jira connection, project permissions, and issue types look good.';
    @endphp

    <div class="row">
        <div class="col-md-12">
            <div class="box box-default">
                <div class="box-header with-border">
                    <h3 class="box-title">{{ trans('general.tech_support_jira_diagnostics') }}</h3>
                </div>
                <div class="box-body">
                    <div class="alert alert-info">
                        {{ trans('general.tech_support_jira_diagnostics_description') }}
                    </div>
                    <div class="alert {{ $statusClass }}">
                        {{ $statusMessage }}
                    </div>
                    @if (!empty($diagnostics['errors']))
                        <div class="alert alert-warning">
                            <strong>Collected Errors:</strong>
                            <ul style="margin-top: 10px; margin-bottom: 0;">
                                @foreach ($diagnostics['errors'] as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-12">
            <div class="box box-default">
                <div class="box-header with-border">
                    <h3 class="box-title">Configured Values</h3>
                </div>
                <div class="box-body">
                    <table class="table table-bordered table-striped">
                        <tbody>
                        <tr>
                            <th style="width: 220px;">Jira Base URL</th>
                            <td>{{ data_get($diagnostics, 'config.base_url', 'Not configured') ?: 'Not configured' }}</td>
                        </tr>
                        <tr>
                            <th>Jira Email</th>
                            <td>{{ data_get($diagnostics, 'config.email', 'Not configured') ?: 'Not configured' }}</td>
                        </tr>
                        <tr>
                            <th>Jira Project Key</th>
                            <td>{{ data_get($diagnostics, 'config.project_key', 'Not configured') ?: 'Not configured' }}</td>
                        </tr>
                        <tr>
                            <th>Timeout</th>
                            <td>{{ data_get($diagnostics, 'config.timeout', 0) }} second(s)</td>
                        </tr>
                        <tr>
                            <th>Configured Issue Types</th>
                            <td>{{ implode(', ', data_get($diagnostics, 'config.issue_types', [])) ?: 'None configured' }}</td>
                        </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    @foreach ([
        'Configuration' => $configCheck,
        'Current Jira User' => $currentUserCheck,
        'Project Access' => $projectCheck,
        'Permissions' => $permissionsCheck,
        'Issue Types' => $issueTypesCheck,
    ] as $title => $check)
        <div class="row">
            <div class="col-md-12">
                <div class="box box-default">
                    <div class="box-header with-border">
                        <h3 class="box-title">{{ $title }}</h3>
                        <div class="pull-right">
                            <span class="label {{ ($check['ok'] ?? false) ? 'label-success' : 'label-danger' }}">
                                {{ ($check['ok'] ?? false) ? 'Pass' : 'Fail' }}
                            </span>
                        </div>
                    </div>
                    <div class="box-body">
                        <p>{{ $check['message'] ?? 'No message returned.' }}</p>

                        @if ($title === 'Current Jira User' && !empty($check['data']))
                            <table class="table table-bordered table-striped">
                                <tbody>
                                <tr>
                                    <th style="width: 220px;">Display Name</th>
                                    <td>{{ $check['data']['display_name'] ?: 'Not returned' }}</td>
                                </tr>
                                <tr>
                                    <th>Email Address</th>
                                    <td>{{ $check['data']['email_address'] ?: 'Not returned by Jira' }}</td>
                                </tr>
                                <tr>
                                    <th>Account ID</th>
                                    <td>{{ $check['data']['account_id'] ?: 'Not returned' }}</td>
                                </tr>
                                <tr>
                                    <th>Active</th>
                                    <td>{{ !empty($check['data']['active']) ? 'Yes' : 'No' }}</td>
                                </tr>
                                </tbody>
                            </table>
                        @endif

                        @if ($title === 'Project Access' && !empty($check['data']))
                            <table class="table table-bordered table-striped">
                                <tbody>
                                <tr>
                                    <th style="width: 220px;">Project Name</th>
                                    <td>{{ $check['data']['name'] ?: 'Not returned' }}</td>
                                </tr>
                                <tr>
                                    <th>Project Key</th>
                                    <td>{{ $check['data']['key'] ?: 'Not returned' }}</td>
                                </tr>
                                <tr>
                                    <th>Project ID</th>
                                    <td>{{ $check['data']['id'] ?: 'Not returned' }}</td>
                                </tr>
                                <tr>
                                    <th>Project Type</th>
                                    <td>{{ $check['data']['project_type_key'] ?: 'Not returned' }}</td>
                                </tr>
                                </tbody>
                            </table>
                        @endif

                        @if ($title === 'Permissions' && !empty($check['data']))
                            <table class="table table-bordered table-striped">
                                <tbody>
                                <tr>
                                    <th style="width: 220px;">Browse Projects</th>
                                    <td>{{ !empty($check['data']['browse_projects']) ? 'Yes' : 'No' }}</td>
                                </tr>
                                <tr>
                                    <th>Create Issues</th>
                                    <td>{{ !empty($check['data']['create_issues']) ? 'Yes' : 'No' }}</td>
                                </tr>
                                </tbody>
                            </table>
                        @endif

                        @if ($title === 'Issue Types' && !empty($check['data']))
                            <div class="row">
                                <div class="col-md-4">
                                    <h4>Configured</h4>
                                    <ul>
                                        @forelse ($check['data']['configured'] ?? [] as $issueType)
                                            <li>{{ $issueType }}</li>
                                        @empty
                                            <li>No configured issue types.</li>
                                        @endforelse
                                    </ul>
                                </div>
                                <div class="col-md-4">
                                    <h4>Available In Jira Project</h4>
                                    <ul>
                                        @forelse ($check['data']['available'] ?? [] as $issueType)
                                            <li>{{ $issueType }}</li>
                                        @empty
                                            <li>No issue types returned.</li>
                                        @endforelse
                                    </ul>
                                </div>
                                <div class="col-md-4">
                                    <h4>Missing</h4>
                                    <ul>
                                        @forelse ($check['data']['missing'] ?? [] as $issueType)
                                            <li>{{ $issueType }}</li>
                                        @empty
                                            <li>None.</li>
                                        @endforelse
                                    </ul>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    @endforeach
@stop
