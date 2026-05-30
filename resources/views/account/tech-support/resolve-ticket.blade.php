@extends('layouts/default')

@section('title')
    {{ trans('general.tech_support_resolve_ticket') }}
    @parent
@stop

@section('content')
    <div class="row">
        <div class="col-md-12">
            <div class="box box-default">
                <div class="box-header with-border">
                    <h3 class="box-title">{{ trans('general.tech_support_resolve_ticket') }}</h3>
                </div>
                <div class="box-body">
                    <div class="alert alert-info">
                        {{ trans('general.tech_support_resolve_ticket_description') }}
                    </div>
                </div>
            </div>

            @if (!empty($firebaseError))
                <div class="alert alert-danger">
                    {{ $firebaseError }}
                </div>
            @endif

            @if ($requests->isEmpty())
                <div class="alert alert-info">
                    There are no approved ticket requests waiting to be resolved right now.
                </div>
            @endif
        </div>
    </div>

    @foreach ($requests as $requestItem)
        <div class="row">
            <div class="col-md-12">
                <div class="box box-default">
                    <div class="box-header with-border">
                        <h3 class="box-title">
                            {{ $requestItem['ticket']['summary'] ?: 'Approved Ticket Request' }}
                        </h3>
                        <div class="pull-right">
                            <span class="label label-success">{{ ucfirst($requestItem['status']) }}</span>
                        </div>
                    </div>

                    <div class="box-body">
                        <div class="row">
                            <div class="col-md-6">
                                <p><strong>Firebase Document ID:</strong> {{ $requestItem['id'] }}</p>
                                <p><strong>Requester:</strong> {{ $requestItem['requester']['name'] ?: 'Unknown requester' }}</p>
                                <p><strong>Requester Email:</strong> {{ $requestItem['requester']['email'] ?: 'Not provided' }}</p>
                                <p><strong>Employee Number:</strong> {{ $requestItem['requester']['employee_number'] ?: 'Not provided' }}</p>
                                <p><strong>Department:</strong> {{ $requestItem['requester']['department'] ?: 'Not provided' }}</p>
                                <p><strong>Contact Number:</strong> {{ $requestItem['requester']['contact_number'] ?: 'Not provided' }}</p>
                                <p><strong>Item Name:</strong> {{ $requestItem['asset']['item_name'] ?: 'Not provided' }}</p>
                                <p><strong>Asset Tag:</strong> {{ $requestItem['asset']['asset_tag'] ?: 'Not provided' }}</p>
                                <p><strong>Serial Number:</strong> {{ $requestItem['asset']['serial_number'] ?: 'Not provided' }}</p>
                                <p><strong>Location:</strong> {{ $requestItem['asset']['location'] ?: 'Not provided' }}</p>
                            </div>

                            <div class="col-md-6">
                                <p><strong>Jira Issue Type:</strong> {{ $requestItem['ticket']['jira_issue_type'] ?: 'Not provided' }}</p>
                                <p><strong>Issue Category:</strong> {{ ucfirst($requestItem['ticket']['issue_category'] ?: 'not provided') }}</p>
                                <p><strong>Priority:</strong> {{ $requestItem['ticket']['priority'] ?: 'Not provided' }}</p>
                                <p><strong>Jira Ticket:</strong>
                                    @if ($requestItem['jira']['issue_key'] && $requestItem['jira']['issue_url'])
                                        <a href="{{ $requestItem['jira']['issue_url'] }}" rel="noopener noreferrer" target="_blank">{{ $requestItem['jira']['issue_key'] }}</a>
                                    @elseif ($requestItem['jira']['issue_key'])
                                        {{ $requestItem['jira']['issue_key'] }}
                                    @else
                                        Not created yet
                                    @endif
                                </p>
                                <p><strong>Submitted At:</strong> {{ $requestItem['submitted_at'] ?: 'Not provided' }}</p>
                                <p><strong>Approved At:</strong> {{ $requestItem['reviewed_at'] ?: 'Not provided' }}</p>
                                <p><strong>Approved By:</strong> {{ $requestItem['reviewed_by_name'] ?: 'Not provided' }}</p>
                                @if ($requestItem['approver_remarks'])
                                    <p><strong>Approval Remarks:</strong><br>{{ $requestItem['approver_remarks'] }}</p>
                                @endif
                                <p><strong>Problem Description:</strong><br>{{ $requestItem['ticket']['description'] ?: 'No description provided.' }}</p>
                            </div>
                        </div>
                    </div>

                    <form action="{{ route('tech-support.resolve-ticket.close', $requestItem['id']) }}" class="form-horizontal" method="POST">
                        @csrf
                        <input type="hidden" name="ticket_id" value="{{ $requestItem['id'] }}">

                        <div class="box-body" style="border-top: 1px solid #f4f4f4;">
                            <div class="form-group {{ $errors->has('resolution') && old('ticket_id') === $requestItem['id'] ? 'has-error' : '' }}">
                                <label class="col-md-2 control-label" for="resolution_{{ $requestItem['id'] }}">Resolution</label>
                                <div class="col-md-8">
                                    <textarea class="form-control" id="resolution_{{ $requestItem['id'] }}" name="resolution" rows="4">{{ old('ticket_id') === $requestItem['id'] ? old('resolution') : '' }}</textarea>
                                    <p class="help-block">Add the resolution details before closing this ticket request.</p>
                                    @if (old('ticket_id') === $requestItem['id'])
                                        {!! $errors->first('resolution', '<span class="alert-msg" aria-hidden="true">:message</span>') !!}
                                        {!! $errors->first('ticket_id', '<span class="alert-msg" aria-hidden="true">:message</span>') !!}
                                    @endif
                                </div>
                            </div>
                        </div>

                        <div class="box-footer">
                            <button class="btn btn-primary" type="submit">Close Ticket</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endforeach
@stop
