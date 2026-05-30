@extends('layouts/default')

@section('title')
    {{ trans('general.asset_specification') }}
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
                    <h3 class="box-title">{{ trans('general.asset_specification') }}</h3>
                </div>
                <div class="box-body">
                    <p>{{ trans('general.asset_specification_description') }}</p>
                </div>
                <div class="box-body table-responsive no-padding">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Asset Name</th>
                                <th>Requested By</th>
                                <th>Asset Type</th>
                                <th>Quantity</th>
                                <th>Priority</th>
                                <th>Needed By</th>
                                <th>Approved At</th>
                                <th>{{ trans('general.action') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($requests as $requestItem)
                                <tr>
                                    <td>{{ $requestItem['assetDetails']['assetName'] ?: 'Asset Request' }}</td>
                                    <td>{{ $requestItem['requester_name'] }}</td>
                                    <td>{{ $requestItem['assetDetails']['assetType'] }}</td>
                                    <td>{{ $requestItem['assetDetails']['quantity'] }}</td>
                                    <td>{{ $requestItem['timeline']['priorityLevel'] }}</td>
                                    <td>{{ $requestItem['timeline']['neededBy'] }}</td>
                                    <td>{{ $requestItem['reviewed_at'] ?: $requestItem['updated_at'] }}</td>
                                    <td>
                                        <a class="btn btn-primary btn-sm" href="{{ route('asset-request.asset-specification.show', $requestItem['id']) }}">
                                            {{ trans('general.details') }}
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8">There are no approved asset requests right now.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@stop
