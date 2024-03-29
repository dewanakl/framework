<?php

namespace Core\Valid;

use Core\File\UploadedFile;
use Exception;

/**
 * Validasi sebuah nilai.
 *
 * @class Validator
 * @package \Core\Valid
 */
class Validator
{
    /**
     * Nama dari class ini untuk translate.
     *
     * @var string NAME
     */
    public const NAME = 'validator';

    /**
     * Data yang akan di validasi.
     *
     * @var array $data
     */
    private $data;

    /**
     * Error tampung disini.
     *
     * @var array $errors
     */
    private $errors;

    /**
     * Ignore errornya.
     *
     * @var array|null $ignore
     */
    private $ignore;

    /**
     * Init object.
     *
     * @param array $data
     * @param array $rule
     * @return void
     */
    public function __construct(array $data = [], array $rule = [])
    {
        $this->setData($data);
        $this->validate($rule);
    }

    /**
     * Set datanya.
     *
     * @return void
     */
    private function setData(array $data): void
    {
        $this->data = $data;
        $this->errors = [];
    }

    /**
     * Validasi rule yang masuk.
     *
     * @param string $param
     * @param array $rules
     * @return void
     */
    private function validateRule(string $param, array $rules): void
    {
        foreach ($rules as $rule) {
            if (!empty($this->errors[$param])) {
                continue;
            }

            $value = $this->__get($param);

            if (!($value instanceof UploadedFile) && !is_array($value)) {
                $this->validateRequest($param, $value, $rule);
                continue;
            }

            if ($value instanceof UploadedFile) {
                $this->checkUploadedFile($value, $param, $rule);
                continue;
            }

            foreach ($value as $file) {
                if (!empty($this->errors[$param])) {
                    continue;
                }

                $this->checkUploadedFile($file, $param, $rule);
            }
        }

        foreach ($this->ignore ?? [] as $value) {
            if (empty($this->__get($value))) {
                $this->__set($value, null);
            }
        }
    }

    /**
     * Validasi rule request yang masuk.
     *
     * @param string $param
     * @param mixed $value
     * @param string $rule
     * @return void
     */
    private function validateRequest(string $param, mixed $value, string $rule): void
    {
        switch (true) {
            case $rule == 'nullable':
                if (empty($value)) {
                    $this->ignore[] = $param;
                }
                break;

            case $rule == 'required':
                if (!$this->__isset($param) || empty(trim(strval($value)))) {
                    $this->setError($param, 'request.required');
                }
                break;

            case $rule == 'email':
                if (filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    $this->__set($param, filter_var($value, FILTER_SANITIZE_EMAIL));
                } else {
                    $this->setError($param, 'request.email');
                }
                break;

            case $rule == 'dns':
                if (!checkdnsrr(explode('@', $value ?? '')[1])) {
                    $this->setError($param, 'request.dns');
                }
                break;

            case $rule == 'url':
                if (filter_var($value, FILTER_VALIDATE_URL)) {
                    $this->__set($param, filter_var($value, FILTER_SANITIZE_URL));
                } else {
                    $this->setError($param, 'request.url');
                }
                break;

            case $rule == 'uuid':
                if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/im', $value ?? '')) {
                    $this->setError($param, 'request.uuid');
                }
                break;

            case $rule == 'int':
                if (is_numeric($value)) {
                    $this->__set($param, intval($value));
                } else {
                    $this->setError($param, 'request.int');
                }
                break;

            case $rule == 'float':
                if (is_numeric($value)) {
                    $this->__set($param, floatval($value));
                } else {
                    $this->setError($param, 'request.float');
                }
                break;

            case $rule == 'str':
                $this->__set($param, strval($value));
                break;

            case $rule == 'bool':
                $this->__set($param, boolval($value));
                break;

            case $rule == 'slug':
                $this->__set($param, preg_replace('/[^\w-]/', '', $value ?? ''));
                break;

            case $rule == 'html':
                $this->__set($param, e($value));
                break;

            case $rule == 'safe':
                $bad = [...array_map('chr', range(0, 31)), '\\', '/', ':', '*', '?', '"', '<', '>', '|'];
                $this->__set($param, str_replace($bad, '', $value ?? ''));
                break;

            case $rule == 'hash':
                $this->__set($param, Hash::make($value ?? ''));
                break;

            case $rule == 'trim':
                $this->__set($param, trim($value ?? ''));
                break;

            case $rule == 'alpha_num':
                $this->__set($param, preg_replace('/[^A-Za-z0-9]/', '', $value ?? ''));
                break;

            case str_contains($rule, 'min'):
                $min = intval(explode(':', $rule)[1] ?? 0);
                if ((is_int($value) || is_float($value) ? intval($value) : strlen($value ?? '')) < $min) {
                    $this->setError($param, 'request.min', $min);
                }
                break;

            case str_contains($rule, 'max'):
                $max = intval(explode(':', $rule)[1] ?? 0);
                if ((is_int($value) || is_float($value) ? intval($value) : strlen($value ?? '')) > $max) {
                    $this->setError($param, 'request.max', $max);
                }
                break;

            case str_contains($rule, 'sama'):
                $target = explode(':', $rule)[1];
                if ($this->__get($target) !== $value) {
                    $this->setError($param, 'request.sama', $target);
                }
                break;

            case str_contains($rule, 'unik'):
                $command = explode(':', $rule);

                $model = 'App\Models\\' . (empty($command[1]) ? 'User' : ucfirst($command[1]));
                $column = $command[2] ?? $param;

                $data = (new $model)->find($value, $column);
                if ($data->{$column}) {
                    $this->setError($param, 'request.unik');
                }

                $data = null;
                unset($data);
                break;
        }
    }

    /**
     * Cek apakah file ini berbahaya?
     *
     * @param string $file
     * @return bool
     */
    private function maliciousKeywords(string $file): bool
    {
        $malicious = implode('|', [
            '\\/bin\\/bash',
            '__HALT_COMPILER',
            'Monolog',
            'PendingRequest',
            '\\<script',
            'ThinkPHP',
            'phar',
            'phpinfo',
            '\\<\\?php',
            '\\$_GET',
            '\\$_POST',
            '\\$_SESSION',
            '\\$_REQUEST',
            'whoami',
            'python',
            'composer',
            'passthru',
            'shell_exec',
            'PHPShell',
            'exec',
            'proc_open',
            'popen',
        ]);

        return (bool) preg_match(sprintf('/(%s)/im', $malicious), strval(file_get_contents($file)));
    }

    /**
     * Check file successfully uploaded.
     *
     * @param UploadedFile $value
     * @param string $param
     * @param string $rule
     * @return void
     */
    private function checkUploadedFile(UploadedFile $value, string $param, string $rule): void
    {
        $file = $value->getFile();

        if (!is_uploaded_file($file['tmp_name'])) {
            $this->setError($param, 'file.corrupt');
        } else {
            $this->validateFile($param, $file, $rule);
        }
    }

    /**
     * Validasi rule file yang masuk.
     *
     * @param string $param
     * @param array $value
     * @param string $rule
     * @return void
     *
     * @throws Exception
     */
    private function validateFile(string $param, array $value, string $rule): void
    {
        $error = [
            0 => false,
            1 => 'The uploaded file exceeds the upload_max_filesize directive in php.ini',
            2 => 'The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form',
            3 => 'The uploaded file was only partially uploaded',
            4 => false,
            6 => 'Missing a temporary folder',
            7 => 'Failed to write file to disk.',
            8 => 'A PHP extension stopped the file upload.',
        ];

        $err = $error[$value['error']];
        if ($err) {
            @unlink($value['tmp_name']);
            throw new Exception($err);
        }

        switch (true) {
            case $rule == 'required':
                if ($value['error'] === 4 || $value['size'] === 0 || empty($value['name'])) {
                    @unlink($value['tmp_name']);
                    $this->setError($param, 'file.required');
                }
                break;

            case str_contains($rule, 'min'):
                $min = intval(explode(':', $rule)[1]) * 1024;
                if ($value['size'] < $min) {
                    @unlink($value['tmp_name']);
                    $this->setError($param, 'file.min', format_bytes($min));
                }
                break;

            case str_contains($rule, 'max'):
                $max = intval(explode(':', $rule)[1]) * 1024;
                if ($value['size'] > $max) {
                    @unlink($value['tmp_name']);
                    $this->setError($param, 'file.max', format_bytes($max));
                }
                break;

            case str_contains($rule, 'mimetypes'):
                $mime = explode(':', $rule)[1];
                if (!in_array($value['type'], explode(',', $mime))) {
                    @unlink($value['tmp_name']);
                    $this->setError($param, 'file.mimetypes', $mime);
                }
                break;

            case str_contains($rule, 'mimes'):
                $mime = explode(':', $rule)[1];
                if (!in_array(pathinfo($value['full_path'], PATHINFO_EXTENSION), explode(',', $mime))) {
                    @unlink($value['tmp_name']);
                    $this->setError($param, 'file.mimes', $mime);
                }
                break;

            case $rule == 'safe':
                if ($this->maliciousKeywords($value['tmp_name'])) {
                    @unlink($value['tmp_name']);
                    $this->setError($param, 'file.unsafe');
                }
                break;
        }
    }

    /**
     * Set error to array errors.
     *
     * @param string $param
     * @param string $key
     * @param mixed $attribute
     * @return void
     */
    private function setError(string $param, string $key, mixed $attribute = null): void
    {
        $this->__set($param, null);
        if (empty($this->errors[$param]) && !in_array($param, $this->ignore ?? [])) {
            $this->errors[$param] = translate()->trans(static::NAME . '.' . $key, [
                'field' => $param,
                'attribute' => strval($attribute)
            ]);
        }
    }

    /**
     * Buat validasinya.
     *
     * @param array $data
     * @param array $rule
     * @return Validator
     */
    public static function make(array $data, array $rule): Validator
    {
        return new Validator($data, $rule);
    }

    /**
     * Tambahkan validasi lagi jika perlu.
     *
     * @param array $rules
     * @return void
     */
    public function validate(array $rules): void
    {
        foreach ($rules as $param => $rule) {
            $this->validateRule($param, $rule);
        }
    }

    /**
     * Cek apakah gagal?.
     *
     * @return bool
     */
    public function fails(): bool
    {
        return !empty($this->errors);
    }

    /**
     * Ambil data gagal validasi.
     *
     * @return array
     */
    public function failed(): array
    {
        return $this->fails() ? $this->errors : [];
    }

    /**
     * Ambil data gagal validasi hanya nilainya.
     *
     * @return array
     */
    public function messages(): array
    {
        return array_values($this->failed());
    }

    /**
     * Set error manual.
     *
     * @param array $error
     * @return void
     */
    public function throw(array $error = []): void
    {
        $this->errors = [...$this->failed(), ...$error];
    }

    /**
     * Ambil sebagian dari validasi.
     *
     * @param array $only
     * @return array
     */
    public function only(array $only): array
    {
        $temp = [];
        foreach ($only as $ol) {
            $temp[$ol] = $this->__get($ol);
        }

        return $temp;
    }

    /**
     * Ambil kecuali dari validasi.
     *
     * @param array $except
     * @return array
     */
    public function except(array $except): array
    {
        $temp = [];
        foreach ($this->get() as $key => $value) {
            if (!in_array($key, $except)) {
                $temp[$key] = $value;
            }
        }

        return $temp;
    }

    /**
     * Ambil nilai dari data ini.
     *
     * @param string|null $name
     * @param mixed $defaultValue
     * @return mixed
     */
    public function get(string|null $name = null, mixed $defaultValue = null): mixed
    {
        if ($name === null) {
            return $this->data;
        }

        return $this->data[$name] ?? $defaultValue;
    }

    /**
     * Ambil nilai dari data.
     *
     * @param string $name
     * @return mixed
     */
    public function __get(string $name): mixed
    {
        return $this->__isset($name) ? $this->data[$name] : null;
    }

    /**
     * Isi nilai data.
     *
     * @param string $name
     * @param mixed $value
     * @return void
     */
    public function __set(string $name, mixed $value): void
    {
        $this->data[$name] = $value;
    }

    /**
     * Cek nilai dari data.
     *
     * @param string $name
     * @return bool
     */
    public function __isset(string $name): bool
    {
        return isset($this->data[$name]);
    }
}
