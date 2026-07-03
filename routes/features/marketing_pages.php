<?php

use Illuminate\Support\Facades\Route;
/*
| Loaded only when EVOLAYER_BASE_EXAMPLE_MARKETING_PAGES=true.
| Publishes the public showcase /about page, mapping onto the package's
| published explainer page (evolayer/base.tsx).
|
| The authenticated launcher (/home) is host-owned by the starter, not the
| package: the framework does not assume control over host auth routing.
*/

Route::inertia('/about', 'evolayer/base')->name('evolayer.base.about');
