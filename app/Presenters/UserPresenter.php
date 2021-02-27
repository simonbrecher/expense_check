<?php

declare(strict_types=1);
namespace App\Presenters;

use App\Form\LoginForm;
use App\Form\SigninForm;

use App\Model\DupliciteUserException;
use Nette\Security\AuthenticationException;

use App\Model;

class UserPresenter extends BasePresenter
{
    private $userModel;

    public function __construct(Model\UserModel $userModel)
    {
        $this->userModel = $userModel;
    }

    public function actionLogin(): void
    {
        if ($this->user->isLoggedIn()) {
            $this->redirect('User:');
        }
    }

    public function createComponentLoginForm(): LoginForm
    {
        $form = new LoginForm();

        $form->addGroup('body');

            $form->addText('username', 'Uživatelské jméno:')->setRequired('Zadejte prosím uživatelské jméno.');

            $form->addPassword('password', 'Heslo:')->setRequired('Zadejte prosím heslo.');

        $form->addGroup('buttons');

            $form->addSubmit('submit', 'Přihlásit se');

        $form->onSuccess[] = [$this, 'loginFormSuccess'];

        return $form;
    }

    public function loginFormSuccess(LoginForm $form): void
    {
        $values = $form->values;

        try {
            $this->user->login($values->username, $values->password);

            $this->redirect('User:');
        } catch (AuthenticationException) {
            $this->flashMessage('Nesprávné uživatelské jméno nebo heslo.', 'error');
        }
    }

    public function createComponentSigninForm(): SigninForm
    {
        $form = new SigninForm();

        $form->addGroup('body');

            $form->addText('name', 'Jméno:')->setRequired('Zadejte prosím své jméno.');

            $form->addText('surname', 'Příjmení:')->setRequired('Zadejte prosím své příjmení.');

            $form->addText('username', 'Uživatelské jméno:')->setRequired('Zadejte prosím uživatelské jméno.');

            $form->addEmail('email', 'Email:')->setRequired('Zadejte prosím email.');

            if (!$this->user->isLoggedIn()) {
                $form->addPassword('password', 'Heslo:')->setRequired('Zadejte prosím heslo.');
            }

        $form->addGroup('buttons');

            $form->addSubmit('submit', 'Registrovat se');

        $form->onSuccess[] = [$this, 'signinFormSuccess'];

        return $form;
    }

    public function signinFormSuccess(SigninForm $form): void
    {
        $values = $form->values;

        try {
            $this->userModel->saveUser($values);
            $this->flashMessage('Uživatelský účet byl úspěšně vytvořený. Můžete se přihlásit.', 'success');

            $this->redirect('User:');
        } catch (DupliciteUserException $exception) {
            $this->flashMessage($exception->getMessage(), 'error');
        } catch (\PDOException $exception) {
            $this->flashMessage('Uživatelský účet se nepodařilo vytvořit.', 'error');
        }
    }
}