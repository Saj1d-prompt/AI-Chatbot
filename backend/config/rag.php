
<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Document Chunking
    |--------------------------------------------------------------------------
    |
    | These settings control how extracted document text is divided
    | into smaller chunks before generating embeddings.
    |
    */

    'chunk_size' => (int) env('RAG_CHUNK_SIZE', 300),

    'chunk_overlap' => (int) env('RAG_CHUNK_OVERLAP', 50),

    /*
    |--------------------------------------------------------------------------
    | RAG Retrieval
    |--------------------------------------------------------------------------
    |
    | retrieval_limit:
    | Maximum number of relevant document chunks included in
    | the retrieved results.
    |
    | minimum_score:
    | Minimum cosine similarity score required for a chunk
    | to be considered relevant.
    |
    */

    'retrieval_limit' => (int) env('RAG_RETRIEVAL_LIMIT', 5),

    'minimum_score' => (float) env('RAG_MINIMUM_SCORE', 0.0),

];
