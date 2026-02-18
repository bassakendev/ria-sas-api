<?php

namespace App\Http\Controllers;

use App\Http\Requests\ClientRequest;
use App\Models\Client;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClientController extends Controller
{

    use AuthorizesRequests;

    /**
     * Display a listing of clients for the authenticated user.
     */
    public function index(Request $request): JsonResponse
    {
        $clients = Client::where('user_id', $request->user()->id)->get();

        return response()->json($clients);
    }

    /**
     * Store a newly created client.
     */
    public function store(ClientRequest $request): JsonResponse
    {
        $client = Client::create([
            'user_id' => $request->user()->id,
            ...$request->validated(),
        ]);

        return response()->json($client, 201);
    }

    /**
     * Display the specified client.
     */
    public function show(Request $request, Client $client): JsonResponse
    {
        $this->authorize('view', $client);

        return response()->json($client);
    }

    /**
     * Update the specified client.
     */
    public function update(ClientRequest $request, Client $client): JsonResponse
    {
        $this->authorize('update', $client);

        $client->update($request->validated());

        return response()->json($client);
    }

    /**
     * Remove the specified client.
     */
    public function destroy(Request $request, Client $client): JsonResponse
    {
        $this->authorize('delete', $client);

        $client->delete();

        return response()->json([
            'message' => 'Client deleted successfully',
        ]);
    }
}
