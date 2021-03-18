<?php

declare(strict_types=1);
namespace App\Presenters;

use App\Form\BasicForm;
use App\Model;

class PaymentPresenter extends BasePresenter
{
    public function __construct(private Model\ImportModel $importModel, private Model\PaymentModel $paymentModel)
    {}

    public function renderViewImport(): void
    {
        $this->template->bankAccounts = $this->paymentModel->getbankAccounts();
        $this->template->paymentModel = $this->paymentModel;
    }

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
        try {
            $result = $this->importModel->import($form->values);
            $countSaved = $result['countSaved'];
            $countDuplicate = $result['countDuplicate'];

            $this->flashMessage('Výpis z bankovního účtu se podařilo úspěšně uložit.', 'success');
            $this->flashMessage('Celkem '.$countSaved.' plateb bylo uloženo.', 'info');
            if ($countDuplicate == 0) {
                $this->flashMessage('Žádné platby nebyly duplicitní.', 'info');
            } else {
                $this->flashMessage('Celkem '.$countDuplicate.' plateb bylo duplicitních, a ty nebyly uloženy.');
            }
        } catch (\PDOException|AccessUserException|Model\InvalidFileValueException|Model\InvalidFileFormatException $exception) {
            $this->flashMessage($exception->getMessage(), 'error');
        } catch (Model\DuplicateImportException $exception) {
            $this->flashMessage($exception->getMessage(), 'info');
        }

        $this->redirect(':default');
    }
}