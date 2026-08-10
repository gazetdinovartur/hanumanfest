<?php

namespace App\Admin\Field;

use EasyCorp\Bundle\EasyAdminBundle\Field\FileField;

final class PublicVideoField
{
    public static function new(string $propertyName, string $label, string $subdir): FileField
    {
        $base = 'uploads/'.$subdir;

        return FileField::new($propertyName, $label)
            ->setBasePath('uploads')
            ->setUploadDir('public/'.$base)
            ->setUploadedFileNamePattern('[uuid].[extension]')
            ->setTemplatePath('admin/field/public_video.html.twig')
            ->mimeTypes('video/mp4,video/webm,video/quicktime,.mp4,.webm,.mov')
            ->maxSize('80M')
            ->setHelp('MP4, WebM или MOV · до 80 МБ');
    }
}
