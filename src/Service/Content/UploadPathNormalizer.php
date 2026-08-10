<?php

namespace App\Service\Content;

/**
 * Normalizes public upload paths between DB storage (/uploads/…) and EasyAdmin fields.
 *
 * Files in the expected subdirectory are reduced to basename; legacy paths (e.g. wp/…) stay
 * relative to public/uploads/ so the form can locate them on disk.
 */
final class UploadPathNormalizer
{
    /** @var array<int, array<string, bool>> spl_object_id => property => uses uploads root */
    private array $usesUploadsRoot = [];

    /**
     * @param array<string, string> $map property name => default uploads subdirectory
     */
    public function stripForForm(object $entity, array $map): void
    {
        $objectId = spl_object_id($entity);

        foreach ($map as $property => $subdir) {
            $getter = 'get'.ucfirst($property);
            $setter = 'set'.ucfirst($property);
            if (!method_exists($entity, $getter) || !method_exists($entity, $setter)) {
                continue;
            }

            $value = $entity->$getter();
            if (!\is_string($value) || '' === $value) {
                continue;
            }

            $parsed = PublicUploadPath::parseStored($value);
            if (null === $parsed) {
                continue;
            }

            $expectedDir = PublicUploadPath::downloadDir($subdir);
            if ($parsed['downloadDir'] === $expectedDir) {
                $entity->$setter($parsed['basename']);
                unset($this->usesUploadsRoot[$objectId][$property]);
                continue;
            }

            $relative = PublicUploadPath::relativeWithinUploads($value);
            if (null === $relative) {
                continue;
            }

            $entity->$setter($relative);
            $this->usesUploadsRoot[$objectId][$property] = true;
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
            if (!\is_string($value) || '' === $value) {
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

    public function usesUploadsRoot(object $entity, string $property): bool
    {
        return $this->usesUploadsRoot[spl_object_id($entity)][$property] ?? false;
    }

    public function reset(object $entity): void
    {
        unset($this->usesUploadsRoot[spl_object_id($entity)]);
    }
}
