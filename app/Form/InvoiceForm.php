<?php

declare(strict_types=1);
namespace App\Form;

use Nette;

use App\Model;
use Tracy\Debugger;

class InvoiceForm extends BaseForm
{
    public const BOX_STYLE_MULTI_CONTROLS = ['type_paidby'];
    public const TOGGLE_BOX_HTML_IDS = ['var_symbol' => 'var-symbol-toggle-box', 'card_id' => 'card-id-toggle-box'];

    /** @var Model\InvoiceModel */
    private $invoiceModel;
    /** @var Nette\Forms\Controls\BaseControl | null */
    private $focusedControl;

    public function __construct(Model\InvoiceModel $invoiceModel)
    {
        $this->invoiceModel = $invoiceModel;
    }

    public function createItems(): void
    {
        $this->focusedControl = $this['czk_total_amount'];
        $this->addItem(0);
    }

    public function addItem(int $addItemCount = 1): void
    {
        $this->addGroup('other');

            $itemCountComponent = $this['item_count'] ?? $this->addHidden('item_count');
            $itemsContainer = $this['items'] ?? $this->addContainer('items');

        $oldItemCount = (int)$itemCountComponent->value;
        $newItemCount = max(0, min($oldItemCount + $addItemCount, $this->invoiceModel::MAX_ITEM_COUNT));
        $currentItemCount = $itemsContainer->components->count();

        if ($currentItemCount < $newItemCount) {
            for ($i = $currentItemCount; $i < $newItemCount; $i++) {
                $container = $itemsContainer->addContainer($i + 1);

                $container->addText('czk_amount', 'Cena položky:')
                    ->addRule($this::FILLED, 'Doplňte cenu.')
                    ->addRule($this::NUMERIC, 'Neplatný formát ceny.');

                $container->addText('description', 'Název položky:')
                    ->setMaxLength(35);

                $categories = $this->getPresenter()->invoiceModel->getUserCategories();
                $container->addSelect('category', 'Kategorie:', $categories)
                    ->setPrompt('');

                $consumers = $this->getPresenter()->invoiceModel->getUserConsumers();
                $container->addSelect('consumer', 'Spotřebitel:', $consumers)
                    ->setPrompt('');
            }
        } elseif ($currentItemCount > $newItemCount) {
            for ($i = $currentItemCount; $i > $newItemCount; $i --) {
                $component = $itemsContainer[$i] ?? null;
                if ($component) {
                    $itemsContainer->removeComponent($component);
                }
            }
        }

        if ($addItemCount < 0) {
            $this->focusedControl = null;
        } elseif ($addItemCount > 0) {
            $this->focusedControl = $this['items'][$newItemCount]['czk_amount'];
        }

        $this->setFocusedControl();

        $itemCountComponent->value = $currentItemCount = $itemsContainer->components->count();
    }

    private function setFocusedControl(): void
    {
        $this->focusedControl?->setHtmlAttribute('autofocus', true);
    }

    public function removeItem(): void
    {
        $this->addItem(-1);
    }
}