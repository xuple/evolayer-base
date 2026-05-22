<?php

namespace EvoDevOps\Base\Models;

use EvoDevOps\Base\Database\Factories\FormSubmissionFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\Tags\HasTags;

class FormSubmission extends Model implements HasMedia
{
    /** @use HasFactory<FormSubmissionFactory> */
    use HasFactory, HasTags, HasUlids, InteractsWithMedia, LogsActivity, SoftDeletes;

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

    public function user(): BelongsTo
    {
        return $this->belongsTo(config('auth.providers.users.model'));
    }
}
