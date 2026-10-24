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
     * List only reusable knowledge bases.
     *
     * Internal conversation attachment knowledge bases
     * must not appear in the normal management interface.
     */
    public function index(Request $request): JsonResponse
    {
        $knowledgeBases = $request
            ->user()
            ->knowledgeBases()
            ->whereNull('conversation_id')
            ->withCount('documents')
            ->orderByDesc('updated_at')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $knowledgeBases,
        ]);
    }

    /**
     * Create a regular, reusable knowledge base.
     */
    public function store(
        StoreKnowledgeBaseRequest $request
    ): JsonResponse {
        $knowledgeBase = $request
            ->user()
            ->knowledgeBases()
            ->create([
                'name' => $request->validated('name'),
                'description' =>
                    $request->validated('description'),
                'conversation_id' => null,
            ]);

        $knowledgeBase->loadCount('documents');

        return response()->json([
            'success' => true,
            'message' =>
                'Knowledge base created successfully.',
            'data' => $knowledgeBase,
        ], 201);
    }

    /**
     * Show only a regular knowledge base
     * belonging to the authenticated user.
     */
    public function show(
        Request $request,
        $knowledgeBase
    ): JsonResponse {
        $knowledgeBase = $this->findOwnedRegularKnowledgeBase(
            $request,
            $knowledgeBase
        );

        $knowledgeBase->loadCount('documents');

        return response()->json([
            'success' => true,
            'data' => $knowledgeBase,
        ]);
    }

    /**
     * Update only a regular knowledge base.
     */
    public function update(
        UpdateKnowledgeBaseRequest $request,
        $knowledgeBase
    ): JsonResponse {
        $knowledgeBase = $this->findOwnedRegularKnowledgeBase(
            $request,
            $knowledgeBase
        );

        $knowledgeBase->update(
            $request->validated()
        );

        $knowledgeBase->refresh();
        $knowledgeBase->loadCount('documents');

        return response()->json([
            'success' => true,
            'message' =>
                'Knowledge base updated successfully.',
            'data' => $knowledgeBase,
        ]);
    }

    /**
     * Delete only a regular knowledge base.
     *
     * Internal chat storage is managed through
     * its owning conversation instead.
     */
    public function destroy(
        Request $request,
        $knowledgeBase
    ): JsonResponse {
        $knowledgeBase = $this->findOwnedRegularKnowledgeBase(
            $request,
            $knowledgeBase
        );

        $knowledgeBase->delete();

        return response()->json([
            'success' => true,
            'message' =>
                'Knowledge base deleted successfully.',
        ]);
    }

    /**
     * Resolve an owned, reusable knowledge base.
     *
     * Even the owner cannot access internal chat
     * storage through ordinary management routes.
     */
    private function findOwnedRegularKnowledgeBase(
        Request $request,
        $knowledgeBaseId
    ): KnowledgeBase {
        return $request
            ->user()
            ->knowledgeBases()
            ->whereNull('conversation_id')
            ->whereKey($knowledgeBaseId)
            ->firstOrFail();
    }
}
