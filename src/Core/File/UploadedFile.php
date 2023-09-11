<?php

namespace Core\File;

use Core\Valid\Hash;

/**
 * File uploaded.
 *
 * @class UploadedFile
 * @package \Core\File
 */
class UploadedFile extends File
{
    /**
     * File object.
     *
     * @var object $file
     */
    private $file;

    /**
     * Parse dari raw request file.
     *
     * @param array $files
     * @return File|array<int, File>
     */
    public static function parse(array $files): File|array
    {
        $data = [];
        foreach ($files as $key => $value) {
            $path = $value['tmp_name'];
            if (is_string($path)) {
                $file = new static($path);
                $data[$key] = $file->setFromArray($value);
                continue;
            }

            $uploadedKey = array_keys($value);
            $value['files'] = [];
            foreach ($path as $index => $_) {

                $value['tmp_file'] = [];
                foreach ($uploadedKey as $keyUploaded) {
                    $value['tmp_file'][$keyUploaded] = $value[$keyUploaded][$index];
                }

                $file = new static($value['tmp_file']['tmp_name']);
                $value['files'][$index] = $file->setFromArray($value['tmp_file']);
                unset($value['tmp_file'], $_);
            }

            $data[$key] = $value['files'];
            unset($value['files']);
        }

        return $data;
    }

    /**
     * Set manual dari array.
     *
     * @param array $name
     * @return UploadedFile
     */
    public function setFromArray(array $name): UploadedFile
    {
        $this->file = (object) $name;

        return $this;
    }

    /**
     * Dapatkan nama aslinya.
     *
     * @return string
     */
    public function getClientOriginalName(): string
    {
        return pathinfo($this->file->name, PATHINFO_FILENAME);
    }

    /**
     * Get file.
     *
     * @return array
     */
    public function getFile(): array
    {
        return (array) $this->file;
    }

    /**
     * Dapatkan extensi aslinya.
     *
     * @return string
     */
    public function getClientOriginalExtension(): string
    {
        return pathinfo($this->file->name, PATHINFO_EXTENSION);
    }

    /**
     * Apakah ada file yang di upload?.
     *
     * @return bool
     */
    public function exists(): bool
    {
        return $this->file->error === 0;
    }

    /**
     * Dapatkan extensi aslinya dengan mime.
     *
     * @return string
     */
    public function extension(): string
    {
        return $this->file->type;
    }

    /**
     * Apakah sudah betul?.
     *
     * @return bool
     */
    public function valid(): bool
    {
        return is_uploaded_file($this->getPathname());
    }

    /**
     * Bikin namanya unik.
     *
     * @return string
     */
    public function hashName(): string
    {
        return Hash::rand(10);
    }

    /**
     * Simpan filenya.
     *
     * @param string $name
     * @param string $folder
     * @return bool
     */
    public function store(string $name, string $folder = 'shared'): bool
    {
        if (!$this->valid()) {
            return false;
        }

        return @move_uploaded_file(
            $this->getPathname(),
            base_path('/' . $folder . '/' . realpath($name) . '.' . $this->getClientOriginalExtension())
        );
    }
}
