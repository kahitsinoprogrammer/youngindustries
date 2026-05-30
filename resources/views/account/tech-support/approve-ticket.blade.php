@extends('layouts/default')

@section('title')
    {{ trans('general.tech_support_approve_ticket') }}
    @parent
@stop

@section('content')
    <div class="row">
        <div class="col-md-12">
            <div class="box box-default">
                <div class="box-header with-border">
                    <h3 class="box-title">{{ trans('general.tech_support_approve_ticket') }}</h3>
                </div>
                <div class="box-body">
                    <div class="alert alert-info">
                        {{ trans('general.tech_support_approve_ticket_description') }}
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
                    There are no ticket requests waiting for approval right now.
                </div>
            @endif
        </div>
    </div>

    @foreach ($requests as $requestItem)
        @php
            $statusClass = match($requestItem['status']) {
                'approved' => 'label-success',
                'rejected' => 'label-danger',
                default => 'label-warning',
            };
        @endphp

        <div class="row">
            <div class="col-md-12">
                <div class="box box-default">
                    <div class="box-header with-border">
                        <h3 class="box-title">
                            {{ $requestItem['ticket']['summary'] ?: 'Ticket Request' }}
                        </h3>
                        <div class="pull-right">
                            <span class="label {{ $statusClass }}">{{ ucfirst($requestItem['status']) }}</span>
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
                                <p><strong>Submitted At:</strong> {{ $requestItem['submitted_at'] ?: 'Not provided' }}</p>
                                @if ($requestItem['reviewed_at'])
                                    <p><strong>Reviewed At:</strong> {{ $requestItem['reviewed_at'] }}</p>
                                @endif
                                @if ($requestItem['reviewed_by_name'])
                                    <p><strong>Reviewed By:</strong> {{ $requestItem['reviewed_by_name'] }}</p>
                                @endif
                                @if ($requestItem['approver_remarks'])
                                    <p><strong>Remarks:</strong><br>{{ $requestItem['approver_remarks'] }}</p>
                                @endif
                                <p><strong>Problem Description:</strong><br>{{ $requestItem['ticket']['description'] ?: 'No description provided.' }}</p>
                            </div>
                        </div>
                    </div>

                    @if ($requestItem['status'] === 'pending')
                        <form action="{{ route('tech-support.approve-ticket.review', $requestItem['id']) }}" class="form-horizontal" method="POST">
                            @csrf
                            <input type="hidden" name="ticket_id" value="{{ $requestItem['id'] }}">

                            <div class="box-body" style="border-top: 1px solid #f4f4f4;">
                                <div class="form-group {{ $errors->has('approver_remarks') && old('ticket_id') === $requestItem['id'] ? 'has-error' : '' }}">
                                    <label class="col-md-2 control-label" for="approver_remarks_{{ $requestItem['id'] }}">Remarks</label>
                                    <div class="col-md-8">
                                        <textarea class="form-control" id="approver_remarks_{{ $requestItem['id'] }}" name="approver_remarks" rows="3">{{ old('ticket_id') === $requestItem['id'] ? old('approver_remarks') : '' }}</textarea>
                                        <p class="help-block">Remarks are required when rejecting a ticket request.</p>
                                        @if (old('ticket_id') === $requestItem['id'])
                                            {!! $errors->first('approver_remarks', '<span class="alert-msg" aria-hidden="true">:message</span>') !!}
                                            {!! $errors->first('approval_status', '<span class="alert-msg" aria-hidden="true">:message</span>') !!}
                                            {!! $errors->first('ticket_id', '<span class="alert-msg" aria-hidden="true">:message</span>') !!}
                                        @endif
                                    </div>
                                </div>
                            </div>

                            <div class="box-footer">
                                <button class="btn btn-success" name="approval_status" type="submit" value="approved">Accept</button>
                                <button class="btn btn-danger" name="approval_status" type="submit" value="rejected">Reject</button>
                            </div>
                        </form>
                    @endif
                </div>
            </div>
        </div>
    @endforeach
@stop
