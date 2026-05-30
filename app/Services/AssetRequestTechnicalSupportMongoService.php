<?php

namespace App\Services;

use MongoDB\Driver\BulkWrite;
use MongoDB\Driver\Manager;
use MongoDB\Driver\Query;
use RuntimeException;

class AssetRequestTechnicalSupportMongoService
{
    public function isTechnicalSupport(int $userId): bool
    {
        $query = new Query(['user_id' => $userId], ['limit' => 1]);
        $cursor = $this->manager()->executeQuery($this->namespace(), $query);

        foreach ($cursor as $document) {
            return (int) ($document->user_id ?? 0) === $userId;
        }

        return false;
    }

    public function upsert(int $userId, int $assignedBy): void
    {
        $timestamp = now()->toIso8601String();
        $bulkWrite = new BulkWrite();

        $bulkWrite->update(
            ['user_id' => $userId],
            [
                '$set' => [
                    'user_id' => $userId,
                    'assigned_by' => $assignedBy,
                    'updated_at' => $timestamp,
                ],
                '$setOnInsert' => [
                    'created_at' => $timestamp,
                ],
            ],
            ['upsert' => true]
        );

        $this->manager()->executeBulkWrite($this->namespace(), $bulkWrite);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function all(): array
    {
        $query = new Query([], ['sort' => ['updated_at' => -1]]);
        $cursor = $this->manager()->executeQuery($this->namespace(), $query);

        $records = [];

        foreach ($cursor as $document) {
            $records[] = [
                'user_id' => (int) ($document->user_id ?? 0),
                'assigned_by' => (int) ($document->assigned_by ?? 0),
                'created_at' => (string) ($document->created_at ?? ''),
                'updated_at' => (string) ($document->updated_at ?? ''),
            ];
        }

        return $records;
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
        $collection = config('mongodb.asset_request_technical_support_collection');

        if (! $database || ! $collection) {
            throw new RuntimeException('MongoDB configuration is incomplete.');
        }

        return $database.'.'.$collection;
    }
}
