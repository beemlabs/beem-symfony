<?php

declare(strict_types=1);

namespace Beem\SymfonyBundle;

use Beem\Beem;
use Beem\Client;
use Beem\Configuration as SdkConfiguration;

/**
 * Runtime factory for the Beem client.
 *
 * The DSN typically contains an env placeholder ({@code %env(BEEM_DSN)%}),
 * which Symfony only resolves at runtime. Parsing it at container compile
 * time would throw on the placeholder token, so the client is built lazily
 * by the container through this factory instead. As a side effect the global
 * {@see Beem} facade is initialized so subscribers can use the static API.
 */
final class BeemClientFactory
{
    /**
     * @param array<string, mixed> $options
     */
    public static function create(string $dsn, array $options = []): Client
    {
        $client = new Client(new SdkConfiguration($dsn, $options));
        Beem::setClient($client);
        return $client;
    }
}
