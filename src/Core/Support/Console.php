<?php

namespace Core\Support;

use Core\Database\Generator;
use Core\Database\Migration;
use Core\Routing\Route;
use Core\Valid\Hash;
use Exception;

/**
 * Saya console untuk mempermudah develop app.
 *
 * @class Console
 * @package \Core\Support
 */
class Console
{
    /**
     * Perintah untuk eksekusi.
     * 
     * @var string|null $command
     */
    private $command;

    /**
     * Optional perintah untuk eksekusi.
     * 
     * @var string $command
     */
    private $options;

    /**
     * Waktu yang dibutuhkan.
     * 
     * @var float $timenow
     */
    private $timenow;

    /**
     * Apakah versi cmd dibawah 10?.
     * 
     * @var bool $version
     */
    private $version;

    /**
     * Buat objek console.
     *
     * @param array $argv
     * @return void
     */
    function __construct(array $argv = [])
    {
        $this->timenow = env('_STARTTIME');
        $this->version = intval(php_uname('r')) >= 10 || !str_contains(php_uname('s'), 'Windows');

        array_shift($argv);
        $this->command = $argv[0] ?? null;
        $this->options = $argv[1] ?? null;

        print($this->createColor('green', "Kamu PHP Framework v1.1.25\n"));
        print($this->createColor('yellow', "Saya Console\n\n"));
    }

    /**
     * Print spasi ketika selesai.
     *
     * @return void
     */
    function __destruct()
    {
        print(PHP_EOL);
    }

    /**
     * Buat warna untuk string.
     *
     * @param string $name
     * @param string $value
     * @return string
     */
    private function createColor(string $name, string $value): string
    {
        if (!$this->version) {
            return $value;
        }

        $colors = [
            'green' => "\033[32m",
            'yellow' => "\033[33m",
            'cyan' => "\033[36m",
            'red' => "\033[31m"
        ];

        foreach ($colors as $key => $val) {
            if ($key == $name) {
                return $val . $value . "\033[37m";
            }
        }

        return $value;
    }

    /**
     * Tampilkan pesan khusus error.
     *
     * @param string $message
     * @param bool $fail
     * @param mixed $options
     * @return void
     */
    private function exception(string $message, bool $fail = true, mixed $options = null): void
    {
        if ($fail) {
            print($this->createColor('red', $message . "\n"));
            exit;
        }

        if ($options) {
            print($this->createColor('green', "\n" . strval($options) . "\n"));
        }
    }

    /**
     * Kalkulasi waktu yang dibutuhkan.
     *
     * @return string
     */
    private function executeTime(): string
    {
        $now = microtime(true);
        $result = strval(diffTime($this->timenow, $now));
        $this->timenow = $now;

        return $this->createColor('cyan', '(' . $result . ' ms)');
    }

    /**
     * Migrasi ke database.
     *
     * @param bool $up
     * @return void
     */
    private function migrasi(bool $up): void
    {
        $baseFile = basepath() . '/database/schema/';

        $files = scandir($baseFile, ($up) ? 0 : 1);
        $files = array_diff($files, array('..', '.'));

        foreach ($files as $file) {
            $arg = require $baseFile . $file;
            if (!($arg instanceof Migration)) {
                throw new Exception('File ' . $file . ' bukan migrasi !');
            }

            ($up) ? $arg->up() : $arg->down();
            $info = ($up) ? $this->createColor('green', ' Migrasi ') : $this->createColor('yellow', ' Migrasi kembali ');
            print("\n" . $file . $info . $this->executeTime());
        }
    }

    /**
     * Isi nilai ke database.
     *
     * @return void
     */
    private function generator(): void
    {
        $arg = require basepath() . '/database/generator.php';
        if (!($arg instanceof Generator)) {
            throw new Exception('File ' . $file . ' bukan generator !');
        }

        $arg->run();
        print("\nGenerator" . $this->createColor('green', ' berhasil ') . $this->executeTime());
    }

    /**
     * Load template file.
     *
     * @param mixed $name
     * @param int $tipe
     * @return mixed
     */
    private function loadTemplate(mixed $name, int $tipe): mixed
    {
        $this->exception('Butuh Nama file !', !$name);
        $type = '';
        switch ($tipe) {
            case 1:
                $type = 'templateMigrasi';
                break;
            case 2:
                $type = 'templateMiddleware';
                break;
            case 3:
                $type = 'templateController';
                break;
            default:
                $type = 'templateModel';
                break;
        }

        return require_once __DIR__ . '/../../helpers/templates/' . $type . '.php';
    }

    /**
     * Save template file.
     *
     * @param string $name
     * @param mixed $data
     * @param int $tipe
     * @return void
     */
    private function saveTemplate(string $name, mixed $data, int $tipe): void
    {
        $type = '';
        $optional = '';
        switch ($tipe) {
            case 1:
                $type = 'database/schema';
                $optional = strtotime('now') . '_';
                break;
            case 2:
                $type = 'app/Middleware';
                break;
            case 3:
                $type = 'app/Controllers';
                break;
            default:
                $type = 'app/Models';
                break;
        }

        $result = file_put_contents(basepath() . '/' . $type . '/' . $optional . $name . '.php', $data);
        $this->exception('Gagal membuat ' . $type, !$result, 'Berhasil membuat ' . $type . ' ' . $name);
    }

    /**
     * Buat file migrasi.
     *
     * @param mixed $name
     * @return void
     */
    private function createMigrasi(mixed $name): void
    {
        $data = $this->loadTemplate($name, 1);
        $data = substr_count($name, '_') >= 1 ? $data[1] : $data[0];
        $data = str_replace('NAME', explode('_', $name)[count(explode('_', $name)) - 1], $data);
        $this->saveTemplate($name, $data, 1);
    }

    /**
     * Buat file middleware.
     *
     * @param mixed $name
     * @return void
     */
    private function createMiddleware(mixed $name): void
    {
        $data = $this->loadTemplate($name, 2);
        $data = str_replace('NAME', $name, $data);
        $this->saveTemplate($name, $data, 2);
    }

    /**
     * Buat file controller.
     *
     * @param mixed $name
     * @return void
     */
    private function createController(mixed $name): void
    {
        $data = $this->loadTemplate($name, 3);
        $data = str_replace('NAME', $name, $data);
        $this->saveTemplate($name, $data, 3);
    }

    /**
     * Buat file model.
     *
     * @param mixed $name
     * @return void
     */
    private function createModel(mixed $name): void
    {
        $data = $this->loadTemplate($name, 4);
        $data = str_replace('NAME', $name, $data);
        $data = str_replace('NAMe', strtolower($name), $data);
        $this->saveTemplate($name, $data, 4);
    }

    /**
     * Buat file mail.
     *
     * @param mixed $name
     * @return void
     */
    private function createMail(mixed $name): void
    {
        $this->exception('Butuh Nama file !', !$name);
        $folder =  basepath() . '/resources/views/email/';
        if (!is_dir($folder)) {
            mkdir($folder, 7777, true);
        }

        $result = copy(__DIR__ . '/../../helpers/templates/templateMail.php', $folder . $name . '.php');
        $this->exception('Gagal membuat email', !$result, 'Berhasil membuat email ' . $name);
    }

    /**
     * Create key to env file.
     *
     * @return void
     */
    private function createKey(): void
    {
        $env = basepath() . '/.env';
        if (!file_exists($env)) {
            $this->exception('env tidak ada !');
        }

        $lines = file($env, FILE_IGNORE_NEW_LINES);
        foreach ($lines as $id => $line) {
            if (str_contains($line, 'APP_KEY=')) {
                $lines[$id] = 'APP_KEY=' . Hash::rand(10) . ':' . Hash::rand(10);
                break;
            }
        }

        $myfile = fopen($env, 'w');
        fwrite($myfile, join("\n", $lines));
        fclose($myfile);

        print("\nAplikasi aman !" . $this->createColor('green', ' berhasil ') . $this->executeTime());
    }

    /**
     * Create cache route file.
     *
     * @return void
     */
    private function createCache(): void
    {
        Route::setRouteFromFile();
        $route = Route::router()->routes();
        $data = '<?php return ' . var_export($route, true) . ';';

        $folder =  basepath() . '/app/cache/';
        if (!is_dir($folder)) {
            mkdir($folder, 7777, true);
        }

        file_put_contents($folder . 'routes.php', $data);

        print("\nCache route siap !" . $this->createColor('green', ' berhasil ') . $this->executeTime());
    }

    /**
     * Delete cache route file.
     *
     * @return void
     */
    private function deleteCache(): void
    {
        $status = @unlink(basepath() . '/app/cache/routes.php');
        if ($status) {
            print("\nCache dihapus !" . $this->createColor('green', ' berhasil ') . $this->executeTime());
        } else {
            $this->exception('file cache tidak ada !');
        }
    }

    /**
     * Tampilkan list menu yang ada.
     *
     * @return void
     */
    private function listMenu(): void
    {
        $menus = [
            [
                'command' => 'coba',
                'description' => 'Jalankan php dengan virtual server'
            ],
            [
                'command' => 'key',
                'description' => 'Amankan aplikasi ini dengan kunci random'
            ],
            [
                'command' => 'cache',
                'description' => 'Buat route cache agar lebih cepat'
            ],
            [
                'command' => 'cache:delete',
                'description' => 'Hapus file cache route tersebut'
            ],
            [
                'command' => 'migrasi',
                'description' => 'Bikin tabel didatabase kamu [--gen]'
            ],
            [
                'command' => 'migrasi:kembali',
                'description' => 'Kembalikan seperti awal databasenya'
            ],
            [
                'command' => 'migrasi:segar',
                'description' => 'Kembalikan seperti awal dan isi ulang [--gen]'
            ],
            [
                'command' => 'generator',
                'description' => 'Isi nilai ke databasenya'
            ],
            [
                'command' => 'bikin:migrasi',
                'description' => 'Bikin file migrasi [nama file]'
            ],
            [
                'command' => 'bikin:middleware',
                'description' => 'Bikin file middleware [nama file]'
            ],
            [
                'command' => 'bikin:controller',
                'description' => 'Bikin file controller [nama file]'
            ],
            [
                'command' => 'bikin:model',
                'description' => 'Bikin file model [nama file]'
            ],
            [
                'command' => 'bikin:email',
                'description' => 'Bikin file email [nama file]'
            ],
        ];

        print("Penggunaan:\n perintah [options]\n\n");
        $mask = $this->createColor('cyan', "%-20s") . " %-30s\n";

        foreach ($menus as $value) {
            printf($mask, $value['command'], $value['description']);
        }
    }

    /**
     * Jalankan console.
     *
     * @return int
     */
    public function run(): int
    {
        switch ($this->command) {
            case 'coba':
                $location = ($this->options) ? $this->options : 'localhost:8000';
                shell_exec('php -S ' .  $location .  ' -t public');
                break;
            case 'key':
                $this->createKey();
                break;
            case 'cache':
                $this->createCache();
                break;
            case 'cache:delete':
                $this->deleteCache();
                break;
            case 'migrasi':
                $this->migrasi(true);
                if ($this->options == '--gen') {
                    $this->generator();
                }
                break;
            case 'generator':
                $this->generator();
                break;
            case 'migrasi:kembali':
                $this->migrasi(false);
                break;
            case 'migrasi:segar':
                $this->migrasi(false);
                $this->migrasi(true);
                if ($this->options == '--gen') {
                    $this->generator();
                }
                break;
            case 'bikin:migrasi':
                $this->createMigrasi($this->options);
                break;
            case 'bikin:middleware':
                $this->createMiddleware($this->options);
                break;
            case 'bikin:controller':
                $this->createController($this->options);
                break;
            case 'bikin:model':
                $this->createModel($this->options);
                break;
            case 'bikin:email':
                $this->createMail($this->options);
                break;
            default:
                $this->listMenu();
                break;
        }

        return 0;
    }
}
