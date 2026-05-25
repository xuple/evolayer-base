<?php

namespace Xuple\EvoLayer\Base\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class AiCapability extends Model
{
    use HasUlids;

    protected $table = 'evolayer_base_ai_capabilities';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'probe_passed' => 'boolean',
            'probed_at' => 'immutable_datetime',
            'superseded_at' => 'immutable_datetime',
        ];
    }
}
