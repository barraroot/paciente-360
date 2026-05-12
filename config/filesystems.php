<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default filesystem disk that should be used
    | by the framework. The "local" disk, as well as a variety of cloud
    | based disks are available to your application for file storage.
    |
    */

    'default' => env('FILESYSTEM_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Filesystem Disks
    |--------------------------------------------------------------------------
    |
    | Below you may configure as many filesystem disks as necessary, and you
    | may even configure multiple disks for the same driver. Examples for
    | most supported storage drivers are configured here for reference.
    |
    | Supported drivers: "local", "ftp", "sftp", "s3"
    |
    */

    'disks' => [

        'local' => [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            'serve' => true,
            'throw' => false,
            'report' => false,
        ],

        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => rtrim(env('APP_URL', 'http://localhost'), '/').'/storage',
            'visibility' => 'public',
            'throw' => false,
            'report' => false,
        ],

        's3' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION'),
            'bucket' => env('AWS_BUCKET'),
            'url' => env('AWS_URL'),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
            'throw' => false,
            'report' => false,
        ],

        'imports' => [
            'driver' => 'local',
            'root' => storage_path('app/imports'),
            'throw' => false,
        ],

        /*
         * Disk de mídia omnichannel (Fase 3 — Princípio I LGPD).
         *
         * S3-compatible com MinIO em dev/test e AWS S3/Cloudflare R2 em produção.
         * visibility: private — acesso exclusivo via URLs pré-assinadas (TTL 24h).
         * use_path_style_endpoint: true — obrigatório para MinIO.
         *
         * Prefixo de objetos (aplicado no domain service):
         *   tenant_{id}/conversa_{cid}/msg_{mid}/{filename}
         *
         * Credenciais separadas do disco 's3' padrão para isolar permissões
         * (bucket dedicado, sem acesso a outros buckets do mesmo account).
         */
        'media' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID_MEDIA'),
            'secret' => env('AWS_SECRET_ACCESS_KEY_MEDIA'),
            'region' => env('AWS_REGION_MEDIA', 'us-east-1'),
            'bucket' => env('AWS_BUCKET_MEDIA', 'paciente360-media'),
            'endpoint' => env('AWS_ENDPOINT_MEDIA'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', true),
            'visibility' => 'private',
            'throw' => true,
            'report' => false,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Symbolic Links
    |--------------------------------------------------------------------------
    |
    | Here you may configure the symbolic links that will be created when the
    | `storage:link` Artisan command is executed. The array keys should be
    | the locations of the links and the values should be their targets.
    |
    */

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],

];
