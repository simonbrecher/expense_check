<?php

declare(strict_types=1);
namespace App\Presenters;


use App\Form\BasicForm;

use App\Model\DupliciteUserException;
use Nette\Security\AuthenticationException;

use App\Model;

class UserPresenter extends BasePresenter
{
    public function __construct(private Model\UserModel $userModel)
    {}

    public function renderDefault(): void
    {
        if ($this->user->isLoggedIn()) {
            $this->template->userValues = $this->userModel->getUserValues();
        }
    }

    public function actionLogin(): void
    {
        if ($this->user->isLoggedIn()) {
            $this->redirect('User:');
        }
    }

    public function renderSignin(int $id=null): void
    {
        $this->template->isLoggedIn = $this->user->isLoggedIn();
    }

    public function createComponentLoginForm(): BasicForm
    {
        $form = new BasicForm();

        $form->addGroup('body');

            $form->addText('username', 'Uživatelské jméno:')->setRequired('Zadejte prosím uživatelské jméno.')
                    ->setHtmlAttribute('autofocus');

            $form->addPassword('password', 'Heslo:')->setRequired('Zadejte prosím heslo.');

        $form->addGroup('buttons');

            $form->addSubmit('submit', 'Přihlásit se');

        $form->onSuccess[] = [$this, 'loginFormSuccess'];

        return $form;
    }

    public function loginFormSuccess(BasicForm $form): void
    {
        $values = $form->values;

        try {
            $this->user->login($values->username, $values->password);

            $this->redirect('Homepage:');
        } catch (AuthenticationException) {
            $this->flashMessage('Nesprávné uživatelské jméno nebo heslo.', 'error');
        }
    }

    public function createComponentSigninForm(): BasicForm
    {
        $form = new BasicForm();

        $form->addGroup('body');

            $form->addText('name', 'Jméno:')->setRequired('Zadejte prosím své jméno.');

            $form->addText('surname', 'Příjmení:')->setRequired('Zadejte prosím své příjmení.');

            $form->addText('username', 'Uživatelské jméno:')->setRequired('Zadejte prosím uživatelské jméno.');

            $form->addEmail('email', 'Email:')->setRequired('Zadejte prosím email.');

            if (!$this->user->isLoggedIn()) {
                $form->addPassword('password', 'Heslo:')->setRequired('Zadejte prosím heslo.');
            } else {
                $values = $this->userModel->getUserEditValues();
                $form->setDefaults($values);
            }

        $form->addGroup('buttons');

            $form->addSubmit('submit', $this->user->isLoggedIn() ? 'Upravit' : 'Založit');

        $form->onSuccess[] = [$this, 'signinFormSuccess'];

        return $form;
    }

    public function signinFormSuccess(BasicForm $form): void
    {
        $values = $form->values;

        if ($this->user->isLoggedIn()) {
            try {
                $wasUpdated = $this->userModel->editUser($values);

                if ($wasUpdated) {
                    $this->flashMessage('Uživatelský účet byl úspěšně upravený.', 'success');
                } else {
                    $this->flashMessage('Nedošlo k žádné změně v nastavení uživatelského účtu.', 'info');
                }
                $this->redirect('User:');
            } catch (\PDOException|DupliciteUserException $exception) {
                $this->flashMessage($exception->getMessage(), 'error');
            }

        } else {
            try {
                $this->userModel->addUser($values);

                $this->flashMessage('Uživatelský účet a nová rodina byli správně založeni.', 'success');
                $this->user->login($values->username, $values->password);
                $this->redirect('Homepage:');
            } catch (\PDOException|DupliciteUserException $exception) {
                $this->flashMessage($exception->getMessage(), 'error');
            }
        }
    }
}