<?php

namespace App\Http\Controllers;

use App\Models\Property;
use App\Models\PropertyReport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PropertyReportController extends Controller
{
    /**
     * Create one report from the authenticated user for a property.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'property_id' => ['required', 'integer', 'exists:properties,id'],
            'reason' => [
                'required',
                Rule::in([
                    'fraud',
                    'inappropriate_content',
                    'misleading_information',
                    'duplicate',
                    'other',
                ]),
            ],
            'details' => ['nullable', 'string', 'max:2000'],
        ]);

        $user = $request->user();
        $property = Property::findOrFail($validated['property_id']);

        if ($property->user_id === $user->id) {
            return response()->json([
                'message' => 'You cannot report your own property.',
            ], 403);
        }

        $report = PropertyReport::firstOrCreate(
            [
                'property_id' => $property->id,
                'reporter_id' => $user->id,
            ],
            [
                'reason' => $validated['reason'],
                'details' => $validated['details'] ?? null,
            ]
        );

        if (!$report->wasRecentlyCreated) {
            return response()->json([
                'message' => 'You have already reported this property.',
                'report' => $report,
            ], 409);
        }

        return response()->json([
            'success' => true,
            'message' => 'Your report was submitted successfully.',
            'report' => $report,
        ], 201);
    }

    /**
     * List reports for the administrator, optionally filtered by status.
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['nullable', Rule::in(['pending', 'reviewed', 'resolved', 'rejected'])],
        ]);

        $reports = PropertyReport::query()
            ->with([
                'property:id,user_id,details,location,category,status',
                'property.owner:id,first_name,last_name,phone',
                'reporter:id,first_name,last_name,phone,role',
                'reviewer:id,first_name,last_name',
            ])
            ->when(
                $validated['status'] ?? null,
                fn ($query, $status) => $query->where('status', $status)
            )
            ->latest()
            ->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $reports,
        ], 200);
    }


    public function updateStatus(Request $request, PropertyReport $report): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(['reviewed', 'resolved', 'rejected'])],
            'admin_note' => ['nullable', 'string', 'max:2000'],
        ]);

        $report->update([
            'status' => $validated['status'],
            'admin_note' => $validated['admin_note'] ?? null,
            'reviewed_by' => $request->user()->id,
        ]);

        $report->load([
            'property:id,user_id,details,location,category,status',
            'reporter:id,first_name,last_name,phone,role',
            'reviewer:id,first_name,last_name',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Report status updated successfully.',
            'report' => $report,
        ], 200);
    }
}
