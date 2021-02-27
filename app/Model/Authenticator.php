<?php

declare(strict_types=1);

namespace App\Model;

use Nette;

class Authenticator implements Nette\Security\Authenticator
{
    private $database;
    private $passwords;

    public function __construct(Nette\Database\Explorer $database, Nette\Security\Passwords $passwords)
    {
        $this->database = $database;
        $this->passwords = $passwords;
    }

    public function authenticate(string $username, string $password): Nette\Security\SimpleIdentity
    {
        $row = $this->database->table('user')->where('username', $username)->fetch();

        if (!$row) {
            throw new Nette\Security\AuthenticationException('Uživatelské jméno nebylo nalezeno.', self::IDENTITY_NOT_FOUND);
        }

        $approved = (bool) $row->is_active;
        if (!$approved) {
            throw new Nette\Security\AuthenticationException('Nepovolen přístup.', self::INVALID_CREDENTIAL);
        }

        $verified = $this->passwords->verify($password, $row->password_hash);
        if (!$verified) {
            throw new Nette\Security\AuthenticationException('Neplatné heslo.', self::INVALID_CREDENTIAL);
        }

        $needsRehash = $this->passwords->needsRehash($row->password_hash);
        if ($needsRehash) {
            $row->update(['password_hash' => $this->passwords->hash($password)]);
        }

        $identity = new Nette\Security\SimpleIdentity(
            $row->id,
            $row->role,
            ['username' => $username, 'family_id' => $row->family_id]
        );

        return $identity;
    }
}