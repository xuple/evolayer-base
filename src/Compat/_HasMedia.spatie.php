<?php

namespace Xuple\EvoLayer\Base\Compat;

/**
 * Compat target when spatie/laravel-medialibrary is installed.
 * Inherits Spatie's contract verbatim — models implementing our
 * Compat\HasMedia also satisfy Spatie\MediaLibrary\HasMedia.
 */
interface HasMedia extends \Spatie\MediaLibrary\HasMedia {}
