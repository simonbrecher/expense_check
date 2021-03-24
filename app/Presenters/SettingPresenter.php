<?php

declare(strict_types=1);
namespace App\Presenters;


use App\Form\BasicForm;
use App\Model;
use App\Model\DupliciteCategoryException;
use App\Model\DupliciteUserException;
use Nette\Neon\Exception;

class SettingPresenter extends BasePresenter
{
    public function __construct(private Model\SettingModel $settingModel)
    {}

    public function actionDefault(): void
    {
        $this->redirect(':viewCategory');
    }

    public function renderViewCategory(): void
    {
        $categories = $this->settingModel->getCategories();
        $this->template->categories = $categories;
        $this->template->settingModel = $this->settingModel;
    }

    public function actionAddCategory(int $id=null): void
    {
        if ($id !== null) {
            if (!$this->settingModel->canAccessCategory($id)) {
                $this->redirect(':default');
            }
        }
    }

    public function renderAddCategory(int $id=null): void
    {
        $this->template->id = $id;
    }

    public function createComponentAddCategoryForm(): BasicForm
    {
        $form = new BasicForm();

        $form->addGroup('body');

            $form->addText('name', 'Název:');

            $form->addTextArea('description', 'Popis:');

            $isActiveSelect = $this->settingModel->getIsActiveSelect();
            $form->addSelect('is_active', 'Je aktivní:', $isActiveSelect)->setDefaultValue(1);

        $form->addGroup('buttons');

            $form->addSubmit('submit', 'Uložit');

            if ($this->getParameter('id') !== null) {
                $form->addSubmit('delete', 'Smazat')->setValidationScope([])
                    ->setHtmlAttribute('class', 'delete');
            }

        $form->onSuccess[] = [$this, 'addCategoryFormSuccess'];

        if ($this->getParameter('id') !== null) {
            $form->setDefaults($this->settingModel->getCategoryParameters((int) $this->getParameter('id')));
        }

        return $form;
    }

    public function addCategoryFormSuccess(BasicForm $form): void
    {
        $submittedBy = $form->isSubmitted()->name;
        if ($this->getParameter('id') === null) {
            try {
                $this->settingModel->addCategory($form->values);
                $this->flashMessage('Kategorie byla úspěšně přidaná.', 'success');
                $this->redirect(':viewCategory');
            } catch (\PDOException|DupliciteCategoryException $exception) {
                $this->flashMessage($exception->getMessage(), 'error');
            }
        } elseif ($submittedBy == 'submit') {
            try {
                $wasUpdated = $this->settingModel->editCategory($form->values, (int) $this->getParameter('id'));

                if ($wasUpdated) {
                    $this->flashMessage('Kategorie byla úspěšně upravená.', 'success');
                } else {
                    $this->flashMessage('Nedošlo k žádné změně v kategorii.', 'info');
                }
                $this->redirect(':viewCategory');
            } catch (\PDOException|DupliciteUserException|AccessUserException $exception) {
                $this->flashMessage($exception->getMessage(), 'error');
            }
        } elseif ($submittedBy == 'delete') {
            $this->redirect(':removeCategory', (int) $this->getParameter('id'));
        } else {
            throw new Exception('Form submitted by unknown button: '.$submittedBy);
        }
    }

    public function actionRemoveCategory(int $id): void
    {
        if (!$this->settingModel->canAccessCategory($id)) {
            $this->redirect(':default');
        }

        $itemCount = $this->settingModel->getCategoryItemCount($id);
        if ($itemCount > 0) {
            $this->flashMessage('V této kategorii jsou položky, a proto ji nelze smazat.', 'error');
            $this->redirect(':viewCategory');
        }
    }

    public function handleRemoveCategory(int $id): void
    {
        try {
            $this->settingModel->removeCategory($id);
            $this->flashMessage('Kategorie byla úspěšně smazána.', 'success');
        } catch (\PDOException $exception) {
            $this->flashMessage($exception->getMessage(), 'error');
        }
        $this->redirect(':viewCategory');
    }

    public function handleNotRemoveCategory(): void
    {
        $this->flashMessage('Kategorie nebyla smazaná.', 'info');
        $this->redirect(':viewCategory');
    }

    public function renderRemoveCategory(int $id): void
    {
        $this->template->category = $this->settingModel->getCategoryName($id);
        $this->template->id = $id;
    }
}

class AccessUserException extends Exception
{

}