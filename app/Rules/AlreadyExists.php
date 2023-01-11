<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;
use Illuminate\Support\Facades\DB;

class AlreadyExists implements Rule
{
    /**
     * Rule options table
     *
     * @var string
     */
    protected string $table;

    /**
     * Rule options ID
     *
     * @var int|null
     */
    protected ?int $id = null;

    /**
     * Message value
     *
     * @var mixed
     */
    protected $value;

    /**
     * Create a new rule instance.
     *
     * @return void
     */
    public function __construct(string $table, ?int $id = null)
    {
        $this->table = $table;
        $this->id = $id;
    }

    /**
     * Determine if the validation rule passes.
     *
     * @param  string  $attribute
     * @param  mixed  $value
     * @return bool
     */
    public function passes($attribute, $value)
    {
        $this->value = $value;
        $query = DB::table($this->table);
        if (!is_null($this->id)) {
            $query = $query->where('id', '!=', $this->id);
        }
        return $query->where($attribute, $value)->count() < 1;
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message()
    {
        return preg_replace_array('/:[a-z]+/', [$this->table, $this->value], 'The :table table already has record \":record\"');
    }
}
