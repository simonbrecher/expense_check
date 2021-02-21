<?php


namespace App\Form;


class InvoiceForm extends BaseForm
{
    public function createItems(): void
    {

    }

    public function addItem(int $addItemCount): void
    {
        for ($i = 0; $i < self::MAX_ITEM_COUNT; $i++) {
            $container = $form->addContainer($i);

            $categories = $this->dataLoader->getFormSelectDict('category', 'name', 'Neuvedeno');
            $container->addSelect('category', 'Kategorie:', $categories);

            $consumers = $this->dataLoader->getFormSelectDict('consumer', 'name', 'Všichni');
            $container->addSelect('consumer', 'Spotřebitel:', $consumers);

            if ($i == 0) {
                $form->addText('totalPrice', 'Celková cena:')
                    ->addRule($form::FILLED, 'Doplňte celkovou cenu.');
            } else {
                $container->addText('price', 'Cena položky:')
                    ->addCondition($form::FILLED, 'Doplňte cenu '.($i + 1).' položky.');
            }

            $container->addText('description', $i == 0 ? 'Název:' : 'Název položky:')
                ->setMaxLength(35);
        }
    }

    public function removeItem(): void
    {
        $this->addItem(-1);
    }
}