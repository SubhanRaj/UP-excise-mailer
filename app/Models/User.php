<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;

#[Fillable(['name', 'username', 'email', 'mobile', 'password', 'role', 'designation_id', 'post', 'section_id', 'privileges'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, SoftDeletes;

    /**
     * Fine-grained permissions layered on top of the flat `role` column — a SuperAdmin already
     * has every privilege implicitly; an Admin/User can be granted specific ones here. Keep in
     * sync with resources/views/admin/users/_privilege_checkboxes.blade.php by hand.
     */
    public const PRIVILEGES = [
        'users.manage',
        'sections.manage',
        'mail-accounts.manage',
        'designations.manage',
        'templates.manage',
        'campaigns.send',
        'recipients.import',
        'activity-logs.view',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'privileges' => 'array',
        ];
    }

    public function designation(): BelongsTo
    {
        return $this->belongsTo(Designation::class)->withTrashed();
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    public function isAdmin(): bool
    {
        return $this->role === 'SuperAdmin';
    }

    public function hasPrivilege(string $privilege): bool
    {
        if ($this->isAdmin()) {
            return true;
        }

        $privileges = $this->privileges ?? [];

        return in_array('*', $privileges, true) || in_array($privilege, $privileges, true);
    }

    /**
     * A user may only send through mail accounts belonging to their own section, unless
     * SuperAdmin — sections model real Google One seats, so cross-section sending would use
     * someone else's mailbox.
     */
    public function canUseMailAccount(MailAccount $account): bool
    {
        return $this->isAdmin() || $this->section_id === $account->section_id;
    }

    /**
     * Slugifies name, appending -2/-3/etc. on collision (checked against soft-deleted rows too,
     * since a deleted user's username shouldn't be immediately reusable and confuse audit logs).
     */
    public static function uniqueUsername(string $name): string
    {
        $base = Str::slug($name, '_');
        $username = $base;
        $i = 2;

        while (static::withTrashed()->where('username', $username)->exists()) {
            $username = "{$base}_{$i}";
            $i++;
        }

        return $username;
    }
}
