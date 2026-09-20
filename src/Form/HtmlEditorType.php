<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\OptionsResolver\OptionsResolver;

class HtmlEditorType extends AbstractType
{
    public function getParent(): string
    {
        return TextareaType::class;
    }

    public function getBlockPrefix(): string
    {
        return 'hf_html_editor';
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'attr' => ['class' => 'hf-html-editor-input'],
        ]);
    }
}
