<?php

namespace App\Http\Controllers;

use App\Http\Requests\ServiceRequest;
use App\Models\Service;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ServiceController extends Controller
{
    use AuthorizesRequests;

    /**
     * Display a listing of services for the authenticated user.
     */
    public function index(Request $request): JsonResponse
    {
        $services = Service::where('user_id', $request->user()->id)->get();

        return response()->json($services);
    }

    /**
     * Store a newly created service.
     */
    public function store(ServiceRequest $request): JsonResponse
    {
        $service = Service::create([
            'user_id' => $request->user()->id,
            ...$request->validated(),
        ]);

        return response()->json($service, 201);
    }

    /**
     * Display the specified service.
     */
    public function show(Request $request, Service $service): JsonResponse
    {
        $this->authorize('view', $service);

        return response()->json($service);
    }

    /**
     * Update the specified service.
     */
    public function update(ServiceRequest $request, Service $service): JsonResponse
    {
        $this->authorize('update', $service);

        $service->update($request->validated());

        return response()->json($service);
    }

    /**
     * Remove the specified service.
     */
    public function destroy(Request $request, Service $service): JsonResponse
    {
        $this->authorize('delete', $service);

        $service->delete();

        return response()->json([
            'message' => 'Service deleted successfully',
        ]);
    }
}
