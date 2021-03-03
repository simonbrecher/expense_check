<?php

declare(strict_types=1);
namespace App\Presenters;

use App\Form\AddCategoryForm;
use App\Model;
use App\Model\DupliciteCategoryException;
use App\Model\DupliciteUserException;
use Nette\Neon\Exception;

class SettingPresenter extends BasePresenter
{
    public function __construct(private Model\SettingModel $settingModel)
    {}

    public function renderviewCategory(): void
    {
        $categories = $this->settingModel->getCategories();
        $this->template->categories = $categories;
    }

    public function actionAddCategory(int|null $id=null): void
    {
        if ($id !== null) {
            if (!$this->settingModel->canAccessCategory($id)) {
                $this->redirect(':default');
            }
        }
    }

    public function createComponentAddCategoryForm(): AddCategoryForm
    {
        $form = new AddCategoryForm();

        $form->addGroup('body');

        $form->addText('name', 'Název:');

        $form->addTextArea('description', 'Popis:');

        $form->addGroup('buttons');

        $form->addSubmit('submit', 'Uložit');

        $form->onSuccess[] = [$this, 'addCategoryFormSuccess'];

        if ($this->getParameter('id') !== null) {
            $form->setDefaults($this->settingModel->getCategoryParameters((int) $this->getParameter('id')));
        }

        return $form;
    }

    public function addCategoryFormSuccess(AddCategoryForm $form): void
    {
        if ($this->getParameter('id') === null) {
            try {
                $this->settingModel->addCategory($form->values);
                $this->flashMessage('Kategorie byla úspěšně přidaná.', 'success');
                $this->redirect(':viewCategory');
            } catch (\PDOException|DupliciteCategoryException $exception) {
                $this->flashMessage($exception->getMessage(), 'error');
            }
        } else {
            try {
                $wasUpdated = $this->settingModel->editCategory($form->values, (int) $this->getParameter('id'));

                if ($wasUpdated) {
                    $this->flashMessage('Kategorie byla úspěšně updatovaná.', 'success');
                } else {
                    $this->flashMessage('Nedošlo k žádné změně v kategorii.', 'info');
                }
                $this->redirect(':viewCategory');
            } catch (\PDOException|DupliciteUserException|AccessUserException $exception) {
                $this->flashMessage($exception->getMessage(), 'error');
            }
        }
    }

    public function renderRemoveCategory(int $id): void
    {

    }
}

class AccessUserException extends Exception
{

}