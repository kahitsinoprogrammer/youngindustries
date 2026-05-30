@extends('layouts/default')

@section('title')
    {{ trans('general.tech_support_ticket_status') }}
    @parent
@stop

@section('content')
    <div class="row">
        <div class="col-md-12">
            <div class="box box-default">
                <div class="box-header with-border">
                    <h3 class="box-title">{{ trans('general.tech_support_ticket_status') }}</h3>
                </div>
                <div class="box-body">
                    <div class="alert alert-info">
                        {{ trans('general.tech_support_ticket_status_description') }}
                    </div>
                </div>
            </div>

            @if (!empty($firebaseError))
                <div class="alert alert-danger">
                    {{ $firebaseError }}
                </div>
            @endif
        </div>
    </div>

    <div class="row">
        <div class="col-md-12">
            <div class="box box-default">
                <form action="{{ route('tech-support.ticket-status') }}" class="form-horizontal" method="GET">
                    <div class="box-body">
                        <div class="form-group">
                            <label class="col-md-2 control-label" for="ticket_search">Search</label>
                            <div class="col-md-5">
                                <input class="form-control" id="ticket_search" name="search" placeholder="Search by ticket, Jira key, requester, asset, issue type, remarks, or resolution" type="text" value="{{ $search }}">
                            </div>

                            <label class="col-md-1 control-label" for="ticket_status_filter">Status</label>
                            <div class="col-md-2">
                                <select class="form-control" id="ticket_status_filter" name="status">
                                    <option value="">All Statuses</option>
                                    @foreach ($statusOptions as $value => $label)
                                        <option value="{{ $value }}" {{ $statusFilter === $value ? 'selected' : '' }}>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-md-2">
                                <button class="btn btn-primary" type="submit">Apply</button>
                                <a class="btn btn-default" href="{{ route('tech-support.ticket-status') }}">Clear</a>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-12">
            @if ($requests->isEmpty())
                <div class="alert alert-info">
                    There are no ticket requests matching the current search or filter.
                </div>
            @else
                <div class="box box-default">
                    <div class="box-header with-border">
                        <h3 class="box-title">{{ $requests->count() }} ticket request{{ $requests->count() === 1 ? '' : 's' }}</h3>
                    </div>

                    <div class="box-body table-responsive">
                        <table class="table table-bordered table-striped">
                            <thead>
                            <tr>
                                <th>Status</th>
                                <th>Summary</th>
                                <th>Requester</th>
                                <th>Item</th>
                                <th>Issue Type</th>
                                <th>Priority</th>
                                <th>Jira</th>
                                <th>Submitted</th>
                                <th>Reviewed</th>
                                <th>Closed</th>
                                <th>Resolution</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach ($requests as $requestItem)
                                @php
                                    $statusClass = match($requestItem['status']) {
                                        'approved' => 'label-success',
                                        'rejected' => 'label-danger',
                                        'closed' => 'label-primary',
                                        default => 'label-warning',
                                    };
                                @endphp
                                <tr>
                                    <td>
                                        <span class="label {{ $statusClass }}">{{ ucfirst($requestItem['status']) }}</span>
                                    </td>
                                    <td>
                                        <strong>{{ $requestItem['ticket']['summary'] ?: 'Ticket Request' }}</strong><br>
                                        <small>{{ $requestItem['ticket']['description'] ?: 'No description provided.' }}</small><br>
                                        <small><strong>ID:</strong> {{ $requestItem['id'] }}</small>
                                    </td>
                                    <td>
                                        {{ $requestItem['requester']['name'] ?: 'Unknown requester' }}<br>
                                        <small>{{ $requestItem['requester']['email'] ?: 'No email provided' }}</small>
                                    </td>
                                    <td>
                                        {{ $requestItem['asset']['item_name'] ?: 'Not provided' }}<br>
                                        <small>Asset Tag: {{ $requestItem['asset']['asset_tag'] ?: 'N/A' }}</small>
                                    </td>
                                    <td>
                                        {{ $requestItem['ticket']['jira_issue_type'] ?: 'Not provided' }}<br>
                                        <small>Category: {{ ucfirst($requestItem['ticket']['issue_category'] ?: 'not provided') }}</small>
                                    </td>
                                    <td>{{ $requestItem['ticket']['priority'] ?: 'Not provided' }}</td>
                                    <td>
                                        @if ($requestItem['jira']['issue_key'] && $requestItem['jira']['issue_url'])
                                            <a href="{{ $requestItem['jira']['issue_url'] }}" rel="noopener noreferrer" target="_blank">{{ $requestItem['jira']['issue_key'] }}</a>
                                        @elseif ($requestItem['jira']['issue_key'])
                                            {{ $requestItem['jira']['issue_key'] }}
                                        @else
                                            <span class="text-muted">Not created yet</span>
                                        @endif
                                    </td>
                                    <td>{{ $requestItem['submitted_at'] ?: 'Not provided' }}</td>
                                    <td>
                                        @if ($requestItem['reviewed_at'])
                                            {{ $requestItem['reviewed_at'] }}<br>
                                            <small>{{ $requestItem['reviewed_by_name'] ?: 'Reviewer not recorded' }}</small>
                                        @else
                                            <span class="text-muted">Not reviewed yet</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if ($requestItem['closed_at'])
                                            {{ $requestItem['closed_at'] }}<br>
                                            <small>{{ $requestItem['closed_by_name'] ?: 'Closer not recorded' }}</small>
                                        @else
                                            <span class="text-muted">Not closed yet</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if ($requestItem['resolution'])
                                            {{ $requestItem['resolution'] }}
                                        @elseif ($requestItem['approver_remarks'])
                                            <small>Approval remarks: {{ $requestItem['approver_remarks'] }}</small>
                                        @else
                                            <span class="text-muted">No resolution yet</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif
        </div>
    </div>
@stop
