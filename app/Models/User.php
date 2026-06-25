<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    private ?Collection $cachedEffectivePermissionKeys = null;

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

    public function directoryEntry(): HasOne
    {
        return $this->hasOne(EmployeeDirectoryEntry::class);
    }

    public function profileChangeRequests(): HasMany
    {
        return $this->hasMany(ProfileChangeRequest::class);
    }

    public function itAssets(): HasMany
    {
        return $this->hasMany(ItAsset::class, 'owner_id');
    }

    public function favoriteWorkflowTemplates(): BelongsToMany
    {
        return $this->belongsToMany(WorkflowTemplate::class, 'workflow_template_favorites')->withTimestamps();
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

    public function canAccessItAssets(): bool
    {
        if ($this->canAccessAny(['assets.view', 'assets.manage', 'assets.reports', 'assets.settings.manage', 'assets.delete'])) {
            return true;
        }

        if ($this->hasDeniedAny(['assets.view', 'assets.manage', 'assets.reports', 'assets.settings.manage', 'assets.delete'])) {
            return false;
        }

        return $this->belongsToItDepartment() && $this->canAccessAny(['it.portal.view', 'tickets.manage']);
    }

    public function canManageItAssets(): bool
    {
        if ($this->canAccess('assets.manage')) {
            return true;
        }

        if ($this->hasDeniedAny(['assets.manage', 'assets.view'])) {
            return false;
        }

        return $this->belongsToItDepartment() && $this->canAccessAny(['it.portal.view', 'tickets.manage']);
    }

    public function canManageItAssetSettings(): bool
    {
        if ($this->canAccess('assets.settings.manage')) {
            return true;
        }

        if ($this->hasDeniedAny(['assets.settings.manage', 'assets.view'])) {
            return false;
        }

        return $this->belongsToItDepartment() && $this->canAccessAny(['assets.manage', 'it.portal.view', 'tickets.manage']);
    }

    public function canDeleteItAssets(): bool
    {
        return $this->canAccess('assets.delete');
    }

    public function canExportItAssets(): bool
    {
        if ($this->canAccess('assets.reports')) {
            return true;
        }

        if ($this->hasDeniedAny(['assets.reports', 'assets.view'])) {
            return false;
        }

        return $this->belongsToItDepartment() && $this->canAccessAny(['it.portal.view', 'tickets.manage']);
    }

    public function effectivePermissionKeys(): Collection
    {
        if ($this->cachedEffectivePermissionKeys !== null) {
            return $this->cachedEffectivePermissionKeys;
        }

        $this->loadMissing('role.permissions', 'permissionOverrides');

        $rolePermissions = $this->role?->permissions->pluck('key') ?? collect();
        $grants = $this->permissionOverrides
            ->where('pivot.effect', 'grant')
            ->pluck('key');
        $denies = $this->permissionOverrides
            ->where('pivot.effect', 'deny')
            ->pluck('key');

        return $this->cachedEffectivePermissionKeys = $rolePermissions
            ->merge($grants)
            ->unique()
            ->diff($denies)
            ->values();
    }

    private function hasDeniedAny(array $permissionKeys): bool
    {
        $this->loadMissing('permissionOverrides');

        return $this->permissionOverrides
            ->whereIn('key', $permissionKeys)
            ->where('pivot.effect', 'deny')
            ->isNotEmpty();
    }

    private function belongsToItDepartment(): bool
    {
        $this->loadMissing('employee.department');

        $departmentCode = strtoupper((string) $this->employee?->department?->code);
        $departmentName = mb_strtolower((string) $this->employee?->department?->name);

        return $departmentCode === 'IT'
            || str_contains($departmentName, 'เทคโนโลยี')
            || str_contains($departmentName, 'information technology')
            || str_contains($departmentName, ' it ');
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
