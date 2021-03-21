<?php

declare(strict_types=1);
namespace App\Form;

use App\Model;
use Nette\Application\UI\Form;

class InvoiceForm extends Form
{
    public const BOX_STYLE_MULTI_CONTROLS = ['type_paidby'];
    public const TOGGLE_BOX_HTML_IDS = array(
        'var_symbol' => 'var-symbol-toggle-box',
        'card_id' => 'card-id-toggle-box',
        'category' => 'toggle-not-paidby-atm',
        'consumer' => 'toggle-not-paidby-atm',
    );
    public const TOGGLE_BUTTON_HTML_IDS = ['add' => 'toggle-not-paidby-atm', 'remove' => 'toggle-not-paidby-atm'];

    private $focusedControl;

    public function __construct(private Model\InvoiceModel $invoiceModel)
    {}

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

                $container->addText('czk_amount', 'Cena položky:');

                $container->addText('description', 'Název položky:')
                    ->setMaxLength(35);

                $editId = $this->getPresenter()->getParameter('id');

                $categories = $this->getPresenter()->invoiceModel->getCategorySelect($editId);
                $container->addSelect('category', 'Kategorie:', $categories)
                    ->setPrompt('');

                $consumers = $this->getPresenter()->invoiceModel->getConsumerSelect($editId);
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