<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Document Chunking
    |--------------------------------------------------------------------------
    |
    | These values control how extracted document text is split before
    | embeddings are generated.
    |
    */

    'chunk_size' => env(
        'RAG_CHUNK_SIZE',
        300
    ),

    'chunk_overlap' => env(
        'RAG_CHUNK_OVERLAP',
        50
    ),
];