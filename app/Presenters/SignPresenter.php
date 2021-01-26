<?php

declare(strict_types=1);

namespace App\Presenters;

use Nette;
use Nette\Application\UI\Form;

class SignPresenter extends Nette\Application\UI\Presenter
{
    public function actionOut(): void
    {
        $this->user->logout(true);
        $this->redirect("Homepage:default");
    }

    protected function createComponentSignInForm(): Form
    {
        $form = new Form;
        $form->getElementPrototype()->setAttribute('autocomplete', 'off');

        $form->addEmail('email', 'Email:')
            ->setRequired('Please fill in your Email.');

        $form->addPassword('password', 'Password:')
            ->setRequired('Please fill in your password.');

        $form->addSubmit('send', 'Login');

        $form->onSuccess[] = [$this, 'signInFormSucceeded'];
        return $form;
    }

    public function signInFormSucceeded(Form $form, \stdClass $values): void
    {
        try {
            $this->getUser()->login($values->email, $values->password);
            $this->redirect('Homepage:');

        } catch (Nette\Security\AuthenticationException $e) {
            $form->addError('Incorrect email or password.');
        }
    }
}