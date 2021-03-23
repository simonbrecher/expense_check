<?php

declare(strict_types=1);
namespace App\Model;


use App\Presenters\AccessUserException;
use Nette;
use Nette\Database\Table\Selection;
use Nette\Utils\ArrayHash;
use Nette\Utils\DateTime;

class FamilyModel extends BaseModel
{
    private const ROLE_NAME = ['ROLE_EDITOR' => 'Editor', 'ROLE_VIEWER' => 'XXXX', 'ROLE_CONSUMER' => 'Spotřebitel'];
    private const ROLE_SELECT = ['ROLE_CONSUMER' => 'Spotřebitel', 'ROLE_EDITOR' => 'Editor'];

    public function __construct(
        private Nette\Security\Passwords $passwords,
        Nette\Database\Explorer $database,
        Nette\Security\User $user
    )
    {
        parent::__construct($database, $user);
    }

    public function addConsumer(ArrayHash $values): void
    {
        $database = $this->database;

        if ($values->role == 'ROLE_CONSUMER') {
            $data = array(
                'family_id' => $this->user->identity->family_id,
                'name' => $values->name,
                'surname' => $values->surname,
                'role' => 'ROLE_CONSUMER',
                'is_active' => $values->is_active,
                'username' => null,
                'email' => null,
                'password_hash' => null,
            );
            if (!$data['is_active']) {
                $data['dt_deactivated'] = new DateTime();
            }

            $sameName = $this->table('user')->where('name', $data['name'])->fetch();
            if ($sameName) {
                throw new DupliciteUserException('Stejné jméno v této rodině už existuje.');
            }

            try {
                $this->database->table('user')->insert($data);
            } catch (\PDOException) {
                throw new \PDOException('Nepodařilo se přidat člena rodiny.');
            }
        } elseif ($values->role == 'ROLE_EDITOR') {
            $data = array(
                'family_id' => $this->user->identity->family_id,
                'name' => $values->name,
                'surname' => $values->surname,
                'role' => 'ROLE_EDITOR',
                'is_active' => $values->is_active,
                'username' => $values->username,
                'email' => $values->email,
                'password_hash' => $this->passwords->hash($values->password),
            );
            if (!$data['is_active']) {
                $data['dt_deactivated'] = new DateTime();
            }

            $sameName = $this->table('user')->where('name', $data['name'])->fetch();
            if ($sameName) {
                throw new DupliciteUserException('Stejné jméno v této rodině už existuje.');
            }
            $sameUsername = $database->table('user')->where('username', $data['username'])->fetch();
            if ($sameUsername) {
                throw new DupliciteUserException('Stejné uživatelské jméno už existuje.');
            }
//            $sameEmail = $database->table('user')->where('email', $data['email'])->fetch();
//            if ($sameEmail) {
//                throw new DupliciteUserException('Stejný email už existuje.');
//            }

            try {
                $database->beginTransaction();
                $user = $this->database->table('user')->insert($data);
                $this->database->table('cash_account')->insert(['user_id' => $user->id]);

                $database->commit();
            } catch (\PDOException) {
                $database->rollBack();

                throw new \PDOException('Nepodařilo se přidat člena rodiny.');
            }
        }
    }

    public function editConsumer(ArrayHash $values, int $editId): bool
    {
        if (!$this->canAccessConsumer($editId)) {
            throw new AccessUserException('Nepodařilo se upravit člena rodiny.');
        }

        $row = $this->table('user')->get($editId);

        if ($row->name != $values->name) {
            $sameName = $this->table('user')->where('name', $values->name)->fetch();
            if ($sameName) {
                throw new DupliciteUserException('Stejné jméno v této rodině už existuje.');
            }
        }

        $data = array(
            'name' => $values->name,
            'surname' => $values->surname,
            'role' => $values->role,
            'is_active' => $values->is_active,
        );

        try {
            return $row->update($data);
        } catch (\PDOException) {
            throw new \PDOException('Nepodařilo se  upravit člena rodiny');
        }
    }

    public function removeConsumer(int $id): void
    {
        if (!$this->canAccessConsumer($id)) {
            throw new \PDOException('Nepodařilo se smazat člena rodiny.');
        }

        $row = $this->table('user')->get($id);
        if (!$row) {
            throw new \PDOException('Nepodařilo se smazat člena rodiny.');
        }

        try {
            $row->delete();
        } catch (\PDOException $exception) {
            throw new \PDOException('Nepodařilo se smazat člena rodiny.');
        }
    }

    public function getConsumerParameters(int $id): array
    {
        if (!$this->canAccessConsumer($id)) {
            throw new AccessUserException('User can not access this consumer.');
        }

        $row = $this->table('user')->get($id);

        $data = array(
            'name' => $row->name,
            'surname' => $row->surname,
            'role' => $row->role,
            'is_active' => $row->is_active,
        );

        return $data;
    }

    public function getConsumers(): Selection
    {
        return $this->table('user')->select('id, name, surname, username, role, is_active')->order('is_active DESC');
    }

    public function getRoleName(string $role): string
    {
        return self::ROLE_NAME[$role];
    }

    public function canAccessConsumer(int $id): bool
    {
        $row = $this->table('user')->get($id);
        if (!$row) {
            return false;
        }

        return $row->role == 'ROLE_CONSUMER';
    }

    public function getRoleSelect(): array
    {
        return self::ROLE_SELECT;
    }

    public function getConsumerItemCount(int $id): int
    {
        return $this->database->table('invoice_item')->where('consumer_id', $id)->count();
    }
}