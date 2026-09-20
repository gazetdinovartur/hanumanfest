<?php

namespace App\Admin\Field;

use App\Form\HtmlEditorType;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;

final class HtmlEditorField
{
    public static function new(string $propertyName, ?string $label = null, int $rows = 12): TextareaField
    {
        return TextareaField::new($propertyName, $label)
            ->setFormType(HtmlEditorType::class)
            ->setNumOfRows($rows)
            ->setHelp('Визуальный редактор. Кнопка «HTML» — правка разметки вручную.');
    }
}
