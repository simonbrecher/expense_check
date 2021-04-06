<?php

declare(strict_types=1);
namespace App\Presenters;


use App\Form\BasicForm;
use App\Model;
use Nette\Utils\DateTime;

class ConsumptionPresenter extends BasePresenter
{
    public  function __construct(private Model\ConsumptionModel $consumptionModel)
    {}

    public function renderDefault(): void
    {
        list($initialCashAccountAmount, $initialCashAccountDate) = $this->consumptionModel->getInitialCashAccountState();
        $this->template->initialCashAccountAmount = $initialCashAccountAmount;
        $this->template->initialCashAccountDate = $initialCashAccountDate;

        $this->template->actualCashAccountAmount = $this->consumptionModel->getActualCashAccountAmount($initialCashAccountAmount, $initialCashAccountDate);
        $this->template->actualCashAccountDate = new DateTime();
    }

    public function renderEditStartBalance(): void
    {

    }

    public function createComponentEditStartBalanceForm(): BasicForm
    {
        $form = new BasicForm();

        $form->addGroup('body');

            $form->addText('d_balance', 'Datum:')->addRule($form::PATTERN, 'Nesprávný formát data', $this->consumptionModel::DATE_PATTERN_FLEXIBLE)
                ->setRequired('Doplňte datum počátečního stavu hotovosti');

            $form->addText('czk_amount', 'Stav hotovosti:');

        $form->addGroup('buttons');

            $form->addSubmit('submit', 'Upravit');

        $form->onSuccess[] = [$this, 'editStartBalanceSuccess'];

        $form->setDefaults($this->consumptionModel->getStartBalanceDefaults());

        return $form;
    }

    public function editStartBalanceSuccess(BasicForm $form): void
    {
        try {
            $this->consumptionModel->editStartBalance($form->values);

            $this->flashMessage('Počáteční stav hotovosti byl úspěšně upravený', 'success');

            $this->redirect(':default');
        } catch (\PDOException|Model\InvalidValueException $exception) {
            $this->flashMessage($exception->getMessage(), 'error');
        }
    }
}