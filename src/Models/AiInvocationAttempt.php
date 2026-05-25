<?php

namespace Xuple\EvoLayer\Base\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class AiInvocationAttempt extends Model
{
    use HasUlids, LogsActivity;

    protected $table = 'evolayer_base_ai_invocation_attempts';

    protected $guarded = ['id'];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('ai')
            ->logOnly(['status', 'provider', 'model', 'exception_class'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    protected function casts(): array
    {
        return [
            'response_keys' => 'array',
            'missing_fields' => 'array',
            'invalid_fields' => 'array',
            'started_at' => 'immutable_datetime',
            'finished_at' => 'immutable_datetime',
        ];
    }

    public function invocation(): BelongsTo
    {
        return $this->belongsTo(AiInvocation::class, 'ai_invocation_id');
    }
}
