<?php

namespace Tests\App;

use Illuminate\Database\Eloquent\Model;

class BasicModel extends Model
{
    public const TEST = 2;

    public int $fill = 42;

    public $fill_untyped = 42;

    public const TEST_ARRAY_NUMBERS = [83483];

    public array $fill_numbers = [42];

    public $fill_untyped_numbers = [42];

    public const TEST_STRING = 'hello there';

    public string $fill_string = 'hello world';

    public $fill_untyped_string = 'hello world';

    public const TEST_ARRAY_STRINGS = ['hell othere wat'];

    public array $fill_strings = ['other test'];

    public $fill_untyped_strings = ['yet another'];
}
