<?php

declare(strict_types=1);
namespace App\Model;

use Nette;
use Tracy\Debugger;

class UserModel extends BaseModel
{
    public function __construct(
        private Nette\Security\Passwords $passwords,
        Nette\Database\Explorer $database,
        Nette\Security\User $user
    )
    {
        parent::__construct($database, $user);
    }

    public function getUserEditValues(): array
    {
        return $this->database->table('user')->select('name, surname, username, email')->get($this->user->id)->toArray();
    }

    public function addUser(Nette\Utils\ArrayHash $values): void
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
        } catch (\PDOException) {
            $this->database->rollBack();
            throw new \PDOException('Nepodařilo se vytvořit uživatelský účet.');
        }
    }

    public function editUser(Nette\Utils\ArrayHash $values): bool
    {
        $database = $this->database;

        $row = $database->table('user')->get($this->user->id);

        if ($row->username != $values->username) {
            $sameUsername = $database->table('user')->where('username', $values->username)->fetch();
            if ($sameUsername) {
                throw new DupliciteUserException('Uživatelské jméno už existuje.');
            }
        }
        if ($row->email != $values->email) {
            $sameUsername = $database->table('email')->where('email', $values->email)->fetch();
            if ($sameUsername) {
                throw new DupliciteUserException('Email je už zabraný.');
            }
        }

        try {
            return $row->update($values);
        } catch (\PDOException) {
            throw new \PDOException('Nepodařilo se editovat uživatelský účet.');
        }
    }
}

class DupliciteUserException extends Nette\Neon\Exception
{

}