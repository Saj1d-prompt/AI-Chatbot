<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Document\StoreDocumentRequest;
use App\Models\KnowledgeBase;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Throwable;

class DocumentController extends Controller
{
    /**
     * Upload a document into a knowledge base
     * owned by the authenticated user.
     */
    public function store(
        StoreDocumentRequest $request,
        $knowledgeBase
    ): JsonResponse {
        /*
         * Security:
         *
         * Only look for the knowledge base among
         * knowledge bases owned by the logged-in user.
         */
        $knowledgeBase = $this
            ->findOwnedKnowledgeBase(
                $request,
                $knowledgeBase
            );

        $file = $request->file('file');

        /*
         * Laravel generates a safe random filename.
         *
         * Example:
         * jHD72hda82HD....txt
         */
        $storedName = $file->hashName();

        /*
         * Keep every user's RAG files separated.
         *
         * Example:
         *
         * rag/
         * └── users/
         *     └── 1/
         *         └── knowledge-bases/
         *             └── 5/
         *                 └── documents/
         */
        $directory =
            'rag/users/' .
            $request->user()->id .
            '/knowledge-bases/' .
            $knowledgeBase->id .
            '/documents';

        /*
         * Store on Laravel's private local disk.
         */
        $path = $file->storeAs(
            $directory,
            $storedName,
            'local'
        );

        if ($path === false) {
            return response()->json([
                'success' => false,
                'message' =>
                    'The document could not be stored.',
            ], 500);
        }

        try {
            $document = $knowledgeBase
                ->documents()
                ->create([
                    'original_name' =>
                        $file->getClientOriginalName(),

                    'stored_name' =>
                        $storedName,

                    'mime_type' =>
                        $file->getMimeType(),

                    'file_path' =>
                        $path,

                    'file_size' =>
                        $file->getSize(),

                    'status' =>
                        'uploaded',
                ]);
        } catch (Throwable $exception) {
            /*
             * If database insertion fails after
             * saving the physical file, remove it
             * so we do not leave orphan files.
             */
            Storage::disk('local')
                ->delete($path);

            throw $exception;
        }

        return response()->json([
            'success' => true,
            'message' =>
                'Document uploaded successfully.',
            'data' => $document,
        ], 201);
    }

    /**
     * Find a knowledge base only through
     * the authenticated user.
     */
    private function findOwnedKnowledgeBase(
        StoreDocumentRequest $request,
        $knowledgeBaseId
    ): KnowledgeBase {
        return $request
            ->user()
            ->knowledgeBases()
            ->whereKey($knowledgeBaseId)
            ->firstOrFail();
    }
}