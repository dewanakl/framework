<?php

namespace Core\Valid;

use Core\Http\Request;

abstract class Form extends Request
{
    /**
     * Validate request.
     *
     * @return Validator
     */
    public function validated(): Validator
    {
        return Validator::make($this->all(), $this->authorize() ? $this->rules() : []);
    }

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules(): array
    {
        return [];
    }
}
