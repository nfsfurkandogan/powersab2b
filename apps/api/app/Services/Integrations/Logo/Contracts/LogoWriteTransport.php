<?php

namespace App\Services\Integrations\Logo\Contracts;

interface LogoWriteTransport
{
    /**
     * @param  array<string, mixed>  $envelope
     */
    public function publish(array $envelope): void;
}
