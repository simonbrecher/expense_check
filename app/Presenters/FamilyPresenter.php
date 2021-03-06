<?php

declare(strict_types=1);
namespace App\Presenters;

use App\Form\BasicForm;
use App\Model;
use Tracy\Debugger;

class FamilyPresenter extends BasePresenter
{
    public function __construct(private Model\FamilyModel $familyModel)
    {}

    public function renderViewConsumer(): void
    {
        $this->template->consumers = $this->familyModel->getConsumers();
        $this->template->familyModel = $this->familyModel;
    }

    public function actionAddConsumer(int $id=null): void
    {
        if ($id !== null) {
            if (!$this->familyModel->canAccessConsumer($id)) {
                $this->redirect(':viewConsumer');
            }
        }
    }

    public function createComponentAddCategoryForm(): BasicForm
    {
        $form = new BasicForm();

        $form->addGroup('body');

            $form->addText('name', 'Jméno:')->setRequired();

            $form->addText('surname', 'Příjmení:')->setRequired();

            $roleSelect = $this->familyModel->getRoleSelect();
            $role = $form->addSelect('role', 'Role:', $roleSelect)->setDefaultValue('ROLE_CONSUMER');
            $role->addCondition($form::EQUAL, 'ROLE_EDITOR')
                    ->toggle('role-editor');

            $isActiveSelect = $this->familyModel->getIsActiveSelect();
            $form->addSelect('is_active', 'Je aktivní:', $isActiveSelect)->setDefaultValue(1);

            $form->addText('username', 'Uživatelské jméno:')
                    ->addConditionOn($role, $form::EQUAL, 'ROLE_EDITOR')
                        ->setRequired('Vyplňte uživatelské jméno.');

            $form->addEmail('email', 'Email:')
                    ->addConditionOn($role, $form::EQUAL, 'ROLE_EDITOR')
                        ->setRequired('Vyplňte email.');

            $form->addPassword('password', 'Heslo:')
                    ->addConditionOn($role, $form::EQUAL, 'ROLE_EDITOR')
                        ->setRequired('Vyplňte heslo.');

        $form->addGroup('buttons');

            $form->addSubmit('submit', 'Uložit');

            if ($this->getParameter('id') !== null) {
                $form->addSubmit('delete', 'Smazat')->setValidationScope([])
                    ->setHtmlAttribute('class', 'delete');
            }

        $form->onSuccess[] = [$this, 'addCategoryFormSuccess'];

//        if ($this->getParameter('id') !== null) {
//            $form->setDefaults($this->settingModel->getCategoryParameters((int) $this->getParameter('id')));
//        }

        return $form;
    }

    public function addCategoryFormSuccess(BasicForm $form): void
    {
        $submittedBy = $form->isSubmitted()->name;

        if ($submittedBy == 'submit') {
            try {
                $this->familyModel->addConsumer($form->values);

                $this->flashMessage('Člen rodiny byl úspěšně přidaný.', 'success');
                $this->redirect(':viewConsumer');
            } catch (\PDOException|Model\DupliciteUserException $exception) {
                $this->flashMessage($exception->getMessage(), 'error');
            }
        }
    }

    public function renderAddConsumer(int $id=null): void
    {

    }

    public function actionRemoveConsumer(int $id): void
    {

    }

    public function handleRemoveConsumer(int $id): void
    {

    }

    public function handleNotRemoveConsumer(): void
    {

    }

    public function renderRemoveConsumer(int $id): void
    {

    }
}