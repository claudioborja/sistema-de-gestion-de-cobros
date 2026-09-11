<?php
declare(strict_types=1);
namespace App\Domain;
final class ValidationException extends \InvalidArgumentException
{
    public function __construct(public readonly array $errors) { parent::__construct('Revisa los campos indicados.'); }
}
