<?php

namespace Core\File;

use Core\Http\Request;
use Core\Valid\Hash;

/**
 * File uploaded.
 *
 * @class File
 * @package \Core\File
 */
class File
{
    /**
     * Request object.
     * 
     * @var Request $request
     */
    private $request;

    /**
     * File object.
     * 
     * @var object $file
     */
    private $file;

    /**
     * Init objek.
     * 
     * @param Request $request
     * @return void
     */
    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    /**
     * Dapatkan file dari request.
     * 
     * @param string $name
     * @return void
     */
    public function getFromRequest(string $name): void
    {
        $this->file = (object) $this->request->get($name);
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
        return move_uploaded_file(
            $this->file->tmp_name,
            basepath() . '/' . $folder . '/' . $name . '.' . $this->getClientOriginalExtension()
        );
    }
}
