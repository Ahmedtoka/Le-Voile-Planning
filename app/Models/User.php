<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $guarded = [];
    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return ['password' => 'hashed', 'is_active' => 'boolean'];
    }

    public function roles()   { return $this->belongsToMany(Role::class); }
    /** المصنع الخارجي — الاسم employerFactory عشان ما يغطّيش على HasFactory::factory() */
    public function employerFactory() { return $this->belongsTo(Factory::class); }
    public function supplier(){ return $this->belongsTo(Supplier::class); }
    public function notifications2() { return $this->hasMany(AppNotification::class); }

    /** كل صلاحيات المستخدم من كل أدواره — مكاش بسيط للريكوست الواحد */
    public function permissionKeys(): array
    {
        static $cache = [];
        if (isset($cache[$this->id])) return $cache[$this->id];

        $keys = Permission::query()
            ->join('permission_role', 'permissions.id', '=', 'permission_role.permission_id')
            ->join('role_user', 'permission_role.role_id', '=', 'role_user.role_id')
            ->where('role_user.user_id', $this->id)
            ->pluck('permissions.key')->unique()->values()->all();

        return $cache[$this->id] = $keys;
    }

    public function hasRole(string ...$keys): bool
    {
        return $this->roles->whereIn('key', $keys)->isNotEmpty();
    }

    public function isAdmin(): bool
    {
        return $this->hasRole('admin');
    }

    /** الأدمن بيعدّي على كل حاجة */
    public function can2(string $permission): bool
    {
        if ($this->isAdmin()) return true;
        return in_array($permission, $this->permissionKeys(), true);
    }

    public function roleNames(): string
    {
        return $this->roles->pluck('name')->implode('، ');
    }
}
