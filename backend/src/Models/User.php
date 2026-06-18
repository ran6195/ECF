<?php

declare(strict_types=1);

namespace Ecf\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $email
 * @property string $password_hash
 */
class User extends Model
{
    protected $table = 'users';

    protected $fillable = [
        'email',
        'password_hash',
    ];

    // Non esporre mai l'hash nelle serializzazioni JSON.
    protected $hidden = [
        'password_hash',
    ];

    public function verifyPassword(string $plain): bool
    {
        return password_verify($plain, $this->password_hash);
    }
}
