<?php

declare(strict_types=1);
namespace App\Model;


use Nette;

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

    public function getUserValues(): Nette\Database\Table\ActiveRow
    {
        return $this->database->table('user')->select('name, surname, username, email')->get($this->user->id);
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
            throw new DupliciteUserException('Stejné uživatelské jméno už existuje.');
        }
        # UNCOMMENT TO ALLOW ONLY ONE USER WITH ONE EMAIL IN WHOLE DATABASE
//        $sameEmail = $database->table('user')->where('email', $values->email)->fetch();
//        if ($sameEmail) {
//            throw new DupliciteUserException('Stejný email už existuje.');
//        }

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
                $user = $family->related('user')->insert($data);
                $database->table('cash_account')->insert(['user_id' => $user->id]);
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
                throw new DupliciteUserException('Stejné uživatelské jméno už existuje.');
            }
        }
//        if ($row->email != $values->email) {
//            $sameEmail = $database->table('user')->where('email', $values->email)->fetch();
//            if ($sameEmail) {
//                throw new DupliciteUserException('Stejný email už existuje.');
//            }
//        }

        try {
            return $row->update($values);
        } catch (\PDOException) {
            throw new \PDOException('Nepodařilo se upravit uživatelský účet.');
        }
    }
}

class DupliciteUserException extends Nette\Neon\Exception
{

}