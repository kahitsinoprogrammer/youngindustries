@extends('layouts/default')

@section('title')
    {{ trans('general.assign_approver') }}
    @parent
@stop

@section('content')
    <form action="{{ route('asset-request.assign-approver.store') }}" class="form-horizontal" method="POST">
        @csrf

        <div class="row">
            <div class="col-md-12">
                <div class="box box-default">
                    <div class="box-header with-border">
                        <h3 class="box-title">Assign Approver</h3>
                    </div>
                    <div class="box-body">
                        @if (!empty($mongoError))
                            <div class="alert alert-danger">
                                {{ $mongoError }}
                            </div>
                        @endif

                        <div class="alert alert-info">
                            Choose a user, then choose the approver for that user. If that user already has an approver, saving this form will replace the old assignment.
                        </div>

                        <div class="form-group {{ $errors->has('user_id') ? 'has-error' : '' }}">
                            <label class="col-md-3 control-label" for="approver_target_user_id">User</label>
                            <div class="col-md-7">
                                <select class="js-data-ajax" data-endpoint="users" data-placeholder="{{ trans('general.select_user') }}" id="approver_target_user_id" name="user_id" style="width: 100%">
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

                        <div class="form-group {{ $errors->has('approver_id') ? 'has-error' : '' }}">
                            <label class="col-md-3 control-label" for="approver_user_id">Approver</label>
                            <div class="col-md-7">
                                <select class="js-data-ajax" data-endpoint="users" data-placeholder="{{ trans('general.select_user') }}" id="approver_user_id" name="approver_id" style="width: 100%">
                                    @if ($selectedApproverId = old('approver_id'))
                                        @php($selectedApprover = \App\Models\User::find($selectedApproverId))
                                        <option value="{{ $selectedApproverId }}" selected="selected">
                                            {{ $selectedApprover?->display_name ?: $selectedApprover?->username }}
                                        </option>
                                    @else
                                        <option value="">{{ trans('general.select_user') }}</option>
                                    @endif
                                </select>
                                {!! $errors->first('approver_id', '<span class="alert-msg" aria-hidden="true">:message</span>') !!}
                            </div>
                        </div>
                    </div>

                    <div class="box-footer">
                        <button class="btn btn-primary pull-right" type="submit">Save Approver Assignment</button>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-12">
                <div class="box box-default">
                    <div class="box-header with-border">
                        <h3 class="box-title">Current Approver Assignments</h3>
                    </div>
                    <div class="box-body table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>User</th>
                                    <th>Approver</th>
                                    <th>Assigned By</th>
                                    <th>Last Updated</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($assignments as $assignment)
                                    <tr>
                                        <td>{{ $assignment['user_name'] }}</td>
                                        <td>{{ $assignment['approver_name'] }}</td>
                                        <td>{{ $assignment['assigned_by_name'] }}</td>
                                        <td>{{ $assignment['updated_at'] }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4">No approver assignments have been saved yet.</td>
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
