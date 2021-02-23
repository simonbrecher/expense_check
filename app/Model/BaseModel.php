<?php

declare(strict_types=1);
namespace App\Model;

use Nette;

class BaseModel
{
    public const DATE_PATTERN_FLEXIBLE = '(0?[1-9]|[12][0-9]|3[01])\. ?(0?[1-9]|1[0-2])(\. ?((20)?[0-9]{2})?)?';
    public const DATE_PATTERN_DD_MM = '(0?[1-9]|[12][0-9]|3[01])\. ?(0?[1-9]|1[0-2])\.?';
    public const DATE_PATTERN_DD_MM_YY = '(0?[1-9]|[12][0-9]|3[01])\. ?(0?[1-9]|1[0-2])\. ?[0-9]{2}';
    public const DATE_PATTERN_DD_MM_YYYY = '(0?[1-9]|[12][0-9]|3[01])\. ?(0?[1-9]|1[0-2])\. ?20[0-9]{2}';

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
}