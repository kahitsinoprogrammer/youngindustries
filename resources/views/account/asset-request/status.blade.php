@extends('layouts/default')

@section('title')
    {{ trans('general.asset_request_status') }}
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
        </div>
    </div>

    <div class="row">
        <div class="col-md-12">
            <div class="box box-default">
                <div class="box-header with-border">
                    <h3 class="box-title">{{ trans('general.asset_request_status') }}</h3>
                </div>
                <div class="box-body">
                    <p>{{ trans('general.asset_request_status_description') }}</p>
                </div>
                <div class="box-body table-responsive no-padding">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Asset Name</th>
                                <th>Requested By</th>
                                <th>Asset Type</th>
                                <th>Request Type</th>
                                <th>Quantity</th>
                                <th>Submitted At</th>
                                <th>Last Updated</th>
                                <th>{{ trans('general.status') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($requests as $requestItem)
                                <tr>
                                    <td>{{ $requestItem['assetDetails']['assetName'] ?: 'Asset Request' }}</td>
                                    <td>{{ $requestItem['requester_name'] }}</td>
                                    <td>{{ $requestItem['assetDetails']['assetType'] }}</td>
                                    <td>{{ $requestItem['requestDetails']['requestType'] }}</td>
                                    <td>{{ $requestItem['assetDetails']['quantity'] }}</td>
                                    <td>{{ $requestItem['created_at'] }}</td>
                                    <td>{{ $requestItem['updated_at'] }}</td>
                                    <td>
                                        <span class="label {{ $requestItem['request_status_class'] }}">
                                            {{ $requestItem['request_status_label'] }}
                                        </span>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8">There are no asset requests right now.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@stop
