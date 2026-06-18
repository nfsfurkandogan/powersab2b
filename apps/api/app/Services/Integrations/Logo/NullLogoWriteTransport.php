<?php

namespace App\Services\Integrations\Logo;

use App\Services\Integrations\Logo\Contracts\LogoWriteTransport;

class NullLogoWriteTransport implements LogoWriteTransport
{
    public function publish(array $envelope): void
    {
        // SQL bridge mode polls pending endpoints; there is no immediate publish step.
    }
}
