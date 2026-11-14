<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaAiEditor\Controller;

use Ibexa\Contracts\AdminUi\Controller\Controller;
use Ibexa\Core\MVC\Symfony\Security\Authorization\Attribute;
use Masilia\Contracts\IbexaAi\Http\CsrfProtectedController;
use Masilia\IbexaAiEditor\Ibexa\ContentContextReader;
use Masilia\Contracts\IbexaAi\Api\ApiError;
use Masilia\IbexaAiEditor\Suggestion\PromptText;
use Masilia\Contracts\IbexaAi\Image\ImageGenerator;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Throwable;

/**
 * Image generation for the image field of the content being edited.
 *
 * The prompt is enriched with the content type, title, field description and sibling fields,
 * so the picture matches the item it illustrates.
 */
final class ImageController extends Controller implements CsrfProtectedController
{
    private const GENERIC_ERROR = 'The AI image generation service is currently unavailable. Please try again later or contact an administrator.';

    public function __construct(
        private readonly ImageGenerator $images,
        private readonly ContentContextReader $contents,
        private readonly LoggerInterface $aiLogger,
    ) {
    }

    /** What the editing form shows: the buttons of the form are for who may edit contents. */
    public function performAccessCheck(): void
    {
        parent::performAccessCheck();
        $this->denyAccessUnlessGranted(new Attribute('content', 'edit'));
    }


    #[Route('/api/ai/generate-image', name: 'masilia.ibexa_ai.generate_image', methods: ['POST'], options: ['expose' => true])]
    public function generate(Request $request): JsonResponse
    {
        $payload = $request->getPayload()->all();

        $prompt = trim((string) ($payload['prompt'] ?? ''));
        if ($prompt === '') {
            return new JsonResponse(ApiError::validation('Missing required field: prompt')->toArray(), Response::HTTP_BAD_REQUEST);
        }

        try {
            $size = isset($payload['size']) && is_string($payload['size']) ? $payload['size'] : null;
            $quality = isset($payload['quality']) && is_string($payload['quality']) ? $payload['quality'] : null;

            $result = $this->images->generate($this->enrich($payload, $prompt), $size, $quality);

            return new JsonResponse($result->toArray());
        } catch (Throwable $e) {
            $this->aiLogger->error('[AI] Image generation failed: {message}', ['message' => $e->getMessage(), 'exception' => $e]);

            return new JsonResponse(ApiError::serviceUnavailable(self::GENERIC_ERROR)->toArray(), Response::HTTP_SERVICE_UNAVAILABLE);
        }
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function enrich(array $payload, string $prompt): string
    {
        $contentId = (int) ($payload['contentId'] ?? 0);
        if ($contentId <= 0) {
            return $prompt;
        }

        $content = $this->contents->load($contentId, 'for image generation');
        if ($content === null) {
            return $prompt;
        }

        $language = $this->contents->resolveLanguage($content, (string) ($payload['language'] ?? '')) ?? (string) ($payload['language'] ?? '');
        $context = $this->contents->context($content, (string) ($payload['fieldName'] ?? ''), $language);

        $lines = [];
        if ($context->contentTypeName !== '') {
            $lines[] = sprintf('Content type: "%s".', PromptText::scrub($context->contentTypeName));
        }
        if ($context->title !== '') {
            $lines[] = sprintf('Content title: "%s".', PromptText::scrub($context->title));
        }
        if ($context->siblingFields !== []) {
            $lines[] = 'Existing field context:';
            foreach ($context->siblingFields as $sibling) {
                $lines[] = sprintf('  - %s: "%s"', PromptText::scrub($sibling->label), PromptText::scrub($sibling->value));
            }
        }

        return $lines === [] ? $prompt : implode("\n", $lines) . "\n\n" . $prompt;
    }
}
