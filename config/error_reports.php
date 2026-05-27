<?php

return [
    /*
    | Shared secret. When set, incoming reports must send a matching
    | "X-Report-Token" header. Leave null to accept anything (local dev).
    */
    'token' => env('ERROR_REPORT_TOKEN'),

    /*
    | If set, each received report is also emailed to this address
    | (best-effort — a mail failure never blocks storing the report).
    */
    'notify_email' => env('ERROR_REPORT_NOTIFY_EMAIL'),
];
