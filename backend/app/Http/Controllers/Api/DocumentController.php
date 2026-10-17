<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Document\StoreDocumentRequest;
use App\Models\KnowledgeBase;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Throwable;

class DocumentController extends Controller
{
    /**
     * List documents belonging to a knowledge base
     * owned by the authenticated user.
     */
    public function index(
        Request $request,
        $knowledgeBase
    ): JsonResponse {
        $knowledgeBase = $this->findOwnedKnowledgeBase(
            $request,
            $knowledgeBase
        );

        $documents = $knowledgeBase
            ->documents()
            ->select([
                'id',
                'knowledge_base_id',
                'original_name',
                'file_size',
                'mime_type',
                'status',
                'error_message',
                'created_at',
                'updated_at',
            ])
            ->withCount('chunks')
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $documents,
        ]);
    }

    /**
     * Upload a TXT document to an owned knowledge base.
     */
    public function store(
        StoreDocumentRequest $request,
        $knowledgeBase
    ): JsonResponse {
        $knowledgeBase = $this->findOwnedKnowledgeBase(
            $request,
            $knowledgeBase
        );

        $file = $request->file('file');

        $storedName = $file->hashName();

        $directory =
            'rag/users/' .
            $request->user()->id .
            '/knowledge-bases/' .
            $knowledgeBase->id .
            '/documents';

        $path = $file->storeAs(
            $directory,
            $storedName,
            'local'
        );

        if ($path === false) {
            return response()->json([
                'success' => false,
                'message' => 'The document could not be stored.',
            ], 500);
        }

        try {
            $document = $knowledgeBase
                ->documents()
                ->create([
                    'original_name' =>
                        $file->getClientOriginalName(),

                    'stored_name' => $storedName,

                    'mime_type' => $file->getMimeType(),

                    'file_path' => $path,

                    'file_size' => $file->getSize(),

                    'status' => 'uploaded',
                ]);
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($path);

            throw $exception;
        }

        return response()->json([
            'success' => true,
            'message' => 'Document uploaded successfully.',
            'data' => $document,
        ], 201);
    }

    /**
     * Only resolve knowledge bases owned by
     * the authenticated user.
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
