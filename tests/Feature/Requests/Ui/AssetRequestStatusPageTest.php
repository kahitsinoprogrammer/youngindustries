<?php

namespace Tests\Feature\Requests\Ui;

use App\Models\User;
use App\Services\AssetRequestMongoService;
use Tests\TestCase;

class AssetRequestStatusPageTest extends TestCase
{
    public function test_authenticated_user_can_view_asset_request_status_page()
    {
        $this->app->instance(AssetRequestMongoService::class, new class extends AssetRequestMongoService
        {
            public function all(): array
            {
                return [];
            }
        });

        $this->actingAs(User::factory()->create())
            ->get(route('asset-request.status'))
            ->assertOk()
            ->assertSeeText('Asset Request Status');
    }

    public function test_asset_request_status_page_shows_requested_approved_and_on_process_statuses()
    {
        $this->app->instance(AssetRequestMongoService::class, new class extends AssetRequestMongoService
        {
            public function all(): array
            {
                return [
                    [
                        'id' => '507f1f77bcf86cd799439011',
                        'employeeNumber' => 101,
                        'approver_id' => 201,
                        'approval_status' => 'pending',
                        'approver_remarks' => '',
                        'reviewed_by' => null,
                        'reviewed_at' => '',
                        'technical_support_specification' => '',
                        'technical_support_specification_updated_by' => null,
                        'technical_support_specification_updated_at' => '',
                        'technical_support_deployment_status' => '',
                        'technical_support_deployed_by' => null,
                        'technical_support_deployed_at' => '',
                        'created_at' => '2026-05-05T08:00:00+08:00',
                        'updated_at' => '2026-05-05T08:00:00+08:00',
                        'assetDetails' => [
                            'assetType' => 'Laptop',
                            'assetName' => 'Requested Laptop',
                            'description' => 'Pending request',
                            'specifications' => '',
                            'quantity' => 1,
                        ],
                        'requestDetails' => [
                            'reason' => 'For onboarding',
                            'requestType' => 'New',
                        ],
                        'timeline' => [
                            'neededBy' => '2026-05-10',
                            'priorityLevel' => 'High',
                        ],
                        'budget' => [
                            'estimatedCost' => 1200,
                            'budgetCode' => 'IT-REQ',
                        ],
                    ],
                    [
                        'id' => '507f1f77bcf86cd799439012',
                        'employeeNumber' => 102,
                        'approver_id' => 202,
                        'approval_status' => 'approved',
                        'approver_remarks' => 'Approved.',
                        'reviewed_by' => 202,
                        'reviewed_at' => '2026-05-05T09:00:00+08:00',
                        'technical_support_specification' => '',
                        'technical_support_specification_updated_by' => null,
                        'technical_support_specification_updated_at' => '',
                        'technical_support_deployment_status' => '',
                        'technical_support_deployed_by' => null,
                        'technical_support_deployed_at' => '',
                        'created_at' => '2026-05-05T08:30:00+08:00',
                        'updated_at' => '2026-05-05T09:00:00+08:00',
                        'assetDetails' => [
                            'assetType' => 'Monitor',
                            'assetName' => 'Approved Monitor',
                            'description' => 'Approved request',
                            'specifications' => '',
                            'quantity' => 2,
                        ],
                        'requestDetails' => [
                            'reason' => 'Replacement',
                            'requestType' => 'Replacement',
                        ],
                        'timeline' => [
                            'neededBy' => '2026-05-12',
                            'priorityLevel' => 'Medium',
                        ],
                        'budget' => [
                            'estimatedCost' => 600,
                            'budgetCode' => 'IT-APR',
                        ],
                    ],
                    [
                        'id' => '507f1f77bcf86cd799439013',
                        'employeeNumber' => 103,
                        'approver_id' => 203,
                        'approval_status' => 'approved',
                        'approver_remarks' => 'Approved.',
                        'reviewed_by' => 203,
                        'reviewed_at' => '2026-05-05T10:00:00+08:00',
                        'technical_support_specification' => '32GB RAM, 1TB SSD',
                        'technical_support_specification_updated_by' => 301,
                        'technical_support_specification_updated_at' => '2026-05-05T11:00:00+08:00',
                        'technical_support_deployment_status' => '',
                        'technical_support_deployed_by' => null,
                        'technical_support_deployed_at' => '',
                        'created_at' => '2026-05-05T09:30:00+08:00',
                        'updated_at' => '2026-05-05T11:00:00+08:00',
                        'assetDetails' => [
                            'assetType' => 'Desktop',
                            'assetName' => 'Processing Desktop',
                            'description' => 'On process request',
                            'specifications' => '',
                            'quantity' => 1,
                        ],
                        'requestDetails' => [
                            'reason' => 'Upgrade',
                            'requestType' => 'Upgrade',
                        ],
                        'timeline' => [
                            'neededBy' => '2026-05-15',
                            'priorityLevel' => 'High',
                        ],
                        'budget' => [
                            'estimatedCost' => 2000,
                            'budgetCode' => 'IT-OPS',
                        ],
                    ],
                    [
                        'id' => '507f1f77bcf86cd799439014',
                        'employeeNumber' => 104,
                        'approver_id' => 204,
                        'approval_status' => 'approved',
                        'approver_remarks' => 'Approved.',
                        'reviewed_by' => 204,
                        'reviewed_at' => '2026-05-05T10:30:00+08:00',
                        'technical_support_specification' => 'Docking station, image loaded',
                        'technical_support_specification_updated_by' => 302,
                        'technical_support_specification_updated_at' => '2026-05-05T11:30:00+08:00',
                        'technical_support_deployment_status' => 'deployed',
                        'technical_support_deployed_by' => 302,
                        'technical_support_deployed_at' => '2026-05-05T12:00:00+08:00',
                        'created_at' => '2026-05-05T10:00:00+08:00',
                        'updated_at' => '2026-05-05T12:00:00+08:00',
                        'assetDetails' => [
                            'assetType' => 'Laptop',
                            'assetName' => 'Deployed Laptop',
                            'description' => 'Deployed request',
                            'specifications' => '',
                            'quantity' => 1,
                        ],
                        'requestDetails' => [
                            'reason' => 'New hire deployment',
                            'requestType' => 'New',
                        ],
                        'timeline' => [
                            'neededBy' => '2026-05-16',
                            'priorityLevel' => 'High',
                        ],
                        'budget' => [
                            'estimatedCost' => 1800,
                            'budgetCode' => 'IT-DEP',
                        ],
                    ],
                ];
            }
        });

        User::factory()->create(['id' => 101, 'first_name' => 'Jane', 'last_name' => 'Requester']);
        User::factory()->create(['id' => 102, 'first_name' => 'John', 'last_name' => 'Approved']);
        User::factory()->create(['id' => 103, 'first_name' => 'Mia', 'last_name' => 'Process']);
        User::factory()->create(['id' => 104, 'first_name' => 'Dan', 'last_name' => 'Deploy']);
        User::factory()->create(['id' => 201, 'first_name' => 'Amy', 'last_name' => 'Approver']);
        User::factory()->create(['id' => 202, 'first_name' => 'Ben', 'last_name' => 'Approver']);
        User::factory()->create(['id' => 203, 'first_name' => 'Carl', 'last_name' => 'Approver']);
        User::factory()->create(['id' => 204, 'first_name' => 'Drew', 'last_name' => 'Approver']);
        User::factory()->create(['id' => 301, 'first_name' => 'Terry', 'last_name' => 'Support']);
        User::factory()->create(['id' => 302, 'first_name' => 'Pat', 'last_name' => 'Support']);

        $this->actingAs(User::factory()->create())
            ->get(route('asset-request.status'))
            ->assertOk()
            ->assertSeeText('Requested Laptop')
            ->assertSeeText('Approved Monitor')
            ->assertSeeText('Processing Desktop')
            ->assertSeeText('Deployed Laptop')
            ->assertSeeText('Requested')
            ->assertSeeText('Approved')
            ->assertSeeText('On Process')
            ->assertSeeText('Deployed');
    }
}
