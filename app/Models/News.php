<?php

namespace App\Models;

use App\Support\HtmlSanitizer;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class News extends Model
{
    use HasFactory;

    public const TEMPLATES = ['standard', 'highlight'];

    protected $fillable = [
        'title',
        'body',
        'image_path',
        'template',
        'is_published',
        'expires_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'is_published' => 'boolean',
            'expires_at' => 'date',
            'notified_at' => 'datetime',
        ];
    }

    /**
     * The Trix editor stores rich text as HTML; sanitize on the way in so
     * only a small known-safe tag set (see HtmlSanitizer) ever reaches the
     * dashboard carousel other users' browsers render.
     */
    protected function body(): Attribute
    {
        return Attribute::make(
            set: fn (?string $value) => HtmlSanitizer::clean($value),
        );
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Published and not past its optional expiry date - what the dashboard carousel shows. */
    public function scopePublished($query)
    {
        return $query->where('is_published', true)
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhereDate('expires_at', '>=', now()->toDateString()));
    }

    /**
     * "Show Until" is a whole calendar day, not an instant - expires_at is
     * cast to a date (midnight), so comparing it with Carbon::isPast()
     * directly would call a news item expired at 12:00am on its last valid
     * day, hours before that day is actually over. This is the single
     * source of truth both scopePublished() (via the equivalent whereDate
     * comparison) and NewsController::show() must agree on.
     */
    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->lt(now()->startOfDay());
    }
}
