<?php

declare(strict_types=1);
namespace App\Presenters;


use App\Form\BasicForm;
use App\Model;
use Nette\Utils\DateTime;

class ConsumptionPresenter extends BasePresenter
{
    private $startInterval;
    private $endInterval;

    public  function __construct(private Model\ConsumptionModel $consumptionModel)
    {}

    public function actionDefault(string $renderBy='year', int $year=null, int $month=null, int $category=null, int $consumer=null): void
    {
        if (! in_array($renderBy, [null, 'year', 'month', 'category', 'consumer'])) {
            $this->redirect(':default', ['renderBy' => null, 'year' => $year, 'month' => $month, 'category' => $category, 'consumer' => $consumer]);
        }

        list($startInterval, $endInterval) = $this->consumptionModel->getPaymentInterval();
        $this->startInterval = $startInterval;
        $this->endInterval = $endInterval;

        $startYear = (int) $startInterval->format('Y');
        $startMonth = (int) $startInterval->format('n');
        $endYear = (int) $endInterval->format('Y');
        $endMonth = (int) $endInterval->format('n');

        if ($month !== null && $year === null) {
            $this->redirect(':default', ['renderBy' => $renderBy, 'year' => null, 'month' => null, 'category' => $category, 'consumer' => $consumer]);
        }

        if ($renderBy == 'year') {
            if ($year !== null || $month !== null) {
                $this->redirect(':default', ['renderBy' => $renderBy, 'year' => null, 'month' => null, 'category' => $category, 'consumer' => $consumer]);
            }
        } elseif ($renderBy == 'month') {
            if ($month !== null) {
                $this->redirect(':default', ['renderBy' => $renderBy, 'year' => $year, 'month' => null, 'category' => $category, 'consumer' => $consumer]);
            }
        } elseif ($renderBy == 'category') {
            if ($category !== null) {
                $this->redirect(':default', ['renderBy' => $renderBy, 'year' => $year, 'month' => $month, 'category' => null, 'consumer' => $consumer]);
            }
        } elseif ($renderBy == 'consumer') {
            if ($consumer !== null) {
                $this->redirect(':default', ['renderBy' => $renderBy, 'year' => $year, 'month' => $month, 'category' => $category, 'consumer' => null]);
            }
        }

        if ($year !== null && $month !== null) {
            if ($year < $startYear || $year > $endYear) {
                $this->redirect(':default', ['renderBy' => $renderBy, 'year' => null, 'month' => null, 'category' => $category, 'consumer' => $consumer]);
            } elseif (($year == $startYear && $month < $startMonth) || ($year == $endYear && $month > $endMonth)) {
                $this->redirect(':default', ['renderBy' => $renderBy, 'year' => $year, 'month' => null, 'category' => $category, 'consumer' => $consumer]);
            } elseif ($month < 1 || $month > 12) {
                $this->redirect(':default', ['renderBy' => $renderBy, 'year' => $year, 'month' => null, 'category' => $category, 'consumer' => $consumer]);
            }
        } elseif ($year !== null) {
            if ($year < $startYear || $year > $endYear) {
                $this->redirect(':default', ['renderBy' => $renderBy, 'year' => null, 'month' => null, 'category' => $category, 'consumer' => $consumer]);
            }
        }

        if ($category !== null) {
            if (!$this->consumptionModel->canAccessCategory($category)){
                $this->redirect(':default', ['renderBy' => $renderBy, 'year' => $year, 'month' => $month, 'category' => null, 'consumer' => $consumer]);
            }
        }

        if ($consumer !== null) {
            if (!$this->consumptionModel->canAccessConsumer($consumer)){
                $this->redirect(':default', ['renderBy' => $renderBy, 'year' => $year, 'month' => $month, 'category' => $category, 'consumer' => null]);
            }
        }
    }

    public function renderDefault(string $renderBy='year', int $year=null, int $month=null, int $category=null, int $consumer=null): void
    {
        list($initialCashAccountAmount, $initialCashAccountDate) = $this->consumptionModel->getInitialCashAccountState();
        $this->template->initialCashAccountAmount = $initialCashAccountAmount;
        $this->template->initialCashAccountDate = $initialCashAccountDate;

        $this->template->actualCashAccountAmount = $this->consumptionModel->getActualCashAccountAmount($initialCashAccountAmount, $initialCashAccountDate);
        $this->template->actualCashAccountDate = new DateTime();

        $this->template->renderBy = $renderBy;
        $this->template->renderYear = $year;
        $this->template->renderMonth = $month;
        $this->template->renderCategory = $category;
        $this->template->renderConsumer = $consumer;

        $this->template->categories = $this->consumptionModel->getCategories();
        $this->template->consumers = $this->consumptionModel->getConsumers();

        $startInterval = $this->startInterval;
        $endInterval = $this->endInterval;

        $this->template->startYear = (int) $startInterval->format('Y');
        $this->template->startMonth = (int) $startInterval->format('n');
        $this->template->endYear = (int) $endInterval->format('Y');
        $this->template->endMonth = (int) $endInterval->format('n');

        $this->template->consumptionModel = $this->consumptionModel;
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