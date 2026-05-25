<?php

namespace Xuple\EvoLayer\Base\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;
use Xuple\EvoLayer\Base\Compat\HasMedia;
use Xuple\EvoLayer\Base\Compat\HasTags;
use Xuple\EvoLayer\Base\Compat\InteractsWithMedia;
use Xuple\EvoLayer\Base\Database\Factories\FormSubmissionFactory;

class FormSubmission extends Model implements HasMedia
{
    /** @use HasFactory<FormSubmissionFactory> */
    use HasFactory, HasTags, HasUlids, InteractsWithMedia, LogsActivity, SoftDeletes;

    protected $table = 'evolayer_base_form_submissions';

    protected $guarded = ['id'];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'new',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'embedding' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::retrieved(function (self $model): void {
            $model->enableLoggingModelsEvents = false;
        });
        static::creating(function (self $model): void {
            $model->enableLoggingModelsEvents = false;
        });
        static::updating(function (self $model): void {
            $model->enableLoggingModelsEvents = false;
        });
        static::deleting(function (self $model): void {
            $model->enableLoggingModelsEvents = false;
        });
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('submission')
            ->dontLogIfAttributesChangedOnly(['updated_at'])
            ->logOnlyDirty()
            ->logOnly([]);
    }

    protected static function newFactory(): FormSubmissionFactory
    {
        return FormSubmissionFactory::new();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(config('auth.providers.users.model'));
    }
}
