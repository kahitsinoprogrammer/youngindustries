@extends('layouts/default')

@section('title')
    {{ trans('general.asset_specification') }}
    @parent
@stop

@section('content')
    @php
        $statusClass = match($requestItem['approval_status']) {
            'approved' => 'label-success',
            'rejected' => 'label-danger',
            default => 'label-warning',
        };
        $hasTechnicalSupportSpecification = trim($requestItem['technical_support_specification']) !== '';
        $isDeployed = $requestItem['technical_support_deployment_status'] === 'deployed';
    @endphp

    <div class="row">
        <div class="col-md-12">
            <a class="btn btn-default" href="{{ route('asset-request.asset-specification') }}">
                {{ trans('general.back') }}
            </a>
        </div>
    </div>

    <div class="row" style="margin-top: 15px;">
        <div class="col-md-12">
            <div class="box box-default">
                <div class="box-header with-border">
                    <h3 class="box-title">
                        {{ $requestItem['assetDetails']['assetName'] ?: 'Asset Request' }}
                    </h3>
                    <div class="pull-right">
                        <span class="label {{ $statusClass }}">{{ ucfirst($requestItem['approval_status']) }}</span>
                    </div>
                </div>
                <div class="box-body">
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>Requested By:</strong> {{ $requestItem['requester_name'] }}</p>
                            <p><strong>Approver:</strong> {{ $requestItem['approver_name'] }}</p>
                            <p><strong>Asset Type:</strong> {{ $requestItem['assetDetails']['assetType'] }}</p>
                            <p><strong>Quantity:</strong> {{ $requestItem['assetDetails']['quantity'] }}</p>
                            <p><strong>Request Type:</strong> {{ $requestItem['requestDetails']['requestType'] }}</p>
                            <p><strong>Priority:</strong> {{ $requestItem['timeline']['priorityLevel'] }}</p>
                            <p><strong>Needed By:</strong> {{ $requestItem['timeline']['neededBy'] }}</p>
                            <p><strong>Estimated Cost:</strong> {{ number_format($requestItem['budget']['estimatedCost'], 2) }}</p>
                            <p><strong>Budget Code:</strong> {{ $requestItem['budget']['budgetCode'] }}</p>
                        </div>

                        <div class="col-md-6">
                            <p><strong>Description:</strong><br>{{ $requestItem['assetDetails']['description'] }}</p>
                            <p><strong>Specifications:</strong><br>{{ $requestItem['assetDetails']['specifications'] ?: 'None provided.' }}</p>
                            <p><strong>Reason:</strong><br>{{ $requestItem['requestDetails']['reason'] }}</p>
                            <p><strong>Submitted At:</strong> {{ $requestItem['created_at'] }}</p>
                            @if ($requestItem['reviewed_at'])
                                <p><strong>Reviewed At:</strong> {{ $requestItem['reviewed_at'] }}</p>
                            @endif
                            @if ($requestItem['reviewed_by_name'])
                                <p><strong>Reviewed By:</strong> {{ $requestItem['reviewed_by_name'] }}</p>
                            @endif
                            @if ($requestItem['approver_remarks'])
                                <p><strong>Approver Remarks:</strong><br>{{ $requestItem['approver_remarks'] }}</p>
                            @endif
                        </div>
                    </div>
                </div>

                <form action="{{ route('asset-request.asset-specification.store', $requestItem['id']) }}" class="form-horizontal" method="POST">
                    @csrf
                    <input type="hidden" name="request_id" value="{{ $requestItem['id'] }}">

                    <div class="box-body" style="border-top: 1px solid #f4f4f4;">
                        <div class="form-group {{ $errors->has('technical_support_specification') ? 'has-error' : '' }}">
                            <label class="col-md-2 control-label" for="technical_support_specification">
                                Technical Support Specification
                            </label>
                            <div class="col-md-8">
                                <textarea class="form-control" id="technical_support_specification" name="technical_support_specification" rows="6">{{ old('technical_support_specification', $requestItem['technical_support_specification']) }}</textarea>
                                <p class="help-block">Save the final technical specification that should be used for this approved request.</p>
                                {!! $errors->first('technical_support_specification', '<span class="alert-msg" aria-hidden="true">:message</span>') !!}
                            </div>
                        </div>

                        @if ($requestItem['technical_support_specification_updated_by_name'] || $requestItem['technical_support_specification_updated_at'])
                            <div class="form-group">
                                <label class="col-md-2 control-label">Last Saved</label>
                                <div class="col-md-8">
                                    <p class="form-control-static">
                                        {{ $requestItem['technical_support_specification_updated_by_name'] ?: 'Technical Support' }}
                                        @if ($requestItem['technical_support_specification_updated_at'])
                                            on {{ $requestItem['technical_support_specification_updated_at'] }}
                                        @endif
                                    </p>
                                </div>
                            </div>
                        @endif
                    </div>

                    <div class="box-footer">
                        <button class="btn btn-primary" type="submit">Save Technical Support Specification</button>
                    </div>
                </form>

                <form action="{{ route('asset-request.asset-specification.deploy', $requestItem['id']) }}" class="form-horizontal" method="POST">
                    @csrf
                    <input type="hidden" name="request_id" value="{{ $requestItem['id'] }}">

                    <div class="box-body" style="border-top: 1px solid #f4f4f4;">
                        <div class="form-group">
                            <label class="col-md-2 control-label">Deploy Action</label>
                            <div class="col-md-8">
                                <button class="btn btn-success" type="submit" {{ (! $hasTechnicalSupportSpecification || $isDeployed) ? 'disabled' : '' }}>
                                    {{ $isDeployed ? 'Already Deployed' : 'Tag as Deployed' }}
                                </button>
                                @if (! $hasTechnicalSupportSpecification)
                                    <p class="help-block">Add and save the technical support specification first before tagging this request as deployed.</p>
                                @elseif ($isDeployed)
                                    <p class="help-block">This request has already been tagged as deployed.</p>
                                @else
                                    <p class="help-block">Use this after the approved request has been fully prepared and deployed.</p>
                                @endif
                            </div>
                        </div>

                        @if ($requestItem['technical_support_deployed_by_name'] || $requestItem['technical_support_deployed_at'])
                            <div class="form-group">
                                <label class="col-md-2 control-label">Deployment</label>
                                <div class="col-md-8">
                                    <p class="form-control-static">
                                        {{ $requestItem['technical_support_deployed_by_name'] ?: 'Technical Support' }}
                                        @if ($requestItem['technical_support_deployed_at'])
                                            tagged this request as deployed on {{ $requestItem['technical_support_deployed_at'] }}
                                        @endif
                                    </p>
                                </div>
                            </div>
                        @endif
                    </div>
                </form>
            </div>
        </div>
    </div>
@stop
