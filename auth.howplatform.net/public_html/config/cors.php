<?php

return [
  'supports_credentials' => true,
  'allowed_origins' => [
    'https://www.howplatform.net',
    'https://library.howplatform.net',
    'https://tools.howplatform.net',
    'https://diploma.howplatform.net',
  ],
  'paths' => ['logout','csrf-token','api/*'],
  'allowed_methods' => ['GET','POST','OPTIONS'],
  'allowed_headers' => [
    'Content-Type','X-CSRF-TOKEN','X-Requested-With','Accept','Origin',
    'X-Api-Key','Authorization',
  ],
];
