<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Message Pagination
    |--------------------------------------------------------------------------
    |
    | Number of messages returned when opening a conversation or loading
    | older history.
    |
    */

    'message_page_size' => (int) env(
        'CHAT_MESSAGE_PAGE_SIZE',
        20
    ),

    /*
    |--------------------------------------------------------------------------
    | Maximum Message Pagination Size
    |--------------------------------------------------------------------------
    |
    | Prevent clients from requesting excessively large message batches.
    |
    */

    'max_message_page_size' => 50,

];