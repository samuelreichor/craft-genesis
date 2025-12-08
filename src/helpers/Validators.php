<?php

namespace samuelreichor\genesis\helpers;

use Craft;

class Validators
{
    /**
     * Checks if a string is a valid boolean representation.
     *
     * @param mixed $value
     * @return bool
     */
    public static function isValidBooleanString(mixed $value): bool
    {
        if (is_bool($value)) {
            return true;
        }

        if (is_string($value)) {
            $value = strtolower(trim($value));
            return in_array($value, ['true', 'false', '1', '0', 'yes', 'no', 'on', 'off'], true);
        }

        if (is_int($value)) {
            return in_array($value, [0, 1], true);
        }

        return false;
    }

    /**
     * Validates if a string is a valid language/locale code.
     *
     * @param string $code The language code to validate
     * @return bool
     */
    public static function isValidLanguageCode(string $code): bool
    {
        // Match patterns like: en, en-US, en_US, de-DE, zh-Hans, zh-Hans-CN
        $pattern = '/^[a-z]{2,3}(-[A-Za-z]{2,4})?(-[A-Za-z]{2})?$/';

        return (bool)preg_match($pattern, $code);
    }

    /**
     * Checks if a value is truthy (handles string "true", "1", etc.)
     *
     * @param mixed $value
     * @return bool
     */
    public static function isTruthy(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_string($value)) {
            $value = strtolower(trim($value));
            return in_array($value, ['true', '1', 'yes', 'on'], true);
        }

        return (bool)$value;
    }

    /**
     * Checks if a site group with the given name exists.
     *
     * @param string $groupName The group name to check
     * @return bool
     */
    public static function siteGroupExists(string $groupName): bool
    {
        $allGroups = Craft::$app->getSites()->getAllGroups();

        foreach ($allGroups as $group) {
            if ($group->getName() === $groupName) {
                return true;
            }
        }

        return false;
    }

    /**
     * Checks if a value is a valid translation method
     *
     * @param mixed $value
     * @return bool
     */
    public static function isValidTranslationMethod(mixed $value): bool
    {
        if (is_string($value)) {
            return in_array($value, [
                'Not translatable',
                'Translate for each site',
                'Translate for each site group',
                'Translate for each language',
                'Custom…',
                'none',
                'site',
                'siteGroup',
                'language',
                'custom',
            ], true);
        }

        return false;
    }

    /**
     * Checks if a value is a valid custom translation method
     *
     * @param mixed $value
     * @return bool
     */
    public static function isValidCustomTranslationMethod(mixed $value): bool
    {
        if (is_string($value)) {
            return in_array($value, ['custom', 'Custom…'], true);
        }

        return false;
    }

    /**
     * Checks if a value is a valid section type
     *
     * @param mixed $value
     * @return bool
     */
    public static function isValidSectionType(mixed $value): bool
    {
        if (is_string($value)) {
            return in_array($value, [
                'single',
                'channel',
                'structure',
            ], true);
        }

        return false;
    }

    /**
     * Checks if a value is a valid propagation method
     *
     * @param mixed $value
     * @return bool
     */
    public static function isValidPropagationMethod(mixed $value): bool
    {
        if (is_string($value)) {
            return in_array($value, [
                'Only save entries to the site they were created in',
                'Save entries to other sites in the same site group',
                'Save entries to other sites with the same language',
                'Save entries to all sites enabled for this section',
                'Let each entry choose which sites it should be saved to',
                'none',
                'siteGroup',
                'language',
                'all',
                'custom',
            ], true);
        }

        return false;
    }

    /**
     * Checks if a value is a valid default placement
     *
     * @param mixed $value
     * @return bool
     */
    public static function isValidDefaultPlacement(mixed $value): bool
    {
        if (is_string($value)) {
            return in_array($value, [
                'Before other entries',
                'After other entries',
                'beginning',
                'end',
            ], true);
        }

        return false;
    }

    /**
     * Checks if a site with the given handle exists.
     *
     * @param string $handle The site handle to check
     * @return bool
     */
    public static function siteExists(string $handle): bool
    {
        return Craft::$app->getSites()->getSiteByHandle($handle) !== null;
    }

    /**
     * Checks if an entry type with the given handle exists.
     *
     * @param string $handle The entry type handle to check
     * @return bool
     */
    public static function entryTypeExists(string $handle): bool
    {
        foreach (Craft::$app->getEntries()->getAllEntryTypes() as $entryType) {
            if ($entryType->handle === $handle) {
                return true;
            }
        }

        return false;
    }

    /**
     * Checks if a value is a positive integer.
     *
     * @param mixed $value
     * @return bool
     */
    public static function isPositiveInteger(mixed $value): bool
    {
        if (is_int($value)) {
            return $value > 0;
        }

        if (is_string($value) && ctype_digit($value)) {
            return (int)$value > 0;
        }

        return false;
    }

    /**
     * Checks if a filesystem with the given handle exists.
     *
     * @param string $handle The filesystem handle to check
     * @return bool
     */
    public static function filesystemExists(string $handle): bool
    {
        return Craft::$app->getFs()->getFilesystemByHandle($handle) !== null;
    }
}
