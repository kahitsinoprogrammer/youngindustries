@extends('layouts/default')

@section('title')
    {{ trans('general.assign_technical_support') }}
    @parent
@stop

@section('content')
    <form action="{{ route('asset-request.assign-technical-support.store') }}" class="form-horizontal" method="POST">
        @csrf

        <div class="row">
            <div class="col-md-12">
                <div class="box box-default">
                    <div class="box-header with-border">
                        <h3 class="box-title">{{ trans('general.assign_technical_support') }}</h3>
                    </div>
                    <div class="box-body">
                        @if (!empty($mongoError))
                            <div class="alert alert-danger">
                                {{ $mongoError }}
                            </div>
                        @endif

                        <div class="alert alert-info">
                            Select the user who should be tagged as technical support. Saving this form will create or refresh that user's technical support assignment in MongoDB.
                        </div>

                        <div class="form-group {{ $errors->has('user_id') ? 'has-error' : '' }}">
                            <label class="col-md-3 control-label" for="technical_support_user_id">{{ trans('general.user') }}</label>
                            <div class="col-md-7">
                                <select class="js-data-ajax" data-endpoint="users" data-placeholder="{{ trans('general.select_user') }}" id="technical_support_user_id" name="user_id" style="width: 100%">
                                    @if ($selectedUserId = old('user_id'))
                                        @php($selectedUser = \App\Models\User::find($selectedUserId))
                                        <option value="{{ $selectedUserId }}" selected="selected">
                                            {{ $selectedUser?->display_name ?: $selectedUser?->username }}
                                        </option>
                                    @else
                                        <option value="">{{ trans('general.select_user') }}</option>
                                    @endif
                                </select>
                                {!! $errors->first('user_id', '<span class="alert-msg" aria-hidden="true">:message</span>') !!}
                            </div>
                        </div>
                    </div>

                    <div class="box-footer">
                        <button class="btn btn-primary pull-right" type="submit">Save Technical Support Assignment</button>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-12">
                <div class="box box-default">
                    <div class="box-header with-border">
                        <h3 class="box-title">Current Technical Support Assignments</h3>
                    </div>
                    <div class="box-body table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>{{ trans('general.user') }}</th>
                                    <th>Assigned By</th>
                                    <th>Last Updated</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($assignments as $assignment)
                                    <tr>
                                        <td>{{ $assignment['user_name'] }}</td>
                                        <td>{{ $assignment['assigned_by_name'] }}</td>
                                        <td>{{ $assignment['updated_at'] }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="3">No technical support assignments have been saved yet.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </form>
@stop
