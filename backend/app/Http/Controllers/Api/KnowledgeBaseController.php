<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\KnowledgeBase\StoreKnowledgeBaseRequest;
use App\Http\Requests\KnowledgeBase\UpdateKnowledgeBaseRequest;
use App\Models\KnowledgeBase;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class KnowledgeBaseController extends Controller
{
    /**
     * Return all knowledge bases owned by
     * the authenticated user.
     */
    public function index(Request $request): JsonResponse
    {
        $knowledgeBases = $request
            ->user()
            ->knowledgeBases()
            ->withCount('documents')
            ->orderByDesc('updated_at')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $knowledgeBases,
        ]);
    }

    /**
     * Create a knowledge base for the
     * authenticated user.
     */
    public function store(
        StoreKnowledgeBaseRequest $request
    ): JsonResponse {
        $knowledgeBase = $request
            ->user()
            ->knowledgeBases()
            ->create(
                $request->validated()
            );

        $knowledgeBase->loadCount(
            'documents'
        );

        return response()->json([
            'success' => true,
            'message' => 'Knowledge base created successfully.',
            'data' => $knowledgeBase,
        ], 201);
    }

    /**
     * Return one knowledge base owned by
     * the authenticated user.
     */
    public function show(
        Request $request,
        $knowledgeBase
    ): JsonResponse {
        $knowledgeBase = $this
            ->findOwnedKnowledgeBase(
                $request,
                $knowledgeBase
            );

        $knowledgeBase->loadCount(
            'documents'
        );

        return response()->json([
            'success' => true,
            'data' => $knowledgeBase,
        ]);
    }

    /**
     * Update one knowledge base owned by
     * the authenticated user.
     */
    public function update(
        UpdateKnowledgeBaseRequest $request,
        $knowledgeBase
    ): JsonResponse {
        $knowledgeBase = $this
            ->findOwnedKnowledgeBase(
                $request,
                $knowledgeBase
            );

        $knowledgeBase->update(
            $request->validated()
        );

        $knowledgeBase->loadCount(
            'documents'
        );

        return response()->json([
            'success' => true,
            'message' => 'Knowledge base updated successfully.',
            'data' => $knowledgeBase->fresh(),
        ]);
    }

    /**
     * Delete one knowledge base owned by
     * the authenticated user.
     */
    public function destroy(
        Request $request,
        $knowledgeBase
    ): JsonResponse {
        $knowledgeBase = $this
            ->findOwnedKnowledgeBase(
                $request,
                $knowledgeBase
            );

        $knowledgeBase->delete();

        return response()->json([
            'success' => true,
            'message' => 'Knowledge base deleted successfully.',
        ]);
    }

    /**
     * Resolve a knowledge base only through
     * the currently authenticated user.
     *
     * If the knowledge base belongs to another
     * user, firstOrFail() returns 404.
     */
    private function findOwnedKnowledgeBase(
        Request $request,
        $knowledgeBaseId
    ): KnowledgeBase {
        return $request
            ->user()
            ->knowledgeBases()
            ->whereKey($knowledgeBaseId)
            ->firstOrFail();
    }
}