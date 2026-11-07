<?php

namespace App\Http\Controllers\Api\Catalog;

use App\Models\FetchJob;
use Illuminate\Http\JsonResponse;

class JobController extends CatalogController
{
    public function show(string $id): JsonResponse
    {
        $job = FetchJob::query()->findOrFail($id);

        return response()->json(array_filter([
            'id' => $job->id, 'status' => $job->status, 'type' => $job->type, 'result' => $job->result, 'error' => $job->error,
        ], fn ($v) => $v !== null));
    }
}
