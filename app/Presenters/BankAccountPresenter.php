<?php

declare(strict_types=1);
namespace App\Presenters;


use App\Form\BasicForm;
use App\Model;

class BankAccountPresenter extends BasePresenter
{
    public function __construct(private Model\BankAccountModel $bankAccountModel)
    {}

    public function actionDefault(): void
    {
        $this->redirect(':view');
    }

    public function handleActivateBankAccount(int $id): void
    {
        try{
            $this->bankAccountModel->activateBankAccount($id);

            $this->flashMessage('Bankovní účet byl úspěšně aktivovaný.', 'success');
        } catch (AccessUserException $exception) {
            $this->flashMessage($exception->getMessage(), 'error');
        }
    }

    public function handleDeactivateBankAccount(int $id): void
    {
        try{
            $this->bankAccountModel->deactivateBankAccount($id);

            $this->flashMessage('Bankovní účet byl úspěšně deaktivovaný.', 'success');
        } catch (AccessUserException $exception) {
            $this->flashMessage($exception->getMessage(), 'error');
        }
    }

    public function handleActivateCard(int $id): void
    {
        try{
            $this->bankAccountModel->activateCard($id);

            $this->flashMessage('Platební karta byla úspěšně aktivovaná.', 'success');
        } catch (AccessUserException $exception) {
            $this->flashMessage($exception->getMessage(), 'error');
        }
    }

    public function handleDeactivateCard(int $id): void
    {
        try{
            $this->bankAccountModel->deactivateCard($id);

            $this->flashMessage('Platební karta byla úspěšně deaktivovaná.', 'success');
        } catch (AccessUserException $exception) {
            $this->flashMessage($exception->getMessage(), 'error');
        }
    }

    public function createComponentAddBankAccountForm(): BasicForm
    {
        $form = new BasicForm();

        $form->addGroup('body');

            $bankSelect = $this->bankAccountModel->getBankSelect();
            $form->addselect('bank_id', 'Banka:', $bankSelect)->setRequired('Vyberte banku.')->setPrompt('');

            $form->addText('number', 'Číslo:')->setRequired('Doplňte číslo bankovního účtu.');

            $isActiveSelect = $this->bankAccountModel->getIsActiveSelect();
            $form->addSelect('is_active', 'Je aktivní:', $isActiveSelect)->setDefaultValue(1);

        $form->addGroup('buttons');

            $form->addSubmit('submit', 'Uložit');

        $form->onSuccess[] = [$this, 'addBankAccountFormSuccess'];

        return $form;
    }

    public function addBankAccountFormSuccess(BasicForm $form): void
    {
        $values = $form->values;

        try {
            $this->bankAccountModel->addBankAccount($values);

            $this->flashMessage('Bankovní účet byl úspěšně přidaný.', 'success');
            $this->redirect(':view');
        } catch (\PDOException|Model\DupliciteException $exception) {
            $this->flashMessage($exception->getMessage(), 'error');
        }
    }

    public function createComponentAddCardForm(): BasicForm
    {
        $form = new BasicForm();

        $form->addGroup('body');

            $bankAccountSelect = $this->bankAccountModel->getBankAccountSelect();
            $form->addselect('bank_account_id', 'Bankovní účet:', $bankAccountSelect)->setRequired('Vyberte banku.')->setPrompt('');

            $form->addText('number', '4 poslední číslice:')->addRule($form::LENGTH, 'Zadejte 4 poslední číslice.', 4)->addRule($form::NUMERIC, 'Zadejte 4 poslední číslice.')->setRequired();

            $form->addText('name', 'Název')->setRequired('Vyplňte název');

            $isActiveSelect = $this->bankAccountModel->getIsActiveSelect();
            $form->addSelect('is_active', 'Je aktivní:', $isActiveSelect)->setDefaultValue(1);

        $form->addGroup('buttons');

            $form->addSubmit('submit', 'Uložit');

        $form->onSuccess[] = [$this, 'addCardFormSuccess'];

        return $form;
    }

    public function addCardFormSuccess(BasicForm $form): void
    {
        $values = $form->values;

        try {
            $this->bankAccountModel->addCard($values);

            $this->flashMessage('Platební karta byla úspěšně přidaná.', 'success');
            $this->redirect(':view');
        } catch (\PDOException|Model\DupliciteException $exception) {
            $this->flashMessage($exception->getMessage(), 'error');
        }
    }

    public function renderView(): void
    {
        $this->template->bankAccounts = $this->bankAccountModel->getBankAccounts();
        $this->template->cards = $this->bankAccountModel->getCards();
        $this->template->bankAccountModel = $this->bankAccountModel;
    }
}