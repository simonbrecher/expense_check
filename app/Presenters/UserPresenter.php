<?php

declare(strict_types=1);
namespace App\Presenters;

use App\Form\LoginForm;
use App\Form\SigninForm;
use Tracy\Debugger;

class UserPresenter extends BasePresenter
{
    public function renderDefault(): void
    {

    }

    public function actionLogin(): void
    {
        if ($this->user->isLoggedIn()) {
            $this->redirect('User:');
        }
    }

    public function createComponentSigninForm(): SigninForm
    {
        $form = new SigninForm();

        $form->addText('username', 'Uživatelské jméno:')->setRequired('Zadejte prosím uživatelské jméno.');

        $form->addPassword('password', 'Heslo:')->setRequired('Zadejte prosím heslo.');

        $form->addSubmit('submit', 'Přihlásit se');

        $form->onSuccess[] = [$this, 'signinFormSuccess'];

        return $form;
    }

    public function signinFormSuccess(SigninForm $form): void
    {
        $values = $form->values;
        $this->user->login($values->username, $values->password);

        $this->redirect('User:');
    }

    public function createComponentLoginForm(): LoginForm
    {
        $form = new LoginForm();

        return $form;
    }
}