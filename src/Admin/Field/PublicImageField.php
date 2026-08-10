<?php

namespace App\Admin\Field;

use EasyCorp\Bundle\EasyAdminBundle\Field\ImageField;

final class PublicImageField
{
    public static function new(string $propertyName, string $label, string $subdir): ImageField
    {
        $base = 'uploads/'.$subdir;

        return ImageField::new($propertyName, $label)
            ->setBasePath('uploads')
            ->setUploadDir('public/'.$base)
            ->setUploadedFileNamePattern('[uuid].[extension]')
            ->setTemplatePath('admin/field/public_image.html.twig');
    }

    public static function thumbnail(string $propertyName, string $subdir): ImageField
    {
        return self::new($propertyName, ' ', $subdir)
            ->onlyOnIndex()
            ->setSortable(false);
    }
}
