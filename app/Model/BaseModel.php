<?php


namespace App\Model;

use Nette;

class BaseModel
{
    /** @var array */
    private const PAIDBY_TYPES = ['cash' => 'V hotovosti', 'card' => 'Kartou', 'bank' => 'Bankovním převodem'];

    /** @var Nette\Database\Explorer */
    protected $database;
    /** @var Nette\Security\User */
    protected $user;

    public function __construct(Nette\Database\Explorer $database)
    {
        $this->database = $database;
    }

    public function setUser(Nette\Security\User $user): void
    {
        $this->user = $user;
    }

    public function getPaidbyTypes(): array
    {
        return self::PAIDBY_TYPES;
    }
}