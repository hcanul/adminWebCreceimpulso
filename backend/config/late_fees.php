<?php

return [
    // Applies only when a new authorization is issued; existing snapshots are never modified.
    'new_authorizations' => env('LATE_FEES_NEW_AUTHORIZATIONS', true),
];
