<?php

namespace App\Http\Middleware;

use App\Models\KnowledgeBase;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRegularKnowledgeBase
{
    /**
     * Allow ordinary document-management operations
     * only for regular, reusable knowledge bases.
     *
     * Internal conversation attachment knowledge bases
     * must be accessed through conversation-specific APIs.
     */
    public function handle(
        Request $request,
        Closure $next
    ): Response {
        $knowledgeBaseParameter = $request->route(
            'knowledgeBase'
        );

        /*
         * Support either a numeric route parameter
         * or an already resolved Eloquent model.
         */
        $knowledgeBaseId =
            $knowledgeBaseParameter instanceof KnowledgeBase
                ? $knowledgeBaseParameter->getKey()
                : $knowledgeBaseParameter;

        if (
            !is_scalar($knowledgeBaseId) ||
            !ctype_digit((string) $knowledgeBaseId)
        ) {
            abort(404);
        }

        /*
         * Only regular knowledge bases are permitted.
         *
         * Existing DocumentController authorization
         * continues to verify user ownership.
         */
        $isRegularKnowledgeBase = KnowledgeBase::query()
            ->whereKey($knowledgeBaseId)
            ->whereNull('conversation_id')
            ->exists();

        if (!$isRegularKnowledgeBase) {
            abort(404);
        }

        return $next($request);
    }
}
