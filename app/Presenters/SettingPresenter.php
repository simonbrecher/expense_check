<?php

declare(strict_types=1);
namespace App\Presenters;

use App\Model;

class SettingPresenter extends BasePresenter
{
    public function __construct(private Model\SettingModel $settingModel)
    {}

    public function renderviewCategory(): void
    {
        $categories = $this->settingModel->getCategories();
        $this->template->categories = $categories;
    }

    public function renderAddCategory(int|null $id=null): void
    {
        if ($id !== null) {
            if (!$this->settingModel->canAccessCategory($id)) {
                $this->redirect(':default');
            }
        }
    }

    public function renderRemoveCategory(int|null $id=null): void
    {

    }
}