<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'role_id',
        'data_scope',
        'employee_code',
        'name',
        'email',
        'password',
        'is_active',
        'last_login_at',
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
            'is_active' => 'boolean',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function permissionOverrides(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class)
            ->withPivot('effect')
            ->withTimestamps();
    }

    public function employee(): HasOne
    {
        return $this->hasOne(Employee::class);
    }

    public function externalAccounts(): HasMany
    {
        return $this->hasMany(ExternalSystemAccount::class);
    }

    public function hasRole(string $slug): bool
    {
        return $this->role?->slug === $slug;
    }

    public function hasAnyRole(array $slugs): bool
    {
        return in_array($this->role?->slug, $slugs, true);
    }

    public function isInDepartment(string $code): bool
    {
        return $this->employee?->department?->code === $code;
    }

    public function isSuperAdmin(): bool
    {
        return $this->hasRole('super_admin');
    }

    public function canAccess(string $permissionKey): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        return $this->effectivePermissionKeys()->contains($permissionKey);
    }

    public function canAccessAny(array $permissionKeys): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        $effectivePermissions = $this->effectivePermissionKeys();

        return collect($permissionKeys)->contains(fn (string $permissionKey) => $effectivePermissions->contains($permissionKey));
    }

    public function effectivePermissionKeys(): Collection
    {
        $this->loadMissing('role.permissions', 'permissionOverrides');

        $rolePermissions = $this->role?->permissions->pluck('key') ?? collect();
        $grants = $this->permissionOverrides
            ->where('pivot.effect', 'grant')
            ->pluck('key');
        $denies = $this->permissionOverrides
            ->where('pivot.effect', 'deny')
            ->pluck('key');

        return $rolePermissions
            ->merge($grants)
            ->unique()
            ->diff($denies)
            ->values();
    }

    public function effectiveDataScope(): string
    {
        return $this->data_scope ?: ($this->role?->default_data_scope ?: 'own');
    }

    public function dataScopeLabel(): string
    {
        return Permission::DATA_SCOPE_LABELS[$this->effectiveDataScope()] ?? 'เฉพาะของตนเอง';
    }

    public function canSeeAllData(): bool
    {
        return $this->isSuperAdmin() || $this->effectiveDataScope() === 'all';
    }

    public function canSeeDepartmentData(): bool
    {
        return in_array($this->effectiveDataScope(), ['department', 'all'], true) || $this->isSuperAdmin();
    }
}
