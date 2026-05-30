<?php

namespace Tests\Feature\Requests\Ui;

use App\Models\User;
use App\Services\AssetRequestMongoService;
use App\Services\AssetRequestTechnicalSupportMongoService;
use Tests\TestCase;

class AssetRequestTechnicalSupportAccessTest extends TestCase
{
    public function test_superuser_can_view_asset_specification_page()
    {
        $this->app->instance(AssetRequestMongoService::class, new class extends AssetRequestMongoService
        {
            public function allApproved(): array
            {
                return [];
            }
        });

        $this->actingAs(User::factory()->superuser()->create())
            ->get(route('asset-request.asset-specification'))
            ->assertOk()
            ->assertSeeText('Asset Specification');
    }

    public function test_technical_support_user_can_view_asset_specification_page()
    {
        $this->app->instance(AssetRequestTechnicalSupportMongoService::class, new class extends AssetRequestTechnicalSupportMongoService
        {
            public function isTechnicalSupport(int $userId): bool
            {
                return true;
            }
        });

        $this->app->instance(AssetRequestMongoService::class, new class extends AssetRequestMongoService
        {
            public function allApproved(): array
            {
                return [];
            }
        });

        $this->actingAs(User::factory()->create())
            ->get(route('asset-request.asset-specification'))
            ->assertOk()
            ->assertSeeText('Asset Specification');
    }

    public function test_regular_user_cannot_view_asset_specification_page()
    {
        $this->app->instance(AssetRequestTechnicalSupportMongoService::class, new class extends AssetRequestTechnicalSupportMongoService
        {
            public function isTechnicalSupport(int $userId): bool
            {
                return false;
            }
        });

        $this->actingAs(User::factory()->create())
            ->get(route('asset-request.asset-specification'))
            ->assertForbidden();
    }

    public function test_technical_support_user_can_view_asset_specification_details()
    {
        $requestId = '507f1f77bcf86cd799439011';

        $this->app->instance(AssetRequestTechnicalSupportMongoService::class, new class extends AssetRequestTechnicalSupportMongoService
        {
            public function isTechnicalSupport(int $userId): bool
            {
                return true;
            }
        });

        $this->app->instance(AssetRequestMongoService::class, new class($requestId) extends AssetRequestMongoService
        {
            public function __construct(private readonly string $requestId)
            {
            }

            public function findApprovedById(string $requestId): ?array
            {
                if ($requestId !== $this->requestId) {
                    return null;
                }

                return [
                    'id' => $requestId,
                    'employeeNumber' => 101,
                    'approver_id' => 202,
                    'approval_status' => 'approved',
                    'approver_remarks' => 'Approved for procurement.',
                    'reviewed_by' => 202,
                    'reviewed_at' => '2026-05-05T09:00:00+08:00',
                    'technical_support_specification' => 'Standard business laptop specification',
                    'technical_support_specification_updated_by' => 303,
                    'technical_support_specification_updated_at' => '2026-05-05T10:00:00+08:00',
                    'technical_support_deployment_status' => '',
                    'technical_support_deployed_by' => null,
                    'technical_support_deployed_at' => '',
                    'created_at' => '2026-05-04T09:00:00+08:00',
                    'updated_at' => '2026-05-05T09:00:00+08:00',
                    'assetDetails' => [
                        'assetType' => 'Laptop',
                        'assetName' => 'Dell Latitude',
                        'description' => 'Laptop for new hire',
                        'specifications' => '16GB RAM, 512GB SSD',
                        'quantity' => 1,
                    ],
                    'requestDetails' => [
                        'reason' => 'Onboarding',
                        'requestType' => 'New',
                    ],
                    'timeline' => [
                        'neededBy' => '2026-05-10',
                        'priorityLevel' => 'High',
                    ],
                    'budget' => [
                        'estimatedCost' => 1500,
                        'budgetCode' => 'IT-001',
                    ],
                ];
            }
        });

        User::factory()->create(['id' => 101, 'first_name' => 'Jane', 'last_name' => 'Requester']);
        User::factory()->create(['id' => 202, 'first_name' => 'Mark', 'last_name' => 'Approver']);
        User::factory()->create(['id' => 303, 'first_name' => 'Terry', 'last_name' => 'Support']);

        $this->actingAs(User::factory()->create())
            ->get(route('asset-request.asset-specification.show', $requestId))
            ->assertOk()
            ->assertSeeText('Dell Latitude')
            ->assertSeeText('Laptop for new hire')
            ->assertSeeText('Approved for procurement.')
            ->assertSeeText('Technical Support Specification')
            ->assertSeeText('Standard business laptop specification')
            ->assertSeeText('Tag as Deployed');
    }

    public function test_regular_user_cannot_view_asset_specification_details()
    {
        $this->app->instance(AssetRequestTechnicalSupportMongoService::class, new class extends AssetRequestTechnicalSupportMongoService
        {
            public function isTechnicalSupport(int $userId): bool
            {
                return false;
            }
        });

        $this->actingAs(User::factory()->create())
            ->get(route('asset-request.asset-specification.show', '507f1f77bcf86cd799439011'))
            ->assertForbidden();
    }

    public function test_technical_support_user_can_save_asset_specification_details()
    {
        $requestId = '507f1f77bcf86cd799439011';

        $this->app->instance(AssetRequestTechnicalSupportMongoService::class, new class extends AssetRequestTechnicalSupportMongoService
        {
            public function isTechnicalSupport(int $userId): bool
            {
                return true;
            }
        });

        $this->app->instance(AssetRequestMongoService::class, new class($requestId) extends AssetRequestMongoService
        {
            public array $saved = [];

            public function __construct(private readonly string $requestId)
            {
            }

            public function saveTechnicalSupportSpecification(
                string $requestId,
                int $technicalSupportUserId,
                string $technicalSupportSpecification
            ): bool {
                $this->saved = [
                    'requestId' => $requestId,
                    'technicalSupportUserId' => $technicalSupportUserId,
                    'technicalSupportSpecification' => $technicalSupportSpecification,
                ];

                return $requestId === $this->requestId;
            }
        });

        $technicalSupportUser = User::factory()->create();

        $this->actingAs($technicalSupportUser)
            ->post(route('asset-request.asset-specification.store', $requestId), [
                'request_id' => $requestId,
                'technical_support_specification' => 'Intel i7, 32GB RAM, 1TB SSD',
            ])
            ->assertRedirect(route('asset-request.asset-specification.show', $requestId))
            ->assertSessionHas('success', 'Technical support specification saved successfully.');
    }

    public function test_technical_support_user_can_mark_asset_specification_as_deployed()
    {
        $requestId = '507f1f77bcf86cd799439011';

        $this->app->instance(AssetRequestTechnicalSupportMongoService::class, new class extends AssetRequestTechnicalSupportMongoService
        {
            public function isTechnicalSupport(int $userId): bool
            {
                return true;
            }
        });

        $this->app->instance(AssetRequestMongoService::class, new class($requestId) extends AssetRequestMongoService
        {
            public function __construct(private readonly string $requestId)
            {
            }

            public function findApprovedById(string $requestId): ?array
            {
                if ($requestId !== $this->requestId) {
                    return null;
                }

                return [
                    'id' => $requestId,
                    'employeeNumber' => 101,
                    'approver_id' => 202,
                    'approval_status' => 'approved',
                    'approver_remarks' => '',
                    'reviewed_by' => 202,
                    'reviewed_at' => '2026-05-05T09:00:00+08:00',
                    'technical_support_specification' => 'Business laptop build',
                    'technical_support_specification_updated_by' => 303,
                    'technical_support_specification_updated_at' => '2026-05-05T10:00:00+08:00',
                    'technical_support_deployment_status' => '',
                    'technical_support_deployed_by' => null,
                    'technical_support_deployed_at' => '',
                    'created_at' => '2026-05-04T09:00:00+08:00',
                    'updated_at' => '2026-05-05T10:00:00+08:00',
                    'assetDetails' => [
                        'assetType' => 'Laptop',
                        'assetName' => 'Deployable Laptop',
                        'description' => 'Laptop for deployment',
                        'specifications' => '16GB RAM',
                        'quantity' => 1,
                    ],
                    'requestDetails' => [
                        'reason' => 'Onboarding',
                        'requestType' => 'New',
                    ],
                    'timeline' => [
                        'neededBy' => '2026-05-10',
                        'priorityLevel' => 'High',
                    ],
                    'budget' => [
                        'estimatedCost' => 1500,
                        'budgetCode' => 'IT-001',
                    ],
                ];
            }

            public function markTechnicalSupportDeployment(
                string $requestId,
                int $technicalSupportUserId
            ): bool {
                return $requestId === $this->requestId && $technicalSupportUserId > 0;
            }
        });

        $technicalSupportUser = User::factory()->create();

        $this->actingAs($technicalSupportUser)
            ->post(route('asset-request.asset-specification.deploy', $requestId), [
                'request_id' => $requestId,
            ])
            ->assertRedirect(route('asset-request.asset-specification.show', $requestId))
            ->assertSessionHas('success', 'Asset request tagged as deployed successfully.');
    }

    public function test_technical_support_user_cannot_mark_asset_specification_as_deployed_without_specification()
    {
        $requestId = '507f1f77bcf86cd799439011';

        $this->app->instance(AssetRequestTechnicalSupportMongoService::class, new class extends AssetRequestTechnicalSupportMongoService
        {
            public function isTechnicalSupport(int $userId): bool
            {
                return true;
            }
        });

        $this->app->instance(AssetRequestMongoService::class, new class($requestId) extends AssetRequestMongoService
        {
            public function __construct(private readonly string $requestId)
            {
            }

            public function findApprovedById(string $requestId): ?array
            {
                if ($requestId !== $this->requestId) {
                    return null;
                }

                return [
                    'id' => $requestId,
                    'employeeNumber' => 101,
                    'approver_id' => 202,
                    'approval_status' => 'approved',
                    'approver_remarks' => '',
                    'reviewed_by' => 202,
                    'reviewed_at' => '2026-05-05T09:00:00+08:00',
                    'technical_support_specification' => '',
                    'technical_support_specification_updated_by' => null,
                    'technical_support_specification_updated_at' => '',
                    'technical_support_deployment_status' => '',
                    'technical_support_deployed_by' => null,
                    'technical_support_deployed_at' => '',
                    'created_at' => '2026-05-04T09:00:00+08:00',
                    'updated_at' => '2026-05-05T10:00:00+08:00',
                    'assetDetails' => [
                        'assetType' => 'Laptop',
                        'assetName' => 'Undeployable Laptop',
                        'description' => 'Laptop for deployment',
                        'specifications' => '',
                        'quantity' => 1,
                    ],
                    'requestDetails' => [
                        'reason' => 'Onboarding',
                        'requestType' => 'New',
                    ],
                    'timeline' => [
                        'neededBy' => '2026-05-10',
                        'priorityLevel' => 'High',
                    ],
                    'budget' => [
                        'estimatedCost' => 1500,
                        'budgetCode' => 'IT-001',
                    ],
                ];
            }
        });

        $technicalSupportUser = User::factory()->create();

        $this->actingAs($technicalSupportUser)
            ->post(route('asset-request.asset-specification.deploy', $requestId), [
                'request_id' => $requestId,
            ])
            ->assertRedirect(route('asset-request.asset-specification.show', $requestId))
            ->assertSessionHas('error', 'Technical support specification is required before tagging this request as deployed.');
    }

    public function test_regular_user_cannot_save_asset_specification_details()
    {
        $this->app->instance(AssetRequestTechnicalSupportMongoService::class, new class extends AssetRequestTechnicalSupportMongoService
        {
            public function isTechnicalSupport(int $userId): bool
            {
                return false;
            }
        });

        $this->actingAs(User::factory()->create())
            ->post(route('asset-request.asset-specification.store', '507f1f77bcf86cd799439011'), [
                'request_id' => '507f1f77bcf86cd799439011',
                'technical_support_specification' => 'Intel i7, 32GB RAM, 1TB SSD',
            ])
            ->assertForbidden();
    }

    public function test_regular_user_cannot_mark_asset_specification_as_deployed()
    {
        $this->app->instance(AssetRequestTechnicalSupportMongoService::class, new class extends AssetRequestTechnicalSupportMongoService
        {
            public function isTechnicalSupport(int $userId): bool
            {
                return false;
            }
        });

        $this->actingAs(User::factory()->create())
            ->post(route('asset-request.asset-specification.deploy', '507f1f77bcf86cd799439011'), [
                'request_id' => '507f1f77bcf86cd799439011',
            ])
            ->assertForbidden();
    }

    public function test_only_superuser_can_view_assign_technical_support_page()
    {
        $this->actingAs(User::factory()->create())
            ->get(route('asset-request.assign-technical-support'))
            ->assertForbidden();

        $this->app->instance(AssetRequestTechnicalSupportMongoService::class, new class extends AssetRequestTechnicalSupportMongoService
        {
            public function all(): array
            {
                return [];
            }
        });

        $this->actingAs(User::factory()->superuser()->create())
            ->get(route('asset-request.assign-technical-support'))
            ->assertOk()
            ->assertSeeText('Assign Technical Support');
    }
}
