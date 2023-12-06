<?php

namespace Core\Routing;

use Core\Facades\App;
use Core\Http\Request;
use Core\Http\Respond;
use Core\Valid\Validator;
use Core\View\View;
use ErrorException;

/**
 * Base controller untuk mempermudah memanggil fungsi.
 *
 * @class Controller
 * @package \Core\Routing
 */
abstract class Controller
{
    /**
     * Pastikan tidak ada error.
     *
     * @return void
     *
     * @throws ErrorException
     */
    private function ensureNoError()
    {
        $error = error_get_last();
        if ($error !== null) {
            error_clear_last();
            throw new ErrorException(
                $error['message'],
                0,
                $error['type'],
                $error['file'],
                $error['line']
            );
        }
    }

    /**
     * View template html.
     *
     * @param string $path
     * @param array $data
     * @return View
     */
    protected function view(string $path, array $data = []): View
    {
        $this->ensureNoError();

        $view = App::get()->singleton(View::class);
        $view->variables($data);
        $view->show($path);

        return $view;
    }

    /**
     * Alihkan ke url.
     *
     * @param string $url
     * @return Respond
     */
    protected function redirect(string $url): Respond
    {
        $this->ensureNoError();
        return App::get()->singleton(Respond::class)->to($url);
    }

    /**
     * Kembali seperti semula.
     *
     * @return Respond
     */
    protected function back(): Respond
    {
        $this->ensureNoError();
        return App::get()->singleton(Respond::class)->back();
    }

    /**
     * Buat validasinya.
     *
     * @param Request $request
     * @param array $rules
     * @return Validator
     */
    protected function validate(Request $request, array $rules): Validator
    {
        return Validator::make($request->all(), $rules);
    }
}
