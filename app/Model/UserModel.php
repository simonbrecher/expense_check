<?php

declare(strict_types=1);
namespace App\Model;

use Nette;

class UserModel extends BaseModel
{
    public function __construct(Nette\Database\Explorer $database, Nette\Security\User $user, Nette\Security\Passwords $passwords)
    {
        parent::__construct($database, $user);
        $this->passwords = $passwords;
    }

    public function saveUser(array $values): void
    {

    }
}

class DupliciteUserException extends Nette\Neon\Exception
{

}