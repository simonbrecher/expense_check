<?php

declare(strict_types=1);
namespace App\Presenters;

use App\Form\BasicForm;
use App\Model;

class PaymentPresenter extends BasePresenter
{
    public function __construct(private Model\PaymentModel $paymentModel)
    {}

    public function createComponentImportForm(): BasicForm
    {
        $form = new BasicForm();

        $form->addGroup('body');

            $form->addUpload('import', 'Výpis z bankovního účtu:')
                ->setRequired(true)
                ->addRule($form::MAX_FILE_SIZE, 'Maximální velikost souboru musí být 4 mB.', 4  * 1024 * 1024);

        $form->addGroup('buttons');

            $form->addSubmit('submit', 'Uložit');

//            if ($this->getParameter('id') !== null) {
//                $form->addSubmit('delete', 'Smazat')->setValidationScope([])
//                    ->setHtmlAttribute('class', 'delete');
//            }

        $form->onSuccess[] = [$this, 'importFormSuccess'];

//            if ($this->getParameter('id') !== null) {
//                $form->setDefaults($this->settingModel->getCategoryParameters((int) $this->getParameter('id')));
//            }

        return $form;
    }

    public function importFormSuccess(BasicForm $form): void
    {
        $this->paymentModel->import($form->values);
    }
}