<?php

return [
    'uri' => env('MONGODB_URI'),
    'database' => env('MONGODB_DATABASE'),
    'asset_request_collection' => env('MONGODB_ASSET_REQUEST_COLLECTION', 'asset_requests'),
    'asset_request_approver_collection' => env('MONGODB_ASSET_REQUEST_APPROVER_COLLECTION', 'asset_request_approvers'),
    'asset_request_technical_support_collection' => env('MONGODB_ASSET_REQUEST_TECHNICAL_SUPPORT_COLLECTION', 'asset_request_technical_supports'),
];
