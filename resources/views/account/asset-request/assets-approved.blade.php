@extends('layouts/default')

@section('title')
    {{ trans('general.assets_approved') }}
    @parent
@stop

@section('content')
    <div class="row">
        <div class="col-md-12">
            @if (!empty($mongoError))
                <div class="alert alert-danger">
                    {{ $mongoError }}
                </div>
            @endif

            @if ($requests->isEmpty())
                <div class="alert alert-info">
                    There are no approved asset requests right now.
                </div>
            @endif
        </div>
    </div>

    @foreach ($requests as $requestItem)
        @php
            $statusClass = match($requestItem['approval_status']) {
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
                </div>
            </div>
        </div>
    @endforeach
@stop
