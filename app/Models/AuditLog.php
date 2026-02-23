<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'actor_id',
        'actor_email',
        'action',
        'target',
        'ip_address',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the actor (admin user).
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    /**
     * Create an audit log entry.
     */
    public static function log(string $action, ?string $target = null, ?array $metadata = null): void
    {
        $user = request()->user();

        self::create([
            'actor_id' => $user?->id,
            'actor_email' => $user?->email ?? 'system',
            'action' => $action,
            'target' => $target,
            'ip_address' => request()->ip(),
            'metadata' => $metadata,
        ]);
    }
}
