<?php

use App\Kernel;
use Core\Facades\App;
use Core\Auth\AuthManager;
use Core\View\Render;
use Core\Http\Request;
use Core\Http\Respond;
use Core\Routing\Route;
use Core\Http\Session;
use Core\View\View;

if (!function_exists('context')) {
    /**
     * Simple context dengan stdClass
     * 
     * @return stdClass
     */
    function context(): stdClass
    {
        return App::get()->singleton(stdClass::class);
    }
}

if (!function_exists('session')) {
    /**
     * Helper method untuk membuat objek session.
     * 
     * @return Session
     */
    function session(): Session
    {
        return App::get()->singleton(Session::class);
    }
}

if (!function_exists('respond')) {
    /**
     * Helper method untuk membuat objek respond.
     * 
     * @return Respond
     */
    function respond(): Respond
    {
        return App::get()->singleton(Respond::class);
    }
}

if (!function_exists('auth')) {
    /**
     * Helper method untuk membuat objek AuthManager.
     * 
     * @return AuthManager
     */
    function auth(): AuthManager
    {
        return App::get()->singleton(AuthManager::class);
    }
}

if (!function_exists('render')) {
    /**
     * Baca dari html serta masih bentuk object.
     * 
     * @param string $path
     * @param array $data
     * @return Render
     */
    function render(string $path, array $data = []): Render
    {
        $template = new Render($path);
        $template->setData($data);
        $template->show();

        return $template;
    }
}

if (!function_exists('clear_ob')) {
    /**
     * Hapus cache ob.
     * 
     * @return void
     */
    function clear_ob(): void
    {
        while (ob_get_level() > 0) {
            @ob_end_clean();
        }
    }
}

if (!function_exists('json')) {
    /**
     * Ubah ke json.
     *
     * @param mixed $data
     * @param int $statusCode
     * @return string|bool
     */
    function json(mixed $data, int $statusCode = 200): string|bool
    {
        http_response_code($statusCode);
        header('Content-Type: application/json', true, $statusCode);
        return json_encode($data, 0, 1024);
    }
}

if (!function_exists('e')) {
    /**
     * Tampikan hasil secara aman.
     * 
     * @param mixed $var
     * @return string|null
     */
    function e(mixed $var): string|null
    {
        if (is_null($var)) {
            return null;
        }

        return htmlspecialchars(strval($var));
    }
}

if (!function_exists('basepath')) {
    /**
     * Lokasi dari aplikasi.
     * 
     * @return string
     */
    function basepath(): string
    {
        return App::get()->singleton(Kernel::class)->getPath();
    }
}

if (!function_exists('trace')) {
    /**
     * Lacak erornya.
     * 
     * @param mixed $error
     * @return void
     */
    function trace(mixed $error): void
    {
        @clear_ob();
        http_response_code(500);
        header('Content-Type: text/html');
        header('HTTP/1.1 500 Internal Server Error', true, 500);
        $path = str_replace(basepath(), '', __DIR__);
        echo render($path . '/errors/trace', ['error' => $error]);
        exit(0);
    }
}

if (!function_exists('dd')) {
    /**
     * Tampikan hasil debugging.
     * 
     * @param mixed $param
     * @return void
     */
    function dd(mixed ...$param): void
    {
        @clear_ob();
        http_response_code(500);
        header('Content-Type: text/html');
        header('HTTP/1.1 500 Internal Server Error', true, 500);
        $path = str_replace(basepath(), '', __DIR__);
        echo render($path . '/errors/dd', ['param' => $param]);
        exit(0);
    }
}

if (!function_exists('abort')) {
    /**
     * Tampikan hasil error 403.
     * 
     * @return void
     */
    function abort(): void
    {
        @clear_ob();
        http_response_code(403);
        header('Content-Type: text/html');
        header('HTTP/1.1 403 Forbidden', true, 403);
        $path = str_replace(basepath(), '', __DIR__);
        respond()->send(render($path . '/errors/error', ['pesan' => 'Forbidden 403']));
        exit(0);
    }
}

if (!function_exists('notFound')) {
    /**
     * Tampikan hasil error 404.
     * 
     * @return void
     */
    function notFound(): void
    {
        @clear_ob();
        http_response_code(404);
        header('Content-Type: text/html');
        header('HTTP/1.1 404 Not Found', true, 404);
        $path = str_replace(basepath(), '', __DIR__);
        respond()->send(render($path . '/errors/error', ['pesan' => 'Not Found 404']));
        exit(0);
    }
}

if (!function_exists('notAllowed')) {
    /**
     * Tampikan hasil error 405.
     * 
     * @return void
     */
    function notAllowed(): void
    {
        @clear_ob();
        http_response_code(405);
        header('Content-Type: text/html');
        header('HTTP/1.1 405 Method Not Allowed', true, 405);
        $path = str_replace(basepath(), '', __DIR__);
        respond()->send(render($path . '/errors/error', ['pesan' => 'Method Not Allowed 405']));
        exit(0);
    }
}

if (!function_exists('pageExpired')) {
    /**
     * Tampikan hasil error 400.
     * 
     * @return void
     */
    function pageExpired(): void
    {
        @clear_ob();
        http_response_code(400);
        header('Content-Type: text/html');
        header('HTTP/1.1 400 Bad Request', true, 400);
        $path = str_replace(basepath(), '', __DIR__);
        respond()->send(render($path . '/errors/error', ['pesan' => 'Page Expired !']));
        exit(0);
    }
}

if (!function_exists('unavailable')) {
    /**
     * Tampikan hasil error 503.
     * 
     * @return void
     */
    function unavailable(): void
    {
        @clear_ob();
        http_response_code(503);
        header('Content-Type: text/html');
        header('HTTP/1.1 503 Service Unavailable', true, 503);
        $path = str_replace(basepath(), '', __DIR__);
        respond()->send(render($path . '/errors/error', ['pesan' => 'Service Unavailable !']));
        exit(0);
    }
}

if (!function_exists('csrf_token')) {
    /**
     * Ambil csrf token dari session.
     * 
     * @return string
     */
    function csrf_token(): string
    {
        return session()->get('_token') ?? '';
    }
}

if (!function_exists('csrf')) {
    /**
     * Jadikan html form input.
     * 
     * @return string
     */
    function csrf(): string
    {
        return '<input type="hidden" name="_token" value="' . csrf_token() . '">' . PHP_EOL;
    }
}

if (!function_exists('method')) {
    /**
     * Method untuk html.
     * 
     * @return string
     */
    function method(string $type): string
    {
        return '<input type="hidden" name="_method" value="' . strtoupper($type) . '">' . PHP_EOL;
    }
}

if (!function_exists('flash')) {
    /**
     * Ambil pesan dari session.
     * 
     * @param string $key
     * @return mixed
     */
    function flash(string $key): mixed
    {
        $result = session()->get($key);
        session()->unset($key);
        return $result;
    }
}

if (!function_exists('env')) {
    /**
     * Dapatkan nilai dari env.
     * 
     * @param string $key
     * @param mixed $optional
     * @return mixed
     */
    function env(string $key, mixed $optional = null): mixed
    {
        $res = $_ENV[$key] ?? $optional;

        if ($res === 'null') {
            return $optional;
        }

        return $res;
    }
}

if (!function_exists('baseurl')) {
    /**
     * URL dari aplikasi.
     * 
     * @return string
     */
    function baseurl(): string
    {
        return env('_BASEURL');
    }
}

if (!function_exists('https')) {
    /**
     * Apakah https?.
     * 
     * @return bool
     */
    function https(): bool
    {
        return env('_HTTPS', false);
    }
}

if (!function_exists('debug')) {
    /**
     * Apakah debug?.
     * 
     * @return bool
     */
    function debug(): bool
    {
        return env('_DEBUG', true);
    }
}

if (!function_exists('asset')) {
    /**
     * Gabungkan dengan baseurl.
     * 
     * @param string $param
     * @return string
     */
    function asset(string $param): string
    {
        if (substr($param, 0, 1) != '/') {
            $param = '/' . $param;
        }

        return baseurl() . $param;
    }
}

if (!function_exists('getPathFromRoute')) {
    /**
     * Ambil url dalam route dengan nama.
     *
     * @param string $name
     * @return string
     * 
     * @throws Exception
     */
    function getPathFromRoute(string $name): string
    {
        foreach (Route::router()->routes() as $route) {
            if ($route['name'] == $name) {
                return $route['path'];
            }
        }

        throw new Exception('Route "' . $name . '" tidak ditemukan');
    }
}

if (!function_exists('route')) {
    /**
     * Dapatkan url dari route name dan masukan value.
     * 
     * @param string $param
     * @param mixed $keys
     * @return string
     * 
     * @throws Exception
     */
    function route(string $param, mixed ...$keys): string
    {
        $regex = '([\w-]*)';
        $param = getPathFromRoute($param);
        $lenregex = strlen($regex);

        foreach ($keys as $key) {
            $pos = strpos($param, $regex);
            $param = ($pos !== false) ? substr_replace($param, strval($key), $pos, $lenregex) : $param;
        }

        if (str_contains($param, $regex)) {
            throw new Exception('Key kurang atau tidak ada di suatu fungsi route');
        }

        return asset($param);
    }
}

if (!function_exists('old')) {
    /**
     * Dapatkan nilai yang lama dari sebuah request.
     * 
     * @param string $param
     * @return string|null
     */
    function old(string $param): string|null
    {
        $old = session()->get('old');
        return e(@$old[$param]);
    }
}

if (!function_exists('error')) {
    /**
     * Dapatkan pesan error dari request yang salah.
     * 
     * @param string|null $key
     * @param mixed $optional
     * @return mixed
     */
    function error(string|null $key = null, mixed $optional = null): mixed
    {
        $error = session()->get('error');

        if ($key === null) {
            return $error;
        }

        $result = @$error[$key] ?? null;

        if ($result && $optional) {
            return $optional;
        }

        return $result;
    }
}

if (!function_exists('routeIs')) {
    /**
     * Cek apakah routenya sudah sesuai.
     * 
     * @param string $param
     * @param mixed $optional
     * @param bool $notcontains
     * @return mixed
     */
    function routeIs(string $param, mixed $optional = null, bool $notcontains = false): mixed
    {
        $now = App::get()->singleton(Request::class)->server('REQUEST_URI');
        $route = $notcontains ? $now === $param : str_contains($now, $param);

        if ($route && $optional) {
            return $optional;
        }

        return $route;
    }
}

if (!function_exists('now')) {
    /**
     * Dapatkan waktu sekarang Y-m-d H:i:s.
     * 
     * @param string $format
     * @return string
     */
    function now(string $format = 'Y-m-d H:i:s'): string
    {
        return (new DateTime())->format($format);
    }
}

if (!function_exists('parents')) {
    /**
     * Set parent html.
     * 
     * @param string $name
     * @param array $variables
     * @return void
     */
    function parents(string $name, array $variables = []): void
    {
        App::get()->singleton(View::class)->parents($name, $variables);
    }
}

if (!function_exists('section')) {
    /**
     * Bagian awal dari html.
     * 
     * @param string $name
     * @return void
     */
    function section(string $name): void
    {
        App::get()->singleton(View::class)->section($name);
    }
}

if (!function_exists('content')) {
    /**
     * Tampilkan bagian dari html.
     * 
     * @param string $name
     * @return string|null
     */
    function content(string $name): string|null
    {
        return App::get()->singleton(View::class)->content($name);
    }
}

if (!function_exists('endsection')) {
    /**
     * Bagian akhir dari html.
     * 
     * @return void
     */
    function endsection(): void
    {
        App::get()->singleton(View::class)->endsection();
    }
}

if (!function_exists('including')) {
    /**
     * Masukan html opsional.
     * 
     * @param string $name
     * @return Render
     */
    function including(string $name): Render
    {
        return App::get()->singleton(View::class)->including($name);
    }
}

if (!function_exists('formatBytes')) {
    /**
     * Dapatkan format ukuran bytes yang mudah dibaca.
     * 
     * @param int $size
     * @param int $precision
     * @return string
     */
    function formatBytes(int $size, int $precision = 2): string
    {
        $base = log($size, 1024);
        $suffixes = ['Byte', 'Kb', 'Mb', 'Gb', 'Tb'];

        return strval(round(pow(1024, $base - floor($base)), $precision)) . $suffixes[floor($base)];
    }
}

if (!function_exists('diffTime')) {
    /**
     * Dapatkan selisih waktu dalam ms.
     * 
     * @param float $start
     * @param float $end
     * @return float
     */
    function diffTime(float $start, float $end): float
    {
        return round(($end - $start) * 1000, 2);
    }
}

if (!function_exists('getPageTime')) {
    /**
     * Dapatkan waktu yang dibutuhkan untuk merender halaman dalam (ms).
     * 
     * @return float
     */
    function getPageTime(): float
    {
        return diffTime(constant('KAMU_START'), microtime(true));
    }
}
