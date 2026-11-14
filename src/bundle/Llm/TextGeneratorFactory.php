<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaAiEditor\Llm;

use Masilia\Contracts\IbexaAi\Observability\RequestLogger;
use Masilia\Contracts\IbexaAi\Provider\ChatProviders;
use Masilia\Contracts\IbexaAi\Provider\ProviderConfigurationException;
use Masilia\IbexaAiEditor\Llm\TextGenerator;

/**
 * Builds the text generator for the current siteaccess. One instance per request, so the
 * system prompt set on the provider never leaks between requests.
 */
final readonly class TextGeneratorFactory
{
    public function __construct(
        private ChatProviders $chat,
        private RequestLogger $logger,
    ) {
    }

    /**
     * @throws ProviderConfigurationException
     */
    public function create(string $siteaccess): TextGenerator
    {
        $connection = $this->chat->forSiteaccess($siteaccess);

        return new NeuronTextGenerator(
            provider: $connection->provider,
            logger: $this->logger,
            providerIdentifier: $connection->providerIdentifier,
            modelIdentifier: $connection->model,
            siteaccess: $siteaccess,
        );
    }
}
