<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaAiEditor\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

final class MasiliaIbexaAiEditorExtension extends Extension
{
    public function getAlias(): string
    {
        return 'masilia_ibexa_ai_editor';
    }

    public function load(array $configs, ContainerBuilder $container): void
    {
        (new YamlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config')))->load('services.yaml');
    }
}
