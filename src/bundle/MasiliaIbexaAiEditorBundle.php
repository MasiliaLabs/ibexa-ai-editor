<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaAiEditor;

use Symfony\Component\HttpKernel\Bundle\Bundle;

/**
 * AI help in the Ibexa content editing form, on the AI OS (masilia/ibexa-ai): a suggestion for each field and image generation.
 * It talks to the OS through its contracts only (the chat providers of a siteaccess, the image generator, the field texts).
 */
final class MasiliaIbexaAiEditorBundle extends Bundle
{
}
