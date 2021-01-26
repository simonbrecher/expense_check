<?php

declare(strict_types=1);

namespace App\Model;

use Nette;
use Tracy\Debugger;

class Authenticator implements Nette\Security\IAuthenticator, Nette\Security\IAuthorizator
{
    private $database;

    public function __construct(Nette\Database\Context $database)
    {
        $this->database = $database;
    }

    public function authenticate(array $credentials): Nette\Security\IIdentity
    {
        [$email, $password] = $credentials;

        $row = $this->database->table('user')
            ->where('email', $email)
            ->fetch();

        if (!$row) {
            throw new Nette\Security\AuthenticationException('User not found.');
        }

        if ($password !== $row->password) {
            throw new Nette\Security\AuthenticationException('Invalid password.');
        }

        return new Nette\Security\Identity($row->id,"user", ['name' => $row->name]);
    }

    // we don't use that now
    public function isAllowed($role, $resource, $operation) : bool
    {
        return true;
    }
}