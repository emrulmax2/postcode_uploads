<?php

namespace App\Http\Controllers;

use App\Models\PostcodeRecord;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class PostcodeRecordController extends Controller
{
    public function showByPostcode(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'post_code' => ['required', 'string'],
        ]);

        $rawPostcode = strtoupper(trim($validated['post_code']));
        $compactPostcode = preg_replace('/\s+/', '', $rawPostcode) ?? $rawPostcode;

        $cacheKey = 'postcode_records:'.$compactPostcode;

        $payload = Cache::remember($cacheKey, now()->addHours(6), function () use ($rawPostcode, $compactPostcode) {
            $records = PostcodeRecord::query()
                ->where('postcode', $rawPostcode)
                ->orWhere('postcode', $compactPostcode)
                ->orWhere('postcode2', $rawPostcode)
                ->orWhere('postcode2', $compactPostcode)
                ->get();

            if ($records->isEmpty()) {
                return null;
            }

            return [
                'post_code' => $rawPostcode,
                'count' => $records->count(),
                'data' => $records->toArray(),
            ];
        });

        if (is_null($payload)) {
            return response()->json([
                'message' => 'No records found for the provided post code.',
            ], 404);
        }

        return response()->json($payload);
    }
}
