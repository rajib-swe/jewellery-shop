<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Backup Directory
    |--------------------------------------------------------------------------
    |
    | Where the gzipped SQL dumps are written. This stays inside
    | storage/app/private, which is outside the public web root, so a dump can
    | never be downloaded by guessing a filename.
    |
    */

    'path' => env('BACKUP_PATH', storage_path('app/private/backups')),

    /*
    |--------------------------------------------------------------------------
    | Retention
    |--------------------------------------------------------------------------
    |
    | Every backup older than this many days is deleted by the next run, and by
    | the `backup:prune` command on its own. Keep enough copies to survive a
    | month of a corrupt database being noticed.
    |
    */

    'keep_days' => (int) env('BACKUP_KEEP_DAYS', 14),

];
