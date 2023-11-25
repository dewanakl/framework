<?php

namespace Core\Support;

use Core\Database\Generator;
use Core\Database\Migration;
use Core\Database\Schema;
use Core\Http\Request;
use Core\Queue\Routine;
use Core\Routing\Route;
use Core\Valid\Hash;
use Core\View\Compiler;
use DirectoryIterator;
use Psy\Configuration;
use Psy\Shell;
use Psy\VersionUpdater\Checker;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Throwable;

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
     * @var string|null $command
     */
    private $options;

    /**
     * Optional args.
     *
     * @var array $args
     */
    private $args;

    /**
     * Waktu yang dibutuhkan.
     *
     * @var float $timenow
     */
    private $timenow;
    /**
     * Buat objek console.
     *
     * @return void
     */
    public function __construct(Request $request)
    {
        $this->timenow = $request->server->get('REQUEST_TIME_FLOAT');
        $argv = $request->server->get('argv');

        array_shift($argv);
        $this->command = $argv[0] ?? null;
        array_shift($argv);
        $this->options = $argv[0] ?? null;
        array_shift($argv);

        $this->args = $argv;
    }

    /**
     * Get version the Framework.
     *
     * @return string|null
     */
    private function getVersion(): string|null
    {
        $composerLock = json_decode(
            strval(file_get_contents(base_path('/composer.lock'))),
            false,
            1024
        );

        foreach ($composerLock->packages as $value) {
            if ($value->name == 'kamu/framework') {
                return $value->version;
            }
        }

        $this->exception('File composer.lock tidak ada !');

        return null;
    }

    public function catchException(Throwable $th): int
    {
        if (in_array('--nooutput', $this->args)) {
            return 1;
        }

        echo $this->createColor('red', get_class($th));
        echo ' - ' . $th->getMessage();

        return 1;
    }

    /**
     * Buat dan tulis file dalam folder.
     *
     * @param string $file
     * @param string $content
     * @return bool
     */
    private function writeFileContent(string $file, string $content): bool
    {
        $arr = explode('/', $file);
        $folder = implode('/', array_splice($arr, 0, -1));

        if (!is_dir($folder)) {
            mkdir($folder, 0777, true);
        }

        $write = file_put_contents($file, $content);
        return $write && chmod($file, 0777);
    }

    /**
     * Buat warna untuk string.
     *
     * @param string $name
     * @param string $value
     * @return string
     */
    public function createColor(string $name, string $value): string
    {
        if (!stream_isatty(STDOUT)) {
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
            exit(1);
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
        $result = strval(diff_time($this->timenow, $now));
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
        $baseFile = base_path('/database/schema/');

        $files = scandir($baseFile, ($up) ? 0 : 1);
        $files = array_diff($files, array('..', '.'));

        foreach ($files as $file) {
            $arg = require $baseFile . $file;
            if (!($arg instanceof Migration)) {
                $this->exception('File ' . $file . ' bukan migrasi !');
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
        $arg = require base_path('/database/generator/generator.php');
        if (!($arg instanceof Generator)) {
            $this->exception('File bukan generator !');
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
            case 4:
                $type = 'templateModel';
                break;
            case 5:
                $type = 'templateJob';
                break;
        }

        return require_once base_path(helper_path('/templates/' . $type . '.php'));
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
            case 4:
                $type = 'app/Models';
                break;
            case 5:
                $type = 'app/Jobs';
                break;
        }

        $result = $this->writeFileContent(base_path('/' . $type . '/' . $optional . $name . '.php'), $data);
        $this->exception('Gagal membuat ' . $type . '/' . $name, !$result, 'Berhasil membuat ' . $type . '/' . $name . '.php');
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
     * Buat file job.
     *
     * @param mixed $name
     * @return void
     */
    private function createJob(mixed $name): void
    {
        $data = $this->loadTemplate($name, 5);
        $data = str_replace('NAME', $name, $data);
        $this->saveTemplate($name, $data, 5);
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

        $folder = base_path('/resources/views/email/');
        if (!is_dir($folder)) {
            mkdir($folder, 0777, true);
        }

        $result = copy(base_path(helper_path('/templates/templateMail.php')), $folder . $name . '.php');
        $this->exception('Gagal membuat email ' . $name, !$result, 'Berhasil membuat email ' . $name);
    }

    /**
     * Create key to env file.
     *
     * @return void
     */
    private function createKey(): void
    {
        $env = base_path('/.env');
        if (!file_exists($env)) {
            $this->exception('.env file tidak ada !');
        }

        $lines = file($env, FILE_IGNORE_NEW_LINES);
        foreach ($lines as $id => $line) {
            if (str_contains($line, 'APP_KEY=')) {
                $lines[$id] = 'APP_KEY=' . base64_encode(openssl_random_pseudo_bytes(32)) . Hash::SPTR . base64_encode(openssl_random_pseudo_bytes(32));
                break;
            }
        }

        file_put_contents($env, join("\n", $lines));
        print("\nAplikasi aman !" . $this->createColor('green', ' berhasil ') . $this->executeTime());
    }

    /**
     * Create cache route file.
     *
     * @return void
     */
    private function createCache(): void
    {
        $env = [];
        if (!is_file(base_path('/.env'))) {
            $this->exception('File env tidak ada !');
        }

        $lines = file(base_path('/.env'), FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $line = trim($line);
            if (strpos($line, '#') === 0) {
                continue;
            }

            list($name, $value) = explode('=', $line, 2);
            $env[trim($name)] = trim($value);
        }

        Route::setRouteFromFile();
        $routes = '<?php return ' . var_export(Route::router()->routes(), true) . ';';
        $envs = '<?php return ' . var_export($env, true) . ';';

        $folder = base_path('/cache');
        $this->writeFileContent($folder . '/routes/routes.php', $routes);
        $this->writeFileContent($folder . '/env/env.php', $envs);

        print("\nCache siap !" . $this->createColor('green', ' berhasil ') . $this->executeTime());
    }

    /**
     * Delete cache route file.
     *
     * @return void
     */
    private function deleteCache(): void
    {
        $routes = @unlink(base_path('/cache/routes/routes.php'));
        $env = @unlink(base_path('/cache/env/env.php'));
        if ($routes && $env) {
            print("\nCache dihapus !" . $this->createColor('green', ' berhasil ') . $this->executeTime());
        } else {
            $this->exception('file cache tidak ada !');
        }
    }

    /**
     * Cache views.
     *
     * @return void
     */
    private function viewCache(): void
    {
        $views = base_path('/resources/views');
        $rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($views));
        $compiler = new Compiler;

        printf("%s\n\n", $this->createColor('yellow', 'Cache View Start'));
        $begin = $this->timenow;

        foreach ($rii as $file) {
            if ($file->isDir()) {
                continue;
            }

            $path = $compiler->compile(ltrim(str_replace(
                '\\',
                '/',
                str_replace(
                    $views,
                    '',
                    str_replace(
                        '.kita.php',
                        '',
                        $file->getPathname()
                    )
                )
            ), '/'))->getPathFileCache();

            printf("%-30s %s\n", $path, $this->executeTime());
        }

        $this->timenow = $begin;
        printf("\n%-10s %s\n", $this->createColor('green', 'Execute success'), $this->executeTime());
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
                'description' => 'Buat cache agar lebih cepat'
            ],
            [
                'command' => 'cache:delete',
                'description' => 'Hapus file cache tersebut'
            ],
            [
                'command' => 'view:cache',
                'description' => 'Buat cache pada view agar jadi cepat'
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
                'command' => 'migrasi:dump',
                'description' => 'Ubah menjadi file .sql pada foler database'
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
            [
                'command' => 'bikin:job',
                'description' => 'Bikin file job [nama file]'
            ],
            [
                'command' => 'play',
                'description' => 'Bermain dengan applikasi'
            ],
        ];

        print("Penggunaan:\n perintah [options]\n\n");
        $mask = $this->createColor('cyan', "%-20s") . " %s\n";

        foreach ($menus as $value) {
            printf($mask, $value['command'], $value['description']);
        }
    }

    /**
     * Run queue loop.
     *
     * @return void
     */
    private function queue(): void
    {
        $files = [];

        // @phpstan-ignore-next-line
        while (true) {
            sleep(intval(explode('=', $this->options ?? '')[1] ?? 3));

            foreach (new DirectoryIterator(base_path('/cache/queue/')) as $item) {
                if ($item->isDot() && !$item->isFile()) {
                    continue;
                }

                if ($item->getFilename() === '.gitignore') {
                    continue;
                }

                $tmp = [];
                foreach ($files as $value) {
                    if ($value == $item->getFilename()) {
                        continue 2;
                    }

                    if (is_file(base_path('/cache/queue/' . $value))) {
                        $tmp[] = $value;
                    }
                }

                $files = $tmp;
                $files[] = $item->getFilename();

                echo $this->createColor('yellow', 'Prepare process') . "\r\n";

                if (Routine::execInBackground($item->getFilename())) {
                    echo $this->createColor('green', 'Run ' . $item->getFilename() . ' in phproutine') . ' ' . $this->executeTime() . "\r\n";
                    continue;
                }

                echo $this->createColor('red', 'Failed run phproutine') . ' ' . $this->executeTime() . "\r\n";
            }

            $tmp = [];
            foreach ($files as $value) {
                if (is_file(base_path('/cache/queue/' . $value))) {
                    $tmp[] = $value;
                }
            }

            $files = $tmp;
        }
    }

    /**
     * Jadikan file .sql
     *
     * @return void
     */
    private function dump(): void
    {
        $baseFile = base_path('/database/schema/');

        $files = scandir($baseFile, 0);
        $files = array_diff($files, array('..', '.'));

        Schema::setDump(true);

        foreach ($files as $file) {
            $arg = require $baseFile . $file;
            if (!($arg instanceof Migration)) {
                $this->exception('File ' . $file . ' bukan migrasi !');
            }

            $arg->up();

            print("\n" . $file . $this->createColor('cyan', ' Dump '));
        }

        file_put_contents(base_path('/database/database.sql'), implode("\n", Schema::getDump()));
        print("\n" . $this->createColor('green', 'DONE ') . $this->executeTime());
    }

    /**
     * Play with this application.
     *
     * @return int
     */
    public function play(): int
    {
        $config = new Configuration();
        $config->setColorMode(Configuration::COLOR_MODE_AUTO);
        $config->setUpdateCheck(Checker::NEVER);
        $config->setInteractiveMode(Configuration::INTERACTIVE_MODE_AUTO);

        $loader = new class($this, new Shell($config))
        {
            /**
             * The shell instance.
             *
             * @var \Psy\Shell
             */
            protected $shell;

            /**
             * The console instance.
             *
             * @var \Core\Support\Console
             */
            protected $console;

            /**
             * All of the discovered classes.
             *
             * @var array
             */
            protected $classes = [];

            /**
             * Create a new shell instance.
             *
             * @param  \Psy\Shell  $shell
             * @return void
             */
            public function __construct(Console $console, Shell $shell)
            {
                $this->shell = $shell;
                $this->console = $console;

                $classes = (array) @require_once base_path('/vendor/composer/autoload_classmap.php');

                foreach ($classes as $class => $path) {
                    $name = basename(str_replace('\\', '/', $class));

                    if (!isset($this->classes[$name])) {
                        $this->classes[$name] = $class;
                    }
                }
            }

            /**
             * Find the closest class by name.
             *
             * @param  string  $class
             * @return void
             */
            public function aliasClass(string $class): void
            {
                if (str_contains($class, '\\')) {
                    return;
                }

                $fullName = $this->classes[$class] ?? false;

                if ($fullName) {
                    $this->shell->writeStdout("\n" . $this->console->createColor('yellow', "[WARN]") . sprintf(" Class \"%s\" alias \"%s\"", $class, $fullName) . "\n");
                    class_alias($fullName, $class);
                }
            }

            /**
             * Register a new alias loader instance.
             *
             * @return self
             */
            public function register(): self
            {
                spl_autoload_register([$this, 'aliasClass']);
                return $this;
            }

            /**
             * Unregister the alias loader instance.
             *
             * @return void
             */
            public function unregister(): void
            {
                spl_autoload_unregister([$this, 'aliasClass']);
            }

            /**
             * Get shell instance.
             *
             * @return Shell
             */
            public function getShell(): Shell
            {
                return $this->shell;
            }
        };

        try {
            return $loader->register()->getShell()->run();
        } finally {
            $loader->unregister();
        }
    }

    /**
     * Jalankan console.
     *
     * @return int
     */
    public function run(): int
    {
        if (!in_array('--nooutput', $this->args)) {
            print($this->createColor('green', sprintf("Kamu PHP Framework %s\n", $this->getVersion())));
            print($this->createColor('yellow', "Saya Console\n\n"));
        }

        switch ($this->command) {
            case 'coba':
                $location = ($this->options) ? $this->options : 'localhost:8000';
                pclose(popen('php -S ' .  $location .  ' -t public', 'r'));
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
            case 'view:cache':
                $this->viewCache();
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
            case 'migrasi:dump':
                $this->dump();
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
            case 'bikin:job':
                $this->createJob($this->options);
                break;
            case 'queue:run':
                $this->queue();
                break;
            case 'queue:sync':
                Routine::sync($this->options);
                break;
            case 'play':
                return $this->play();
            default:
                $this->listMenu();
                break;
        }

        return 0;
    }
}
