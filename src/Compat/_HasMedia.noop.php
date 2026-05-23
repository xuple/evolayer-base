<?php

namespace EvoDevOps\Base\Compat;

/**
 * Compat target when spatie/laravel-medialibrary is NOT installed.
 * Empty marker interface — the model still loads; call sites must gate on
 * config('evo.base.features.contact_attachments') before invoking media APIs.
 */
interface HasMedia
{
}
