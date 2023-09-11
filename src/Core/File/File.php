<?php

namespace Core\File;

use SplFileInfo;

/**
 * Representation of file.
 *
 * @class File
 * @package \Core\File
 */
class File extends SplFileInfo
{
    /**
     * Create a new file.
     *
     * @param string $name
     * @param string $data
     * @param int $perms
     * @param int|null $flag
     * @return bool
     */
    public static function create(string $name, string $data, int $perms = 0755, int|null $flag = null): bool
    {
        $result = file_put_contents($name, $data, $flag);
        if (!$result) {
            return false;
        }

        return chmod($name, $perms);
    }

    /**
     * Copy filenya.
     *
     * @param string $from
     * @param string $to
     * @return bool
     */
    public static function copy(string $from, string $to): bool
    {
        return copy($from, $to);
    }

    /**
     * Hapus filenya.
     *
     * @param string $name
     * @return bool
     */
    public static function delete(string $name): bool
    {
        return unlink($name);
    }
}
