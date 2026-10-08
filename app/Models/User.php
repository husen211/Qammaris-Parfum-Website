<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public const ROLE_SUPER_ADMIN = 'super_admin';

    public const ROLE_STAFF_ORDER = 'staff_order';

    /** Pre-ORD-02 admin accounts keep today's access until a Super Admin converts them. */
    public const ROLE_LEGACY_ADMIN = 'admin';

    public const ROLE_CUSTOMER = 'customer';

    public const ADMIN_ROLES = [self::ROLE_SUPER_ADMIN, self::ROLE_STAFF_ORDER, self::ROLE_LEGACY_ADMIN];

    /** Roles a Super Admin may assign; the legacy role can be kept but never newly granted. */
    public const ASSIGNABLE_ROLES = [self::ROLE_SUPER_ADMIN, self::ROLE_STAFF_ORDER];

    public const ROLE_LABELS = [
        self::ROLE_SUPER_ADMIN => 'Super Admin',
        self::ROLE_STAFF_ORDER => 'Staff Order',
        self::ROLE_LEGACY_ADMIN => 'Admin (lama)',
        self::ROLE_CUSTOMER => 'Customer',
    ];

    /**
     * The attributes that are mass assignable. Role, status and audit columns are set explicitly.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'must_change_password' => 'boolean',
            'auth_version' => 'integer',
            'last_login_at' => 'datetime',
        ];
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === self::ROLE_SUPER_ADMIN;
    }

    public function hasAdminRole(): bool
    {
        return in_array($this->role, self::ADMIN_ROLES, true);
    }

    public function roleLabel(): string
    {
        return self::ROLE_LABELS[$this->role] ?? (string) $this->role;
    }

    public function loginIdentifier(): string
    {
        return $this->username ?? $this->email ?? '#'.$this->id;
    }
}
