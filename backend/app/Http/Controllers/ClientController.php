<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreClientRequest;
use App\Http\Requests\UpdateClientRequest;
use App\Models\Client;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClientController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Client::query();

        if ($request->filled('name')) {
            $query->where('name', 'like', '%' . $request->string('name') . '%');
        }

        if ($request->filled('document')) {
            $query->where('document', preg_replace('/\D/', '', $request->string('document')));
        }

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        $sortField = in_array($request->get('sort_by'), ['name', 'created_at'], true)
            ? $request->get('sort_by')
            : 'name';
        $sortDirection = $request->get('sort_dir') === 'desc' ? 'desc' : 'asc';

        $clients = $query->orderBy($sortField, $sortDirection)
            ->paginate($request->integer('per_page', 15));

        return response()->json($clients);
    }

    public function store(StoreClientRequest $request): JsonResponse
    {
        $client = Client::create($request->validated());

        return response()->json($client, 201);
    }

    public function show(Client $client): JsonResponse
    {
        return response()->json($client);
    }

    public function update(UpdateClientRequest $request, Client $client): JsonResponse
    {
        $client->update($request->validated());

        return response()->json($client);
    }
}