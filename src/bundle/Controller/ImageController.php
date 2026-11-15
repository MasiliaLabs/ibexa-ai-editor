<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaAiEditor\Controller;

use Ibexa\Contracts\AdminUi\Controller\Controller;
use Ibexa\Core\MVC\Symfony\Security\Authorization\Attribute;
use Masilia\Contracts\IbexaAi\Http\CsrfProtectedController;
use Masilia\IbexaAiEditor\Ibexa\ContentContextReader;
use Masilia\Contracts\IbexaAi\Api\ApiError;
use Masilia\IbexaAiEditor\Suggestion\ImagePrompt;
use Masilia\IbexaAiEditor\Suggestion\SiblingField;
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

        // Nothing typed is fine when the item says what to draw (its title, its summary); without either there is nothing to go on.
        $prompt = trim((string) ($payload['prompt'] ?? ''));
        if ($prompt === '' && (int) ($payload['contentId'] ?? 0) <= 0 && trim((string) ($payload['contentTitle'] ?? '')) === '') {
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
     * The brief for the image model: what the editor typed (or, when nothing was typed, the item itself) as the subject, with
     * the title, the type and a few lines of what the item says. See ImagePrompt.
     *
     * @param array<string, mixed> $payload
     */
    private function enrich(array $payload, string $prompt): string
    {
        $contentId = (int) ($payload['contentId'] ?? 0);
        $content = $contentId > 0 ? $this->contents->load($contentId, 'for image generation') : null;
        if ($content === null) {
            // A new content has no stored fields yet: what the form sent is all there is.
            $siblings = [];
            foreach ((array) ($payload['siblingFields'] ?? []) as $field) {
                if (is_array($field) && is_string($field['label'] ?? null) && is_string($field['value'] ?? null)) {
                    $siblings[] = new SiblingField($field['label'], $field['value']);
                }
            }

            return ImagePrompt::build($prompt, (string) ($payload['contentType'] ?? ''), (string) ($payload['contentTitle'] ?? ''), $siblings);
        }

        $language = $this->contents->resolveLanguage($content, (string) ($payload['language'] ?? '')) ?? (string) ($payload['language'] ?? '');
        $context = $this->contents->context($content, (string) ($payload['fieldName'] ?? ''), $language);

        return ImagePrompt::build($prompt, $context->contentTypeName, $context->title, $context->siblingFields);
    }
}
