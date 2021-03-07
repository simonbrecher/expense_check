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

    public function createComponentAddConsumerForm(): BasicForm
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

        $form->onSuccess[] = [$this, 'addConsumerFormSuccess'];

        if ($this->getParameter('id') !== null) {
            $form->setDefaults($this->familyModel->getConsumerParameters((int) $this->getParameter('id')));
        }

        return $form;
    }

    public function addConsumerFormSuccess(BasicForm $form): void
    {
        $submittedBy = $form->isSubmitted()->name;

        $editId = $this->getParameter('id');

        if ($submittedBy == 'submit') {
            if ($editId === null) {
                try {
                    $this->familyModel->addConsumer($form->values);

                    $this->flashMessage('Člen rodiny byl úspěšně přidaný.', 'success');
                    $this->redirect(':viewConsumer');
                } catch (\PDOException|Model\DupliciteUserException $exception) {
                    $this->flashMessage($exception->getMessage(), 'error');
                }

            } else {
                try {
                    $wasUpdated = $this->familyModel->editConsumer($form->values, (int) $editId);

                    if ($wasUpdated) {
                        $this->flashMessage('Člen rodiny byl úspěšně upravený.', 'success');
                    } else {
                        $this->flashMessage('Nedošlo k žádné změně člena rodiny.', 'info');
                    }
                    $this->redirect(':viewConsumer');
                } catch (\PDOException $exception) {
                    $this->flashMessage($exception->getMessage(), 'error');
                }
            }
        } else {
            $this->redirect(':removeConsumer', $editId);
        }
    }

    public function actionRemoveConsumer(int $id): void
    {
        if ($id !== null) {
            if (!$this->familyModel->canAccessConsumer($id)) {
                $this->redirect(':viewConsumer');
            }
        }
    }

    public function handleRemoveConsumer(int $id): void
    {
        try {
            $this->familyModel->removeConsumer($id);
            $this->flashMessage('Člen rodiny byl úspěšně smazaný.', 'success');
        } catch (\PDOException $exception) {
            $this->flashMessage($exception->getMessage(), 'error');
        }
        $this->redirect(':viewConsumer');
    }

    public function handleNotRemoveConsumer(): void
    {
        $this->flashMessage('Člen rodiny nebyl smazán.', 'info');
        $this->redirect(':viewConsumer');
    }

    public function renderRemoveConsumer(int $id): void
    {
        $this->template->id = $id;
        $this->template->itemCount = $this->familyModel->getConsumerItemCount($id);
    }
}