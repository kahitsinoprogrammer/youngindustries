@extends('layouts/default')

@section('title')
    {{ trans('general.asset_request') }}
    @parent
@stop

@section('content')
    @php($user = auth()->user())

    <form action="{{ route('asset-request.store') }}" class="form-horizontal" method="POST">
        @csrf

        <div class="row">
            <div class="col-md-12">
                <div class="box box-default">
                    <div class="box-header with-border">
                        <h3 class="box-title">Asset Request Form</h3>
                    </div>
                    <div class="box-body">
                        <div class="form-group">
                            <label class="col-md-3 control-label" for="employeeNumber">Employee Number</label>
                            <div class="col-md-6">
                                <p class="form-control-static" id="employeeNumber">{{ $user->id }}</p>
                                <p class="help-block">Recorded automatically from the currently logged-in user.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-6">
                <div class="box box-default">
                    <div class="box-header with-border">
                        <h3 class="box-title">Asset Details</h3>
                    </div>
                    <div class="box-body">
                        <div class="form-group {{ $errors->has('assetDetails.assetType') ? 'has-error' : '' }}">
                            <label class="col-md-4 control-label" for="assetType">Asset Type</label>
                            <div class="col-md-8">
                                <input class="form-control" id="assetType" maxlength="100" name="assetDetails[assetType]" type="text" value="{{ old('assetDetails.assetType') }}">
                                {!! $errors->first('assetDetails.assetType', '<span class="alert-msg" aria-hidden="true">:message</span>') !!}
                            </div>
                        </div>

                        <div class="form-group {{ $errors->has('assetDetails.assetName') ? 'has-error' : '' }}">
                            <label class="col-md-4 control-label" for="assetName">Asset Name</label>
                            <div class="col-md-8">
                                <input class="form-control" id="assetName" maxlength="191" name="assetDetails[assetName]" type="text" value="{{ old('assetDetails.assetName') }}">
                                {!! $errors->first('assetDetails.assetName', '<span class="alert-msg" aria-hidden="true">:message</span>') !!}
                            </div>
                        </div>

                        <div class="form-group {{ $errors->has('assetDetails.description') ? 'has-error' : '' }}">
                            <label class="col-md-4 control-label" for="assetDescription">Description</label>
                            <div class="col-md-8">
                                <textarea class="form-control" id="assetDescription" name="assetDetails[description]" rows="4">{{ old('assetDetails.description') }}</textarea>
                                {!! $errors->first('assetDetails.description', '<span class="alert-msg" aria-hidden="true">:message</span>') !!}
                            </div>
                        </div>

                        <div class="form-group {{ $errors->has('assetDetails.specifications') ? 'has-error' : '' }}">
                            <label class="col-md-4 control-label" for="specifications">Specifications</label>
                            <div class="col-md-8">
                                <textarea class="form-control" id="specifications" name="assetDetails[specifications]" rows="4">{{ old('assetDetails.specifications') }}</textarea>
                                {!! $errors->first('assetDetails.specifications', '<span class="alert-msg" aria-hidden="true">:message</span>') !!}
                            </div>
                        </div>

                        <div class="form-group {{ $errors->has('assetDetails.quantity') ? 'has-error' : '' }}">
                            <label class="col-md-4 control-label" for="quantity">Quantity</label>
                            <div class="col-md-8">
                                <input class="form-control" id="quantity" min="1" name="assetDetails[quantity]" type="number" value="{{ old('assetDetails.quantity', 1) }}">
                                {!! $errors->first('assetDetails.quantity', '<span class="alert-msg" aria-hidden="true">:message</span>') !!}
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <div class="box box-default">
                    <div class="box-header with-border">
                        <h3 class="box-title">Request Details</h3>
                    </div>
                    <div class="box-body">
                        <div class="form-group {{ $errors->has('requestDetails.reason') ? 'has-error' : '' }}">
                            <label class="col-md-4 control-label" for="reason">Reason</label>
                            <div class="col-md-8">
                                <textarea class="form-control" id="reason" name="requestDetails[reason]" rows="4">{{ old('requestDetails.reason') }}</textarea>
                                {!! $errors->first('requestDetails.reason', '<span class="alert-msg" aria-hidden="true">:message</span>') !!}
                            </div>
                        </div>

                        <div class="form-group {{ $errors->has('requestDetails.requestType') ? 'has-error' : '' }}">
                            <label class="col-md-4 control-label" for="requestType">Request Type</label>
                            <div class="col-md-8">
                                <select class="form-control" id="requestType" name="requestDetails[requestType]">
                                    <option value="">Select request type</option>
                                    <option value="New" {{ old('requestDetails.requestType') === 'New' ? 'selected' : '' }}>New</option>
                                    <option value="Replacement" {{ old('requestDetails.requestType') === 'Replacement' ? 'selected' : '' }}>Replacement</option>
                                    <option value="Upgrade" {{ old('requestDetails.requestType') === 'Upgrade' ? 'selected' : '' }}>Upgrade</option>
                                </select>
                                {!! $errors->first('requestDetails.requestType', '<span class="alert-msg" aria-hidden="true">:message</span>') !!}
                            </div>
                        </div>

                        <div class="form-group {{ $errors->has('timeline.neededBy') ? 'has-error' : '' }}">
                            <label class="col-md-4 control-label" for="neededBy">Needed By</label>
                            <div class="col-md-8">
                                <input class="form-control" id="neededBy" name="timeline[neededBy]" type="date" value="{{ old('timeline.neededBy') }}">
                                {!! $errors->first('timeline.neededBy', '<span class="alert-msg" aria-hidden="true">:message</span>') !!}
                            </div>
                        </div>

                        <div class="form-group {{ $errors->has('timeline.priorityLevel') ? 'has-error' : '' }}">
                            <label class="col-md-4 control-label" for="priorityLevel">Priority Level</label>
                            <div class="col-md-8">
                                <select class="form-control" id="priorityLevel" name="timeline[priorityLevel]">
                                    <option value="">Select priority</option>
                                    <option value="Low" {{ old('timeline.priorityLevel') === 'Low' ? 'selected' : '' }}>Low</option>
                                    <option value="Medium" {{ old('timeline.priorityLevel') === 'Medium' ? 'selected' : '' }}>Medium</option>
                                    <option value="High" {{ old('timeline.priorityLevel') === 'High' ? 'selected' : '' }}>High</option>
                                    <option value="Critical" {{ old('timeline.priorityLevel') === 'Critical' ? 'selected' : '' }}>Critical</option>
                                </select>
                                {!! $errors->first('timeline.priorityLevel', '<span class="alert-msg" aria-hidden="true">:message</span>') !!}
                            </div>
                        </div>

                        <div class="form-group {{ $errors->has('budget.estimatedCost') ? 'has-error' : '' }}">
                            <label class="col-md-4 control-label" for="estimatedCost">Estimated Cost</label>
                            <div class="col-md-8">
                                <input class="form-control" id="estimatedCost" min="0" name="budget[estimatedCost]" step="0.01" type="number" value="{{ old('budget.estimatedCost') }}">
                                {!! $errors->first('budget.estimatedCost', '<span class="alert-msg" aria-hidden="true">:message</span>') !!}
                            </div>
                        </div>

                        <div class="form-group {{ $errors->has('budget.budgetCode') ? 'has-error' : '' }}">
                            <label class="col-md-4 control-label" for="budgetCode">Budget Code</label>
                            <div class="col-md-8">
                                <input class="form-control" id="budgetCode" maxlength="100" name="budget[budgetCode]" type="text" value="{{ old('budget.budgetCode') }}">
                                {!! $errors->first('budget.budgetCode', '<span class="alert-msg" aria-hidden="true">:message</span>') !!}
                            </div>
                        </div>
                    </div>

                    <div class="box-footer">
                        <button class="btn btn-primary pull-right" type="submit">Submit Asset Request</button>
                    </div>
                </div>
            </div>
        </div>
    </form>
@stop
