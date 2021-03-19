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

    public function renderViewPayment(): void
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

        $form->onSuccess[] = [$this, 'importFormSuccess'];

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

    public function createComponentPaymentChannelForm(): BasicForm
    {
        $form = new BasicForm();

        $form->addGroup('column0');

            $bankAccountSelect = $this->paymentModel->getBankAccountSelect();
            $form->addSelect('bank_account_id', 'Bankovní účet: ', $bankAccountSelect)->setPrompt('')->setRequired('Vyberte bankovní účet.');

            $form->addText('var_symbol', 'Variabilní symbol: ')->setRequired('Vyplňte variabilní symbol.');

            $yesNoSelect = [1 => 'ANO', 0 => 'NE'];
            $form->addSelect('is_active', 'Je aktivní: ', $yesNoSelect)->setDefaultValue(1);

            $form->addSelect('is_consumption_type', 'Je výdaj: ', $yesNoSelect)->setDefaultValue(1);

        $form->addGroup('column1');

            $categorySelect = $this->paymentModel->getCategorySelect();
            $form->addSelect('category_id', 'Kategorie: ', $categorySelect)->setPrompt('')->setRequired('Vyberte kategorii.');

            $form->addText('counter_account_number', 'Číslo protiúčtu: ');

            $form->addText('counter_account_bank_code', 'Bankovní kód protiúčtu: ');

            $form->addTextArea('description', 'Popis: ')->setMaxLength(35);

        $form->addGroup('buttons');

            $form->addSubmit('submit', 'Uložit');

        $form->onSuccess[] = [$this, 'paymentChannelFormSuccess'];

        return $form;
    }

    public function paymentChannelFormSuccess(BasicForm $form): void
    {
        try {
            $this->paymentModel->addPaymentChannel($form);

            $this->flashMessage('Trvalý příkaz byl úspěšně přidaný.', 'success');
            $this->redirect(':viewPaymentChannel');
        } catch (\PDOException|AccessUserException $exception) {
            $this->flashMessage($exception->getMessage(), 'error');
        }
    }
}