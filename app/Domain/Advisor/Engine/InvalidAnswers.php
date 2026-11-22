<?php

namespace App\Domain\Advisor\Engine;

use InvalidArgumentException;

/** Lista odpowiedzi nie odtwarza się na bieżących danych (np. po zmianie bazy albo ręcznej edycji). */
final class InvalidAnswers extends InvalidArgumentException {}
