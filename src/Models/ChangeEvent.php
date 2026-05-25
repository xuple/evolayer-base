<?php

namespace Xuple\EvoLayer\Base\Models;

use Xuple\EvoLayer\Base\Database\Factories\ChangeEventFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ChangeEvent extends Model
{
    /** @use HasFactory<ChangeEventFactory> */
    use HasFactory, HasUlids;

    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'before' => 'array',
            'after' => 'array',
            'properties' => 'array',
            'metadata' => 'array',
            'occurred_at' => 'immutable_datetime',
        ];
    }

    protected static function newFactory(): ChangeEventFactory
    {
        return ChangeEventFactory::new();
    }

    /**
     * The actor that caused the change. Polymorphic — User by default; variants may
     * record Customer / Tenant / system actors. Null for unattributed events.
     */
    public function actor(): MorphTo
    {
        return $this->morphTo();
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }
}
