<?php

declare(strict_types=1);
namespace App\Model;

use Nette;

class UserModel extends BaseModel
{
    private $passwords;

    public function __construct(Nette\Database\Explorer $database, Nette\Security\User $user, Nette\Security\Passwords $passwords)
    {
        parent::__construct($database, $user);
        $this->passwords = $passwords;
    }

    public function saveUser(Nette\Utils\ArrayHash $values): void
    {
        $database = $this->database;

        $sameUsername = $database->table('user')->where('username', $values->username)->fetch();
        if ($sameUsername) {
            throw new DupliciteUserException('Uživatelské jméno už existuje.');
        }
        $sameEmail = $database->table('user')->where('email', $values->email)->fetch();
        if ($sameEmail) {
            throw new DupliciteUserException('Email je už zabraný.');
        }

        $data = array(
            'name' => $values->name,
            'surname' => $values->surname,
            'username' => $values->username,
            'email' => $values->email,
            'password_hash' => $this->passwords->hash($values->password)
        );

        try {
            $database->beginTransaction();
                $family = $database->table('family')->insert([]);
                $family->related('user')->insert($data);
            $database->commit();
        } catch (\PDOException $exception) {
            $this->database->rollBack();
            throw $exception;
        }
    }
}

class DupliciteUserException extends Nette\Neon\Exception
{

}