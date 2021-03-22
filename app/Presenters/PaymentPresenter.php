<?php

declare(strict_types=1);
namespace App\Presenters;


use App\Form\BasicForm;
use App\Model;

class PaymentPresenter extends BasePresenter
{
    private $startInterval;
    private $endInterval;

    public function __construct(private Model\ImportModel $importModel, private Model\PaymentModel $paymentModel, private Model\PairModel $pairModel)
    {}

    public function handlePair(int $id, $confirmed=false): void
    {
        $parameters = $this->getParameters();
        if (array_key_exists('selectedPayments', $parameters)) {
            try {
                $this->pairModel->manualPair($id, $parameters['selectedPayments'], $confirmed);

                if (count($parameters) == 1) {
                    $this->flashMessage('Platba a doklad byly úspěšně spárované.', 'success');
                } else {
                    $this->flashMessage('Platby a doklad byly úspěšně spárované.', 'success');
                }
            } catch (\PDOException|AccessUserException $exception) {
                $this->flashMessage($exception->getMessage(), 'error');
            } catch (Model\ManualPairDifferenceWarning $exception) {
                $this->flashMessage($exception->getMessage(), 'info');
                $this->flashMessage('Je potřeba potvrdit párování.', 'info');
                $this->redirect(':pair', $parameters['selectedPayments'], $id);
            }
        } else {
            $this->flashMessage('Vyberte platby pro párování.', 'info');
        }

        $this->redirect(':pair');
    }

    public function handleCashConsumption(int $id): void
    {
        try {
            $this->pairModel->cashConsumption($id);

            $this->flashMessage('Platba v hotovosti byla označená za výdajovou, proto se započítá do součtu výdajů.', 'info');
        } catch (\PDOException|AccessUserException $exception) {
            $this->flashMessage($exception->getMessage(), 'error');
        }

        $this->redirect(':pair');
    }

    public function handleCashNotConsumption(int $id): void
    {
        try {
            $this->pairModel->cashNotConsumption($id);

            $this->flashMessage('Platba v hotovosti byla označená za nevýdajovou, proto se nezapočítá do součtu výdajů.', 'info');
        } catch (\PDOException|AccessUserException $exception) {
            $this->flashMessage($exception->getMessage(), 'error');
        }

        $this->redirect(':pair');
    }

    public function handleNotConsumption(int $id): void
    {
        try {
            $this->pairModel->notConsumption($id);

            $this->flashMessage('Platba byla označená za nevýdajovou, proto se nezapočítá do součtu výdajů.', 'info');
        } catch (\PDOException|AccessUserException $exception) {
            $this->flashMessage($exception->getMessage(), 'error');
        }

        $this->redirect(':pair');
    }

    public function renderPair(array $selectedPayments=[], int $confirmId=null): void
    {
        $this->template->payments = $this->pairModel->getPayments();
        $this->template->invoices = $this->pairModel->getInvoices();
        $this->template->selectedPayments = $selectedPayments;
        $this->template->confirmId = $confirmId;

        $this->template->cashPayments = $this->pairModel->getNotIdentifiedCashPayments();
    }

    public function handleActivatePaymentChannel(int $id): void
    {
        try{
            $this->paymentModel->activatePaymentChannel($id);
        } catch (AccessUserException $exception) {
            $this->flashMessage($exception->getMessage(), 'error');
        }
    }

    public function handleDeactivatePaymentChannel(int $id): void
    {
        try{
            $this->paymentModel->deactivatePaymentChannel($id);
        } catch (AccessUserException $exception) {
            $this->flashMessage($exception->getMessage(), 'error');
        }
    }

    public function handleRemovePaymentChannel(int $id): void
    {
        try{
            $this->paymentModel->removePaymentChannel($id);

            $this->flashMessage('Trvalý příkaz byl úspěšně smazaný.', 'success');
        } catch (AccessUserException $exception) {
            $this->flashMessage($exception->getMessage(), 'error');
        }
    }

    public function renderViewPaymentChannel(): void
    {
        $this->template->channels = $this->paymentModel->getPaymentChannels();
    }

    public function renderViewImport(): void
    {
        $this->template->bankAccounts = $this->paymentModel->getbankAccounts();
        $this->template->paymentModel = $this->paymentModel;
    }

    public function actionViewPayment(int $year=null, int $month=null): void
    {
        list($startInterval, $endInterval) = $this->paymentModel->getPaymentInterval();
        $this->startInterval = $startInterval;
        $this->endInterval = $endInterval;

        $startYear = (int) $startInterval->format('Y');
        $startMonth = (int) $startInterval->format('n');
        $endYear = (int) $endInterval->format('Y');
        $endMonth = (int) $endInterval->format('n');

        if ($year === null || $month === null) {
            $this->redirect(':viewPayment', [$endYear, $endMonth]);
        } elseif ($year < $startYear || ($year == $startYear && $month < $startMonth)) {
            $this->redirect(':viewPayment', [$startYear, $startMonth]);
        } elseif ($year > $endYear || ($year == $endYear && $month > $endMonth)) {
            $this->redirect(':viewPayment', [$endYear, $endMonth]);
        }
    }

    public function renderViewPayment(int $year=null, int $month=null): void
    {
        $startInterval = $this->startInterval;
        $endInterval = $this->endInterval;

        $this->template->startYear = (int) $startInterval->format('Y');
        $this->template->startMonth = (int) $startInterval->format('n');
        $this->template->endYear = (int) $endInterval->format('Y');
        $this->template->endMonth = (int) $endInterval->format('n');
        $this->template->renderYear = $year;
        $this->template->renderMonth = $month;

        $this->template->startInterval = $this->paymentModel->getFirstDayInMonth($month, $year);
        $this->template->endInterval = $this->paymentModel->getLastDayInMonth($month, $year);

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