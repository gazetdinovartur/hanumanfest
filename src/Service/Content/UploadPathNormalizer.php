<?php

namespace App\Service\Content;

/**
 * Normalizes public upload paths between DB storage (/uploads/…) and EasyAdmin fields.
 *
 * Form values are always relative to public/uploads/ (e.g. hero/a.jpg, wp/2025/10/x.jpg)
 * so legacy WP files and new uploads share the same upload_dir.
 */
final class UploadPathNormalizer
{
    /**
     * @param array<string, string> $map property name => default uploads subdirectory
     */
    public function stripForForm(object $entity, array $map): void
    {
        foreach ($map as $property => $subdir) {
            unset($subdir);
            $getter = 'get'.ucfirst($property);
            $setter = 'set'.ucfirst($property);
            if (!method_exists($entity, $getter) || !method_exists($entity, $setter)) {
                continue;
            }

            $value = $entity->$getter();
            if (!\is_string($value) || '' === $value) {
                continue;
            }

            $relative = PublicUploadPath::relativeWithinUploads($value);
            if (null === $relative) {
                continue;
            }

            $entity->$setter($relative);
        }
    }

    /**
     * @param array<string, string> $map property name => uploads subdirectory
     */
    public function expandForStorage(object $entity, array $map): void
    {
        foreach ($map as $property => $subdir) {
            $getter = 'get'.ucfirst($property);
            $setter = 'set'.ucfirst($property);
            if (!method_exists($entity, $getter) || !method_exists($entity, $setter)) {
                continue;
            }

            $value = $entity->$getter();
            if (!\is_string($value) || '' === trim($value)) {
                $this->setEmpty($entity, $setter);
                continue;
            }

            if (str_starts_with($value, '/uploads/')) {
                continue;
            }

            if (str_contains($value, '/')) {
                $entity->$setter('/uploads/'.$value);

                continue;
            }

            $entity->$setter(PublicUploadPath::storagePath($subdir, $value));
        }
    }

    public function reset(object $entity): void
    {
    }

    private function setEmpty(object $entity, string $setter): void
    {
        $parameter = (new \ReflectionMethod($entity, $setter))->getParameters()[0] ?? null;
        if (null === $parameter || !$parameter->allowsNull()) {
            return;
        }

        $entity->$setter(null);
    }
}
