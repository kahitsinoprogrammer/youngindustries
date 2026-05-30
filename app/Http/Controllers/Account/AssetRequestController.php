<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AssetRequestApproverMongoService;
use App\Services\AssetRequestMongoService;
use App\Services\AssetRequestTechnicalSupportMongoService;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Throwable;

class AssetRequestController extends Controller
{
    public function __construct(
        private readonly AssetRequestMongoService $assetRequestMongoService,
        private readonly AssetRequestApproverMongoService $assetRequestApproverMongoService,
        private readonly AssetRequestTechnicalSupportMongoService $assetRequestTechnicalSupportMongoService
    ) {
    }

    public function index(): View
    {
        return view('account.asset-request.index');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'assetDetails.assetType' => ['required', 'string', 'max:100'],
            'assetDetails.assetName' => ['required', 'string', 'max:191'],
            'assetDetails.description' => ['required', 'string', 'max:1000'],
            'assetDetails.specifications' => ['nullable', 'string', 'max:1000'],
            'assetDetails.quantity' => ['required', 'integer', 'min:1'],
            'requestDetails.reason' => ['required', 'string', 'max:2000'],
            'requestDetails.requestType' => ['required', 'string', 'max:100'],
            'timeline.neededBy' => ['required', 'date'],
            'timeline.priorityLevel' => ['required', 'string', 'max:50'],
            'budget.estimatedCost' => ['required', 'numeric', 'min:0'],
            'budget.budgetCode' => ['required', 'string', 'max:100'],
        ]);

        $requesterId = (int) $request->user()->id;
        $approverId = $this->assetRequestApproverMongoService->findApproverIdForUser($requesterId);

        if (! $approverId) {
            return redirect()
                ->route('asset-request.index')
                ->withInput()
                ->with('error', 'No approver has been assigned to your account yet. Please contact a superuser before submitting an asset request.');
        }

        $timestamp = now()->toIso8601String();

        $payload = [
            'employeeNumber' => $requesterId,
            'approver_id' => $approverId,
            'approval_status' => 'pending',
            'approver_remarks' => '',
            'reviewed_by' => null,
            'reviewed_at' => null,
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
            'assetDetails' => [
                'assetType' => $validated['assetDetails']['assetType'],
                'assetName' => $validated['assetDetails']['assetName'],
                'description' => $validated['assetDetails']['description'],
                'specifications' => $validated['assetDetails']['specifications'] ?? '',
                'quantity' => (int) $validated['assetDetails']['quantity'],
            ],
            'requestDetails' => [
                'reason' => $validated['requestDetails']['reason'],
                'requestType' => $validated['requestDetails']['requestType'],
            ],
            'timeline' => [
                'neededBy' => $validated['timeline']['neededBy'],
                'priorityLevel' => $validated['timeline']['priorityLevel'],
            ],
            'budget' => [
                'estimatedCost' => 0 + $validated['budget']['estimatedCost'],
                'budgetCode' => $validated['budget']['budgetCode'],
            ],
        ];

        try {
            $this->assetRequestMongoService->store($payload);
        } catch (Throwable $exception) {
            report($exception);

            return redirect()
                ->route('asset-request.index')
                ->withInput()
                ->with('error', 'Asset request could not be submitted to MongoDB. Please verify the MongoDB connection settings.');
        }

        return redirect()
            ->route('asset-request.index')
            ->with('success', 'Asset request submitted successfully.');
    }

    public function approve(): View|Factory
    {
        $requests = collect();
        $mongoError = null;

        try {
            $approverId = (int) auth()->id();
            $fallbackRequesterIds = $this->assetRequestApproverMongoService->userIdsForApprover($approverId);

            $requests = $this->hydrateApprovalRequests(
                $this->assetRequestMongoService->allForApprover($approverId, $fallbackRequesterIds)
            );
        } catch (Throwable $exception) {
            report($exception);
            $mongoError = 'Approval requests could not be loaded from MongoDB. Please verify the MongoDB connection settings.';
        }

        return view('account.asset-request.approve', compact('requests', 'mongoError'));
    }

    public function assignApprover(): View|Factory
    {
        $assignments = collect();
        $mongoError = null;

        try {
            $assignments = $this->hydrateApproverAssignments(
                $this->assetRequestApproverMongoService->all()
            );
        } catch (Throwable $exception) {
            report($exception);
            $mongoError = 'Approver assignments could not be loaded from MongoDB. Please verify the MongoDB connection settings.';
        }

        return view('account.asset-request.assign-approver', compact('assignments', 'mongoError'));
    }

    public function assignTechnicalSupport(): View|Factory
    {
        $assignments = collect();
        $mongoError = null;

        try {
            $assignments = $this->hydrateTechnicalSupportAssignments(
                $this->assetRequestTechnicalSupportMongoService->all()
            );
        } catch (Throwable $exception) {
            report($exception);
            $mongoError = 'Technical support assignments could not be loaded from MongoDB. Please verify the MongoDB connection settings.';
        }

        return view('account.asset-request.assign-technical-support', compact('assignments', 'mongoError'));
    }

    public function assetsApproved(): View|Factory
    {
        return $this->assetSpecification();
    }

    public function assetSpecification(): View|Factory
    {
        $requests = collect();
        $mongoError = null;

        try {
            $requests = $this->hydrateApprovalRequests(
                $this->assetRequestMongoService->allApproved()
            );
        } catch (Throwable $exception) {
            report($exception);
            $mongoError = 'Approved asset requests could not be loaded from MongoDB. Please verify the MongoDB connection settings.';
        }

        return view('account.asset-request.asset-specification', compact('requests', 'mongoError'));
    }

    public function requestStatus(): View|Factory
    {
        $requests = collect();
        $mongoError = null;

        try {
            $requests = $this->hydrateApprovalRequests(
                $this->assetRequestMongoService->all()
            );
        } catch (Throwable $exception) {
            report($exception);
            $mongoError = 'Asset request statuses could not be loaded from MongoDB. Please verify the MongoDB connection settings.';
        }

        return view('account.asset-request.status', compact('requests', 'mongoError'));
    }

    public function showAssetSpecification(string $requestId): View|Factory|RedirectResponse
    {
        try {
            $record = $this->assetRequestMongoService->findApprovedById($requestId);
        } catch (Throwable $exception) {
            report($exception);

            return redirect()
                ->route('asset-request.asset-specification')
                ->with('error', 'The asset request details could not be loaded from MongoDB. Please verify the MongoDB connection settings.');
        }

        if (! $record) {
            abort(404);
        }

        $requestItem = $this->hydrateApprovalRequests([$record])->first();

        return view('account.asset-request.asset-specification-show', compact('requestItem'));
    }

    public function storeAssetSpecification(Request $request, string $requestId): RedirectResponse
    {
        $validated = $request->validate([
            'request_id' => ['required', 'string'],
            'technical_support_specification' => ['required', 'string', 'max:3000'],
        ]);

        if ($validated['request_id'] !== $requestId) {
            return redirect()
                ->route('asset-request.asset-specification.show', $requestId)
                ->with('error', 'Asset request identifier mismatch.');
        }

        try {
            $updated = $this->assetRequestMongoService->saveTechnicalSupportSpecification(
                $requestId,
                (int) $request->user()->id,
                trim($validated['technical_support_specification'])
            );
        } catch (Throwable $exception) {
            report($exception);

            return redirect()
                ->route('asset-request.asset-specification.show', $requestId)
                ->withInput()
                ->with('error', 'The technical support specification could not be saved to MongoDB. Please verify the MongoDB connection settings.');
        }

        if (! $updated) {
            return redirect()
                ->route('asset-request.asset-specification.show', $requestId)
                ->withInput()
                ->with('error', 'This approved asset request could not be updated.');
        }

        return redirect()
            ->route('asset-request.asset-specification.show', $requestId)
            ->with('success', 'Technical support specification saved successfully.');
    }

    public function markAssetSpecificationAsDeployed(Request $request, string $requestId): RedirectResponse
    {
        $validated = $request->validate([
            'request_id' => ['required', 'string'],
        ]);

        if ($validated['request_id'] !== $requestId) {
            return redirect()
                ->route('asset-request.asset-specification.show', $requestId)
                ->with('error', 'Asset request identifier mismatch.');
        }

        try {
            $record = $this->assetRequestMongoService->findApprovedById($requestId);
        } catch (Throwable $exception) {
            report($exception);

            return redirect()
                ->route('asset-request.asset-specification.show', $requestId)
                ->with('error', 'The asset request details could not be loaded from MongoDB. Please verify the MongoDB connection settings.');
        }

        if (! $record) {
            abort(404);
        }

        if (trim((string) ($record['technical_support_specification'] ?? '')) === '') {
            return redirect()
                ->route('asset-request.asset-specification.show', $requestId)
                ->with('error', 'Technical support specification is required before tagging this request as deployed.');
        }

        if (($record['technical_support_deployment_status'] ?? '') === 'deployed') {
            return redirect()
                ->route('asset-request.asset-specification.show', $requestId)
                ->with('success', 'This asset request is already tagged as deployed.');
        }

        try {
            $updated = $this->assetRequestMongoService->markTechnicalSupportDeployment(
                $requestId,
                (int) $request->user()->id
            );
        } catch (Throwable $exception) {
            report($exception);

            return redirect()
                ->route('asset-request.asset-specification.show', $requestId)
                ->with('error', 'The deployed status could not be saved to MongoDB. Please verify the MongoDB connection settings.');
        }

        if (! $updated) {
            return redirect()
                ->route('asset-request.asset-specification.show', $requestId)
                ->with('error', 'This approved asset request could not be tagged as deployed.');
        }

        return redirect()
            ->route('asset-request.asset-specification.show', $requestId)
            ->with('success', 'Asset request tagged as deployed successfully.');
    }

    public function reviewApproval(Request $request, string $requestId): RedirectResponse
    {
        $validated = $request->validate([
            'approval_status' => ['required', 'in:approved,rejected'],
            'approver_remarks' => ['nullable', 'string', 'max:2000', 'required_if:approval_status,rejected'],
            'request_id' => ['required', 'string'],
        ]);

        if ($validated['request_id'] !== $requestId) {
            return redirect()
                ->route('asset-request.approve')
                ->with('error', 'Approval request identifier mismatch.');
        }

        try {
            $approverId = (int) $request->user()->id;
            $fallbackRequesterIds = $this->assetRequestApproverMongoService->userIdsForApprover($approverId);

            $updated = $this->assetRequestMongoService->review(
                $requestId,
                $approverId,
                $validated['approval_status'],
                trim((string) ($validated['approver_remarks'] ?? '')),
                $fallbackRequesterIds
            );
        } catch (Throwable $exception) {
            report($exception);

            return redirect()
                ->route('asset-request.approve')
                ->withInput()
                ->with('error', 'The asset request could not be updated in MongoDB. Please verify the MongoDB connection settings.');
        }

        if (! $updated) {
            return redirect()
                ->route('asset-request.approve')
                ->with('error', 'This asset request could not be updated. It may already have been reviewed or may not belong to you.');
        }

        return redirect()
            ->route('asset-request.approve')
            ->with('success', 'Asset request updated successfully.');
    }

    public function storeApproverAssignment(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id', 'different:approver_id'],
            'approver_id' => ['required', 'integer', 'exists:users,id', 'different:user_id'],
        ]);

        try {
            $this->assetRequestApproverMongoService->upsert(
                (int) $validated['user_id'],
                (int) $validated['approver_id'],
                (int) $request->user()->id
            );
        } catch (Throwable $exception) {
            report($exception);

            return redirect()
                ->route('asset-request.assign-approver')
                ->withInput()
                ->with('error', 'Approver assignment could not be saved to MongoDB. Please verify the MongoDB connection settings.');
        }

        return redirect()
            ->route('asset-request.assign-approver')
            ->with('success', 'Approver assignment saved successfully.');
    }

    public function storeTechnicalSupportAssignment(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        try {
            $this->assetRequestTechnicalSupportMongoService->upsert(
                (int) $validated['user_id'],
                (int) $request->user()->id
            );
        } catch (Throwable $exception) {
            report($exception);

            return redirect()
                ->route('asset-request.assign-technical-support')
                ->withInput()
                ->with('error', 'Technical support assignment could not be saved to MongoDB. Please verify the MongoDB connection settings.');
        }

        return redirect()
            ->route('asset-request.assign-technical-support')
            ->with('success', 'Technical support assignment saved successfully.');
    }

    /**
     * @param  array<int, array<string, mixed>>  $records
     */
    protected function hydrateApproverAssignments(array $records): Collection
    {
        $userIds = collect($records)
            ->flatMap(fn (array $record) => [
                $record['user_id'] ?? null,
                $record['approver_id'] ?? null,
                $record['assigned_by'] ?? null,
            ])
            ->filter()
            ->unique()
            ->values();

        $usersById = User::whereIn('id', $userIds)->get()->keyBy('id');

        return collect($records)->map(function (array $record) use ($usersById) {
            $updatedAt = ! empty($record['updated_at'])
                ? Carbon::parse($record['updated_at'])->format('Y-m-d H:i')
                : '';

            return [
                'user_name' => $this->resolveUserLabel($usersById->get((int) ($record['user_id'] ?? 0)), (int) ($record['user_id'] ?? 0)),
                'approver_name' => $this->resolveUserLabel($usersById->get((int) ($record['approver_id'] ?? 0)), (int) ($record['approver_id'] ?? 0)),
                'assigned_by_name' => $this->resolveUserLabel($usersById->get((int) ($record['assigned_by'] ?? 0)), (int) ($record['assigned_by'] ?? 0)),
                'updated_at' => $updatedAt,
            ];
        });
    }

    /**
     * @param  array<int, array<string, mixed>>  $records
     */
    protected function hydrateTechnicalSupportAssignments(array $records): Collection
    {
        $userIds = collect($records)
            ->flatMap(fn (array $record) => [
                $record['user_id'] ?? null,
                $record['assigned_by'] ?? null,
            ])
            ->filter()
            ->unique()
            ->values();

        $usersById = User::whereIn('id', $userIds)->get()->keyBy('id');

        return collect($records)->map(function (array $record) use ($usersById) {
            $updatedAt = ! empty($record['updated_at'])
                ? Carbon::parse($record['updated_at'])->format('Y-m-d H:i')
                : '';

            return [
                'user_name' => $this->resolveUserLabel($usersById->get((int) ($record['user_id'] ?? 0)), (int) ($record['user_id'] ?? 0)),
                'assigned_by_name' => $this->resolveUserLabel($usersById->get((int) ($record['assigned_by'] ?? 0)), (int) ($record['assigned_by'] ?? 0)),
                'updated_at' => $updatedAt,
            ];
        });
    }

    /**
     * @param  array<int, array<string, mixed>>  $records
     */
    protected function hydrateApprovalRequests(array $records): Collection
    {
        $userIds = collect($records)
            ->flatMap(fn (array $record) => [
                $record['employeeNumber'] ?? null,
                $record['approver_id'] ?? null,
                $record['reviewed_by'] ?? null,
                $record['technical_support_specification_updated_by'] ?? null,
                $record['technical_support_deployed_by'] ?? null,
            ])
            ->filter()
            ->unique()
            ->values();

        $usersById = User::whereIn('id', $userIds)->get()->keyBy('id');

        return collect($records)->map(function (array $record) use ($usersById) {
            $requesterId = (int) ($record['employeeNumber'] ?? 0);
            $approverId = (int) ($record['approver_id'] ?? 0);
            $reviewedById = (int) ($record['reviewed_by'] ?? 0);
            $technicalSupportUpdatedById = (int) ($record['technical_support_specification_updated_by'] ?? 0);
            $technicalSupportDeployedById = (int) ($record['technical_support_deployed_by'] ?? 0);
            $status = $record['approval_status'] ?: 'pending';
            $requestStatus = $this->determineRequestStatus($record);

            return [
                'id' => $record['id'],
                'requester_name' => $this->resolveUserLabel($usersById->get($requesterId), $requesterId),
                'approver_name' => $this->resolveUserLabel($usersById->get($approverId), $approverId),
                'reviewed_by_name' => $reviewedById
                    ? $this->resolveUserLabel($usersById->get($reviewedById), $reviewedById)
                    : '',
                'approval_status' => $status,
                'request_status' => $requestStatus['key'],
                'request_status_label' => $requestStatus['label'],
                'request_status_class' => $requestStatus['class'],
                'approver_remarks' => (string) ($record['approver_remarks'] ?? ''),
                'reviewed_at' => $this->formatTimestamp($record['reviewed_at'] ?? ''),
                'technical_support_specification' => (string) ($record['technical_support_specification'] ?? ''),
                'technical_support_specification_updated_by_name' => $technicalSupportUpdatedById
                    ? $this->resolveUserLabel($usersById->get($technicalSupportUpdatedById), $technicalSupportUpdatedById)
                    : '',
                'technical_support_specification_updated_at' => $this->formatTimestamp($record['technical_support_specification_updated_at'] ?? ''),
                'technical_support_deployment_status' => (string) ($record['technical_support_deployment_status'] ?? ''),
                'technical_support_deployed_by_name' => $technicalSupportDeployedById
                    ? $this->resolveUserLabel($usersById->get($technicalSupportDeployedById), $technicalSupportDeployedById)
                    : '',
                'technical_support_deployed_at' => $this->formatTimestamp($record['technical_support_deployed_at'] ?? ''),
                'created_at' => $this->formatTimestamp($record['created_at'] ?? ''),
                'updated_at' => $this->formatTimestamp($record['updated_at'] ?? ''),
                'assetDetails' => $record['assetDetails'],
                'requestDetails' => $record['requestDetails'],
                'timeline' => $record['timeline'],
                'budget' => $record['budget'],
            ];
        });
    }

    /**
     * @param  array<string, mixed>  $record
     * @return array{key: string, label: string, class: string}
     */
    protected function determineRequestStatus(array $record): array
    {
        $approvalStatus = (string) ($record['approval_status'] ?? 'pending');
        $hasTechnicalSupportSpecification = trim((string) ($record['technical_support_specification'] ?? '')) !== '';
        $deploymentStatus = (string) ($record['technical_support_deployment_status'] ?? '');

        if ($deploymentStatus === 'deployed') {
            return [
                'key' => 'deployed',
                'label' => 'Deployed',
                'class' => 'label-info',
            ];
        }

        if (($approvalStatus === 'approved') && $hasTechnicalSupportSpecification) {
            return [
                'key' => 'on_process',
                'label' => 'On Process',
                'class' => 'label-primary',
            ];
        }

        return match ($approvalStatus) {
            'approved' => [
                'key' => 'approved',
                'label' => 'Approved',
                'class' => 'label-success',
            ],
            'rejected' => [
                'key' => 'rejected',
                'label' => 'Rejected',
                'class' => 'label-danger',
            ],
            default => [
                'key' => 'requested',
                'label' => 'Requested',
                'class' => 'label-warning',
            ],
        };
    }

    protected function resolveUserLabel(?User $user, int $fallbackId): string
    {
        if ($user) {
            return $user->display_name ?: $user->username;
        }

        return 'User #'.$fallbackId;
    }

    protected function formatTimestamp(string|null $timestamp): string
    {
        if (! $timestamp) {
            return '';
        }

        return Carbon::parse($timestamp)->format('Y-m-d H:i');
    }
}
