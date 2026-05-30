<?php

namespace App\Services;

use MongoDB\BSON\ObjectId;
use MongoDB\Driver\BulkWrite;
use MongoDB\Driver\Manager;
use MongoDB\Driver\Query;
use RuntimeException;

class AssetRequestMongoService
{
    public function store(array $payload): void
    {
        $bulkWrite = new BulkWrite();

        $bulkWrite->insert($payload);
        $this->manager()->executeBulkWrite($this->namespace(), $bulkWrite);
    }

    /**
     * @param  array<int>  $fallbackRequesterIds
     * @return array<int, array<string, mixed>>
     */
    public function allForApprover(int $approverId, array $fallbackRequesterIds = []): array
    {
        $query = new Query(
            $this->buildApproverFilter($approverId, $fallbackRequesterIds),
            ['sort' => ['updated_at' => -1, 'created_at' => -1]]
        );

        return $this->fetchRecords($query);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function allApproved(): array
    {
        $query = new Query(
            ['approval_status' => 'approved'],
            ['sort' => ['updated_at' => -1, 'created_at' => -1]]
        );

        return $this->fetchRecords($query);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function all(): array
    {
        $query = new Query(
            [],
            ['sort' => ['updated_at' => -1, 'created_at' => -1]]
        );

        return $this->fetchRecords($query);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findApprovedById(string $requestId): ?array
    {
        try {
            $objectId = new ObjectId($requestId);
        } catch (\Throwable) {
            return null;
        }

        $query = new Query(
            [
                '_id' => $objectId,
                'approval_status' => 'approved',
            ],
            ['limit' => 1]
        );

        return $this->fetchFirstRecord($query);
    }

    public function saveTechnicalSupportSpecification(
        string $requestId,
        int $technicalSupportUserId,
        string $technicalSupportSpecification
    ): bool {
        try {
            $objectId = new ObjectId($requestId);
        } catch (\Throwable) {
            return false;
        }

        $bulkWrite = new BulkWrite();
        $timestamp = now()->toIso8601String();

        $bulkWrite->update(
            [
                '_id' => $objectId,
                'approval_status' => 'approved',
            ],
            [
                '$set' => [
                    'technical_support_specification' => $technicalSupportSpecification,
                    'technical_support_specification_updated_by' => $technicalSupportUserId,
                    'technical_support_specification_updated_at' => $timestamp,
                    'updated_at' => $timestamp,
                ],
            ]
        );

        $result = $this->manager()->executeBulkWrite($this->namespace(), $bulkWrite);

        return ($result->getMatchedCount() > 0) || ($result->getModifiedCount() > 0);
    }

    public function markTechnicalSupportDeployment(
        string $requestId,
        int $technicalSupportUserId
    ): bool {
        try {
            $objectId = new ObjectId($requestId);
        } catch (\Throwable) {
            return false;
        }

        $bulkWrite = new BulkWrite();
        $timestamp = now()->toIso8601String();

        $bulkWrite->update(
            [
                '_id' => $objectId,
                'approval_status' => 'approved',
                'technical_support_specification' => ['$exists' => true, '$ne' => ''],
                'technical_support_deployment_status' => ['$ne' => 'deployed'],
            ],
            [
                '$set' => [
                    'technical_support_deployment_status' => 'deployed',
                    'technical_support_deployed_by' => $technicalSupportUserId,
                    'technical_support_deployed_at' => $timestamp,
                    'updated_at' => $timestamp,
                ],
            ]
        );

        $result = $this->manager()->executeBulkWrite($this->namespace(), $bulkWrite);

        return ($result->getMatchedCount() > 0) || ($result->getModifiedCount() > 0);
    }

    /**
     * @param  array<int>  $fallbackRequesterIds
     */
    public function review(
        string $requestId,
        int $approverId,
        string $status,
        string $remarks,
        array $fallbackRequesterIds = []
    ): bool {
        $bulkWrite = new BulkWrite();
        $timestamp = now()->toIso8601String();

        $bulkWrite->update(
            [
                '_id' => new ObjectId($requestId),
                '$and' => [
                    $this->buildApproverFilter($approverId, $fallbackRequesterIds),
                    [
                        '$or' => [
                            ['approval_status' => 'pending'],
                            ['approval_status' => ['$exists' => false]],
                            ['approval_status' => null],
                        ],
                    ],
                ],
            ],
            [
                '$set' => [
                    'approver_id' => $approverId,
                    'approval_status' => $status,
                    'approver_remarks' => $remarks,
                    'reviewed_by' => $approverId,
                    'reviewed_at' => $timestamp,
                    'updated_at' => $timestamp,
                ],
            ]
        );

        $result = $this->manager()->executeBulkWrite($this->namespace(), $bulkWrite);

        return ($result->getMatchedCount() > 0) || ($result->getModifiedCount() > 0);
    }

    protected function manager(): Manager
    {
        if (! extension_loaded('mongodb')) {
            throw new RuntimeException('The MongoDB PHP extension is not installed.');
        }

        $uri = config('mongodb.uri');

        if (! $uri) {
            throw new RuntimeException('MongoDB configuration is incomplete.');
        }

        return new Manager($uri);
    }

    protected function namespace(): string
    {
        $database = config('mongodb.database');
        $collection = config('mongodb.asset_request_collection');

        if (! $database || ! $collection) {
            throw new RuntimeException('MongoDB configuration is incomplete.');
        }

        return $database.'.'.$collection;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function fetchRecords(Query $query): array
    {
        $cursor = $this->manager()->executeQuery($this->namespace(), $query);
        $records = [];

        foreach ($cursor as $document) {
            $records[] = $this->mapDocument($document);
        }

        return $records;
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function fetchFirstRecord(Query $query): ?array
    {
        $cursor = $this->manager()->executeQuery($this->namespace(), $query);

        foreach ($cursor as $document) {
            return $this->mapDocument($document);
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    protected function mapDocument(object $document): array
    {
        return [
            'id' => isset($document->_id) ? (string) $document->_id : '',
            'employeeNumber' => (int) ($document->employeeNumber ?? 0),
            'approver_id' => isset($document->approver_id) ? (int) $document->approver_id : null,
            'approval_status' => (string) ($document->approval_status ?? 'pending'),
            'approver_remarks' => (string) ($document->approver_remarks ?? ''),
            'reviewed_by' => isset($document->reviewed_by) ? (int) $document->reviewed_by : null,
            'reviewed_at' => (string) ($document->reviewed_at ?? ''),
            'technical_support_specification' => (string) ($document->technical_support_specification ?? ''),
            'technical_support_specification_updated_by' => isset($document->technical_support_specification_updated_by)
                ? (int) $document->technical_support_specification_updated_by
                : null,
            'technical_support_specification_updated_at' => (string) ($document->technical_support_specification_updated_at ?? ''),
            'technical_support_deployment_status' => (string) ($document->technical_support_deployment_status ?? ''),
            'technical_support_deployed_by' => isset($document->technical_support_deployed_by)
                ? (int) $document->technical_support_deployed_by
                : null,
            'technical_support_deployed_at' => (string) ($document->technical_support_deployed_at ?? ''),
            'created_at' => (string) ($document->created_at ?? ''),
            'updated_at' => (string) ($document->updated_at ?? ''),
            'assetDetails' => [
                'assetType' => (string) ($document->assetDetails->assetType ?? ''),
                'assetName' => (string) ($document->assetDetails->assetName ?? ''),
                'description' => (string) ($document->assetDetails->description ?? ''),
                'specifications' => (string) ($document->assetDetails->specifications ?? ''),
                'quantity' => (int) ($document->assetDetails->quantity ?? 0),
            ],
            'requestDetails' => [
                'reason' => (string) ($document->requestDetails->reason ?? ''),
                'requestType' => (string) ($document->requestDetails->requestType ?? ''),
            ],
            'timeline' => [
                'neededBy' => (string) ($document->timeline->neededBy ?? ''),
                'priorityLevel' => (string) ($document->timeline->priorityLevel ?? ''),
            ],
            'budget' => [
                'estimatedCost' => (float) ($document->budget->estimatedCost ?? 0),
                'budgetCode' => (string) ($document->budget->budgetCode ?? ''),
            ],
        ];
    }

    /**
     * @param  array<int>  $fallbackRequesterIds
     * @return array<string, mixed>
     */
    protected function buildApproverFilter(int $approverId, array $fallbackRequesterIds = []): array
    {
        $orFilters = [
            ['approver_id' => $approverId],
        ];

        $fallbackRequesterIds = array_values(array_unique(array_filter(array_map('intval', $fallbackRequesterIds))));

        if ($fallbackRequesterIds !== []) {
            $orFilters[] = [
                'employeeNumber' => ['$in' => $fallbackRequesterIds],
                'approver_id' => ['$exists' => false],
            ];
            $orFilters[] = [
                'employeeNumber' => ['$in' => $fallbackRequesterIds],
                'approver_id' => null,
            ];
        }

        return ['$or' => $orFilters];
    }
}
