@extends('layouts/default')

@section('title')
    {{ trans('general.tech_support_request_ticket') }}
    @parent
@stop

@section('content')
    <form action="{{ route('tech-support.request-ticket.store') }}" class="form-horizontal" method="POST">
        @csrf

        <div class="row">
            <div class="col-md-12">
                <div class="box box-default">
                    <div class="box-header with-border">
                        <h3 class="box-title">{{ trans('general.tech_support_request_ticket') }}</h3>
                    </div>
                    <div class="box-body">
                        <div class="alert alert-info">
                            {{ trans('general.tech_support_request_ticket_description') }}
                        </div>
                    </div>
                </div>
            </div>
        </div>

        @if ($ticketRequestPreview)
            <div class="row">
                <div class="col-md-12">
                    <div class="box box-success">
                        <div class="box-header with-border">
                            <h3 class="box-title">Firebase Save Preview</h3>
                        </div>
                        <div class="box-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <p><strong>Firebase Document ID:</strong> {{ $ticketRequestPreview['firebase_document_id'] }}</p>
                                    <p><strong>Status:</strong> {{ $ticketRequestPreview['status'] }}</p>
                                    <p><strong>Issue Type:</strong> {{ $ticketRequestPreview['issue_type'] }}</p>
                                    <p><strong>Priority:</strong> {{ $ticketRequestPreview['priority'] }}</p>
                                    <p><strong>Summary:</strong> {{ $ticketRequestPreview['summary'] }}</p>
                                </div>
                                <div class="col-md-6">
                                    <p><strong>Requester:</strong> {{ $ticketRequestPreview['requester_name'] }} ({{ $ticketRequestPreview['requester_email'] }})</p>
                                    <p><strong>Item:</strong> {{ $ticketRequestPreview['item_name'] }}</p>
                                    <p><strong>Description:</strong><br>{{ $ticketRequestPreview['description'] }}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @endif

        <div class="row">
            <div class="col-md-6">
                <div class="box box-default">
                    <div class="box-header with-border">
                        <h3 class="box-title">Requester Information</h3>
                    </div>
                    <div class="box-body">
                        <div class="form-group {{ $errors->has('requester_name') ? 'has-error' : '' }}">
                            <label class="col-md-4 control-label" for="requester_name">Requester Name</label>
                            <div class="col-md-8">
                                <input class="form-control" id="requester_name" maxlength="191" name="requester_name" type="text" value="{{ old('requester_name', $user->display_name ?: $user->username) }}">
                                {!! $errors->first('requester_name', '<span class="alert-msg" aria-hidden="true">:message</span>') !!}
                            </div>
                        </div>

                        <div class="form-group {{ $errors->has('requester_email') ? 'has-error' : '' }}">
                            <label class="col-md-4 control-label" for="requester_email">Requester Email</label>
                            <div class="col-md-8">
                                <input class="form-control" id="requester_email" maxlength="191" name="requester_email" type="email" value="{{ old('requester_email', $user->email) }}">
                                {!! $errors->first('requester_email', '<span class="alert-msg" aria-hidden="true">:message</span>') !!}
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="col-md-4 control-label" for="employee_number">Employee Number</label>
                            <div class="col-md-8">
                                <p class="form-control-static" id="employee_number">{{ $user->id }}</p>
                            </div>
                        </div>

                        <div class="form-group {{ $errors->has('department') ? 'has-error' : '' }}">
                            <label class="col-md-4 control-label" for="department">Department</label>
                            <div class="col-md-8">
                                <input class="form-control" id="department" maxlength="191" name="department" type="text" value="{{ old('department') }}">
                                {!! $errors->first('department', '<span class="alert-msg" aria-hidden="true">:message</span>') !!}
                            </div>
                        </div>

                        <div class="form-group {{ $errors->has('contact_number') ? 'has-error' : '' }}">
                            <label class="col-md-4 control-label" for="contact_number">Contact Number</label>
                            <div class="col-md-8">
                                <input class="form-control" id="contact_number" maxlength="50" name="contact_number" type="text" value="{{ old('contact_number') }}">
                                {!! $errors->first('contact_number', '<span class="alert-msg" aria-hidden="true">:message</span>') !!}
                            </div>
                        </div>
                    </div>
                </div>

                <div class="box box-default">
                    <div class="box-header with-border">
                        <h3 class="box-title">Asset or Item</h3>
                    </div>
                    <div class="box-body">
                        <div class="form-group {{ $errors->has('item_name') ? 'has-error' : '' }}">
                            <label class="col-md-4 control-label" for="item_name">Item Name</label>
                            <div class="col-md-8">
                                <input class="form-control" id="item_name" maxlength="191" name="item_name" type="text" value="{{ old('item_name') }}">
                                {!! $errors->first('item_name', '<span class="alert-msg" aria-hidden="true">:message</span>') !!}
                            </div>
                        </div>

                        <div class="form-group {{ $errors->has('asset_tag') ? 'has-error' : '' }}">
                            <label class="col-md-4 control-label" for="asset_tag">Asset Tag</label>
                            <div class="col-md-8">
                                <input class="form-control" id="asset_tag" maxlength="100" name="asset_tag" type="text" value="{{ old('asset_tag') }}">
                                {!! $errors->first('asset_tag', '<span class="alert-msg" aria-hidden="true">:message</span>') !!}
                            </div>
                        </div>

                        <div class="form-group {{ $errors->has('serial_number') ? 'has-error' : '' }}">
                            <label class="col-md-4 control-label" for="serial_number">Serial Number</label>
                            <div class="col-md-8">
                                <input class="form-control" id="serial_number" maxlength="100" name="serial_number" type="text" value="{{ old('serial_number') }}">
                                {!! $errors->first('serial_number', '<span class="alert-msg" aria-hidden="true">:message</span>') !!}
                            </div>
                        </div>

                        <div class="form-group {{ $errors->has('location') ? 'has-error' : '' }}">
                            <label class="col-md-4 control-label" for="location">Location</label>
                            <div class="col-md-8">
                                <input class="form-control" id="location" maxlength="191" name="location" type="text" value="{{ old('location') }}">
                                {!! $errors->first('location', '<span class="alert-msg" aria-hidden="true">:message</span>') !!}
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <div class="box box-default">
                    <div class="box-header with-border">
                        <h3 class="box-title">Ticket Details</h3>
                    </div>
                    <div class="box-body">
                        <div class="form-group {{ $errors->has('jira_issue_type') ? 'has-error' : '' }}">
                            <label class="col-md-4 control-label" for="jira_issue_type">Jira Issue Type</label>
                            <div class="col-md-8">
                                <select class="form-control" id="jira_issue_type" name="jira_issue_type">
                                    <option value="">Select issue type</option>
                                    @foreach (['Task', 'Bug', 'Incident', 'Service Request'] as $issueType)
                                        <option value="{{ $issueType }}" {{ old('jira_issue_type') === $issueType ? 'selected' : '' }}>{{ $issueType }}</option>
                                    @endforeach
                                </select>
                                {!! $errors->first('jira_issue_type', '<span class="alert-msg" aria-hidden="true">:message</span>') !!}
                            </div>
                        </div>

                        <div class="form-group {{ $errors->has('issue_category') ? 'has-error' : '' }}">
                            <label class="col-md-4 control-label" for="issue_category">Issue Category</label>
                            <div class="col-md-8">
                                <select class="form-control" id="issue_category" name="issue_category">
                                    <option value="">Select category</option>
                                    <option value="repair" {{ old('issue_category') === 'repair' ? 'selected' : '' }}>Repair</option>
                                    <option value="hardware" {{ old('issue_category') === 'hardware' ? 'selected' : '' }}>Hardware</option>
                                    <option value="software" {{ old('issue_category') === 'software' ? 'selected' : '' }}>Software</option>
                                    <option value="network" {{ old('issue_category') === 'network' ? 'selected' : '' }}>Network</option>
                                    <option value="account" {{ old('issue_category') === 'account' ? 'selected' : '' }}>Account</option>
                                    <option value="other" {{ old('issue_category') === 'other' ? 'selected' : '' }}>Other</option>
                                </select>
                                {!! $errors->first('issue_category', '<span class="alert-msg" aria-hidden="true">:message</span>') !!}
                            </div>
                        </div>

                        <div class="form-group {{ $errors->has('priority') ? 'has-error' : '' }}">
                            <label class="col-md-4 control-label" for="priority">Priority</label>
                            <div class="col-md-8">
                                <select class="form-control" id="priority" name="priority">
                                    <option value="">Select priority</option>
                                    @foreach (['Low', 'Medium', 'High', 'Highest'] as $priority)
                                        <option value="{{ $priority }}" {{ old('priority') === $priority ? 'selected' : '' }}>{{ $priority }}</option>
                                    @endforeach
                                </select>
                                {!! $errors->first('priority', '<span class="alert-msg" aria-hidden="true">:message</span>') !!}
                            </div>
                        </div>

                        <div class="form-group {{ $errors->has('summary') ? 'has-error' : '' }}">
                            <label class="col-md-4 control-label" for="summary">Ticket Summary</label>
                            <div class="col-md-8">
                                <input class="form-control" id="summary" maxlength="191" name="summary" type="text" value="{{ old('summary') }}">
                                <p class="help-block">This maps directly to the Jira issue summary.</p>
                                {!! $errors->first('summary', '<span class="alert-msg" aria-hidden="true">:message</span>') !!}
                            </div>
                        </div>

                        <div class="form-group {{ $errors->has('description') ? 'has-error' : '' }}">
                            <label class="col-md-4 control-label" for="description">Problem Description</label>
                            <div class="col-md-8">
                                <textarea class="form-control" id="description" name="description" rows="10">{{ old('description') }}</textarea>
                                <p class="help-block">This becomes the main Jira issue description for the repair request.</p>
                                {!! $errors->first('description', '<span class="alert-msg" aria-hidden="true">:message</span>') !!}
                            </div>
                        </div>
                    </div>

                    <div class="box-footer">
                        <button class="btn btn-primary pull-right" type="submit">Submit Ticket Request</button>
                    </div>
                </div>
            </div>
        </div>
    </form>
@stop
