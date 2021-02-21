<?php


namespace App\Form;

use App\Model;
use Tracy\Debugger;

class InvoiceForm extends BaseForm
{
    public const BOX_STYLE_MULTI_CONTROLS = ['type_paidby'];
    public const TOGGLE_BOX_HTML_IDS = ['var_symbol' => 'var-symbol-toggle-box', 'card_id' => 'card-id-toggle-box'];

    private $invoiceModel;

    public function __construct(Model\InvoiceModel $invoiceModel)
    {
        $this->invoiceModel = $invoiceModel;
    }

    public function createItems(): void
    {
        $this->addItem(0);
    }

    public function addItem(int $addItemCount = 1): void
    {
        Debugger::barDump($addItemCount);

        $this->addGroup('other');

            $itemCountComponent = $this['item_count'] ?? $this->addHidden('item_count', 0);
            $itemsContainer = $this['items'] ?? $this->addContainer('items');

        $oldItemCount = $itemCountComponent->value;
        $newItemCount = max(0, min($oldItemCount + $addItemCount, $this->invoiceModel::MAX_ITEM_COUNT));
        $currentItemCount = $itemsContainer->components->count();

        Debugger::barDump($oldItemCount);
        Debugger::barDump($newItemCount);
        Debugger::barDump($currentItemCount);

        if ($currentItemCount < $newItemCount) {
            Debugger::barDump('here');
            for ($i = $currentItemCount; $i < $newItemCount; $i++) {
                $container = $itemsContainer->addContainer($i + 1);

                $container->addText('czk_price', 'Celková cena:')
                    ->addRule($this::FILLED, 'Doplňte celkovou cenu.');

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
                Debugger::barDump('here '.$i);
                $component = $itemsContainer[$i] ?? null;
                Debugger::barDump($component);
                Debugger::barDump($itemsContainer->getComponents());
                if ($component) {
                    $itemsContainer->removeComponent($component);
                }
            }
        }

        $itemCountComponent->value = $currentItemCount = $itemsContainer->components->count();

        Debugger::barDump($itemCountComponent->value);
    }

    public function removeItem(): void
    {
        $this->addItem(-1);
    }
}