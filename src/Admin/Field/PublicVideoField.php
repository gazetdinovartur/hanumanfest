<?php

namespace App\Admin\Field;

use App\Service\Content\PublicUploadPath;
use EasyCorp\Bundle\EasyAdminBundle\Field\FileField;

final class PublicVideoField
{
    public static function new(string $propertyName, string $label, string $subdir): FileField
    {
        $subdir = trim($subdir, '/');

        return FileField::new($propertyName, $label)
            ->setBasePath('uploads')
            ->setUploadDir('public/uploads/')
            ->setUploadedFileNamePattern($subdir.'/[uuid].[extension]')
            ->setRequired(false)
            ->setTemplatePath('admin/field/public_video.html.twig')
            ->setFormTypeOption('upload_delete', PublicUploadPath::deleteLocalFileUnlessSharedWp(...))
            ->mimeTypes('video/mp4,video/webm,video/quicktime,.mp4,.webm,.mov')
            ->maxSize('80M')
            ->setHelp('MP4, WebM или MOV · до 80 МБ');
    }
}
