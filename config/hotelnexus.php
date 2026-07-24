<?php

return [
    'base_url' => env('HOTEL_NEXUS_BASE_URL', 'https://nexus.prod.zentrumhub.com/api/hotel'),
    'content_url' => env('HOTEL_NEXUS_CONTENT_URL', 'https://nexus.prod.zentrumhub.com/api/content'),
    'api_key' => env('HOTEL_NEXUS_API_KEY'),
    'account_id' => env('HOTEL_NEXUS_ACCOUNT_ID', 'it_tcnctalnlct'),
    'channel_id' => env('HOTEL_NEXUS_CHANNEL_ID', 'it-live-uk-channel'),
    'location_url' => env('HOTEL_NEXUS_LOCATION_URL', 'https://autosuggest-v2.us.prod.zentrumhub.com'),
];
