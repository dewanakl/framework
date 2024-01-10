<?php

if (!function_exists('context')) {
    /**
     * Simple context dengan stdClass.
     *
     * @param string|null $key
     * @param mixed $val
     * @return mixed
     */
    function context(string|null $key = null, mixed $val = null): mixed
    {
        $ctx = \Core\Facades\App::get()->singleton(\stdClass::class);
        if (!$key && !$val) {
            return $ctx;
        }

        if (!$val) {
            return $ctx->{$key};
        }

        $ctx->{$key} = $val;
        return $ctx->{$key};
    }
}

if (!function_exists('session')) {
    /**
     * Helper method untuk membuat objek session.
     *
     * @return \Core\Http\Session
     */
    function &session(): \Core\Http\Session
    {
        return \Core\Facades\App::get()->singleton(\Core\Http\Session::class);
    }
}

if (!function_exists('cookie')) {
    /**
     * Helper method untuk membuat objek cookie.
     *
     * @return \Core\Http\Cookie
     */
    function &cookie(): \Core\Http\Cookie
    {
        return \Core\Facades\App::get()->singleton(\Core\Http\Cookie::class);
    }
}

if (!function_exists('respond')) {
    /**
     * Helper method untuk membuat objek respond.
     *
     * @return \Core\Http\Respond
     */
    function &respond(): \Core\Http\Respond
    {
        return \Core\Facades\App::get()->singleton(\Core\Http\Respond::class);
    }
}

if (!function_exists('request')) {
    /**
     * Helper method untuk membuat objek request.
     *
     * @return \Core\Http\Request
     */
    function &request(): \Core\Http\Request
    {
        return \Core\Facades\App::get()->singleton(\Core\Http\Request::class);
    }
}

if (!function_exists('auth')) {
    /**
     * Helper method untuk membuat objek AuthManager.
     *
     * @return \Core\Auth\AuthManager
     */
    function &auth(): \Core\Auth\AuthManager
    {
        return \Core\Facades\App::get()->singleton(\Core\Auth\AuthManager::class);
    }
}

if (!function_exists('translate')) {
    /**
     * Helper method untuk membuat objek trans.
     *
     * @return \Core\Valid\Trans
     */
    function &translate(): \Core\Valid\Trans
    {
        return \Core\Facades\App::get()->singleton(\Core\Valid\Trans::class);
    }
}

if (!function_exists('event')) {
    /**
     * Helper method untuk menjalankan objek event.
     *
     * @return object
     */
    function event(object $event): object
    {
        return \Core\Facades\App::get()->singleton(\Core\Events\Dispatch::class)->dispatch($event);
    }
}

if (!function_exists('render')) {
    /**
     * Baca dari file .kita serta masih bentuk object.
     *
     * @param string $path
     * @param array $data
     * @return \Core\View\Render
     */
    function render(string $path, array $data = []): \Core\View\Render
    {
        $template = new \Core\View\Render($path);
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
            if (!@ob_end_clean()) {
                break;
            }
        }
    }
}

if (!function_exists('json')) {
    /**
     * Wrapper json_encode.
     *
     * @param mixed $data
     * @param int $code
     * @param int $flag
     * @return string
     */
    function json(mixed $data, int $code = 200, int $flag = 0): string
    {
        respond()->setCode($code);
        respond()->getHeader()->set('Content-Type', 'application/json');
        return json_encode($data, JSON_THROW_ON_ERROR | $flag, 1024);
    }
}

if (!function_exists('e')) {
    /**
     * Tampikan hasil secara aman.
     *
     * @param mixed $var
     * @return string|null
     *
     * @throws \Core\View\Exception\CastToStringException
     */
    function e(mixed $var): string|null
    {
        if (is_null($var)) {
            return null;
        }

        try {
            $str = strval($var);
        } catch (Throwable $th) {
            $prev = $th->getTrace()[0];

            throw new \Core\View\Exception\CastToStringException(
                $th->getMessage(),
                0,
                E_ERROR,
                $prev['file'],
                $prev['line']
            );
        }

        $error = error_get_last();
        if ($error !== null) {
            error_clear_last();
            throw new \Core\View\Exception\CastToStringException(
                $error['message'],
                0,
                $error['type'],
                $error['file'],
                $error['line']
            );
        }

        return htmlspecialchars($str);
    }
}

if (!function_exists('base_path')) {
    /**
     * Lokasi dari aplikasi.
     *
     * @param string|null $path
     * @return string
     */
    function base_path(string|null $path = null): string
    {
        $base = \Core\Facades\App::get()->singleton(\Core\Kernel\KernelContract::class)->path();
        if (!$path) {
            return $base;
        }

        return $base . $path;
    }
}

if (!function_exists('helper_path')) {
    /**
     * Lokasi dari helper aplikasi.
     *
     * @param string|null $path
     * @return string
     */
    function helper_path(string|null $path = null): string
    {
        $base = str_replace(base_path(), '', __DIR__);
        if (!$path) {
            return $base;
        }

        return $base . $path;
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
        // In Console Application.
        if (in_array(PHP_SAPI, ['cli', 'phpdbg', 'embed'], true)) {
            var_dump(...$param);
            exit(1);
        }

        // Clean all temp respond.
        respond()->clean();
        header_remove();

        http_response_code(500);
        header('Content-Type: ' . (request()->ajax() ? 'application/json' : 'text/html'));
        header('HTTP/1.1 500 Internal Server Error', true, 500);

        if (request()->ajax()) {
            echo json_encode($param, JSON_THROW_ON_ERROR, 1024);
        } else {
            echo render(helper_path('/errors/dd'), ['param' => $param])->__toString();
        }

        exit(1);
    }
}

if (!function_exists('abort')) {
    /**
     * Tampikan hasil error 403.
     *
     * @return void
     *
     * @throws \Core\Http\Exception\ForbiddenException
     */
    function abort(): void
    {
        throw new \Core\Http\Exception\ForbiddenException();
    }
}

if (!function_exists('not_found')) {
    /**
     * Tampikan hasil error 404.
     *
     * @return void
     *
     * @throws \Core\Http\Exception\NotFoundException
     */
    function not_found(): void
    {
        throw new \Core\Http\Exception\NotFoundException();
    }
}

if (!function_exists('not_allowed')) {
    /**
     * Tampikan hasil error 405.
     *
     * @return void
     *
     * @throws \Core\Http\Exception\NotAllowedException
     */
    function not_allowed(): void
    {
        throw new \Core\Http\Exception\NotAllowedException();
    }
}

if (!function_exists('page_expired')) {
    /**
     * Tampikan hasil page expired.
     *
     * @return void
     *
     * @throws \Core\Http\Exception\ExpiredException
     */
    function page_expired(): void
    {
        throw new \Core\Http\Exception\ExpiredException();
    }
}

if (!function_exists('unavailable')) {
    /**
     * Tampikan hasil error 503.
     *
     * @return \Core\View\Render|string
     */
    function unavailable(): \Core\View\Render|string
    {
        $respond = respond();
        $respond->clean();
        $respond->setCode(\Core\Http\Respond::HTTP_SERVICE_UNAVAILABLE);

        if (request()->ajax()) {
            return json(['Service Unavailable'], \Core\Http\Respond::HTTP_SERVICE_UNAVAILABLE);
        }

        return render(helper_path('/errors/error'), ['pesan' => 'Service Unavailable']);
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
        return session()->get(\Core\Http\Session::TOKEN, '');
    }
}

if (!function_exists('flash')) {
    /**
     * Ambil pesan dari session.
     *
     * @param string $key
     * @param string|int|null $optional
     * @return mixed
     */
    function flash(string $key, string|int|null $optional = null): mixed
    {
        $result = session()->get($key, $optional);
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
        $res = \Core\Support\Env::get($key, $optional);

        if ($res === 'null') {
            return $optional;
        }

        return $res;
    }
}

if (!function_exists('base_url')) {
    /**
     * URL dari aplikasi.
     *
     * @param string|null $url
     * @return string
     */
    function base_url(string|null $url = null): string
    {
        if ($url) {
            return env(\Core\Support\Env::BASEURL) . $url;
        }

        return env(\Core\Support\Env::BASEURL);
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
        return env(\Core\Support\Env::HTTPS, false);
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
        return env(\Core\Support\Env::DEBUG, false);
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

        return base_url($param);
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
     * @throws ErrorException
     */
    function route(string $param, mixed ...$keys): string
    {
        $found = false;
        foreach (\Core\Routing\Route::router()->routes() as $route) {
            if ($route['name'] == $param) {
                $param = preg_replace('/{(\w+)}/', '([\w-]*)', $route['path']);
                $found = true;
                break;
            }
        }

        if (!$found) {
            $error = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2)[0];
            throw new ErrorException('Route "' . $param . '" tidak ditemukan', 0, E_ERROR, $error['file'], $error['line']);
        }

        $regex = '([\w-]*)';
        $lenregex = strlen($regex);

        foreach ($keys as $key) {
            $pos = strpos($param, $regex);
            $param = ($pos !== false) ? substr_replace($param, strval($key), $pos, $lenregex) : $param;
        }

        if (str_contains($param, $regex)) {
            $error = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1)[0];
            throw new ErrorException('Key kurang atau tidak ada di suatu fungsi route', 0, E_ERROR, $error['file'], $error['line']);
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
        $old = session()->get(\Core\Http\Session::OLD);
        if (is_null($old)) {
            return null;
        }

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
        $error = session()->get(\Core\Http\Session::ERROR);

        if ($key === null) {
            return $error;
        }

        $result = isset($error[$key]) ? $error[$key] : null;

        if ($result && $optional) {
            return $optional;
        }

        return $result;
    }
}

if (!function_exists('route_is')) {
    /**
     * Cek apakah routenya sudah sesuai.
     *
     * @param string $param
     * @param mixed $optional
     * @param bool $notcontains
     * @return mixed
     */
    function route_is(string $param, mixed $optional = null, bool $notcontains = false): mixed
    {
        $now = request()->server->get('REQUEST_URI');
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
        return \Core\Support\Time::factory()->format($format);
    }
}

if (!function_exists('format_bytes')) {
    /**
     * Dapatkan format ukuran bytes yang mudah dibaca.
     *
     * @param float $size
     * @param int $precision
     * @return string
     */
    function format_bytes(float $size, int $precision = 2): string
    {
        $base = log($size, 1024);
        $suffixes = ['Byte', 'Kb', 'Mb', 'Gb', 'Tb'];

        return strval(round(pow(1024, $base - floor($base)), $precision)) . $suffixes[intval(floor($base))];
    }
}

if (!function_exists('diff_time')) {
    /**
     * Dapatkan selisih waktu dalam ms.
     *
     * @param float $start
     * @param float $end
     * @return float
     */
    function diff_time(float $start, float $end): float
    {
        return round(($end - $start) * 1000, 2);
    }
}

if (!function_exists('execute_time')) {
    /**
     * Dapatkan waktu yang dibutuhkan untuk merender halaman dalam (ms).
     *
     * @return float
     */
    function execute_time(): float
    {
        return diff_time(request()->server->get('REQUEST_TIME_FLOAT'), microtime(true));
    }
}

if (!function_exists('dispatch')) {
    /**
     * Kirimkan tugas baru pada phproutine.
     *
     * @param \Core\Queue\Job $job
     *
     * @throws Exception
     */
    function dispatch(\Core\Queue\Job $job): void
    {
        $file = base_path(sprintf('/cache/queue/%s.tmp', hrtime(true)));

        $status = @file_put_contents(
            $file,
            serialize($job)
        );

        if ($status === false || !chmod($file, 0777)) {
            throw new Exception('Cant save file in cache queue');
        }
    }
}

if (!function_exists('fake')) {
    /**
     * Bikin sesuatu yang palsu.
     *
     * @param string $locale
     * @return \Faker\Generator
     *
     * @throws Exception
     */
    function fake(string $locale = 'id_ID'): \Faker\Generator
    {
        if (!class_exists(\Faker\Factory::class)) {
            throw new Exception("Class \Faker\Factory Doesn't Exist!");
        }

        if (!\Core\Facades\App::get()->has(\Faker\Factory::class)) {
            \Core\Facades\App::get()->bind(\Faker\Factory::class, function () use ($locale): Faker\Generator {
                return \Faker\Factory::create($locale);
            });
        }

        return \Core\Facades\App::get()->singleton(\Faker\Factory::class);
    }
}
