<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaAiEditor\Controller;

use Locale;
use Ibexa\Contracts\AdminUi\Controller\Controller;
use Ibexa\Core\MVC\Symfony\Security\Authorization\Attribute;
use Masilia\Contracts\IbexaAi\Http\CsrfProtectedController;
use Ibexa\Core\MVC\Symfony\SiteAccess\SiteAccessServiceInterface;
use Masilia\IbexaAiEditor\Ibexa\ContentContextReader;
use Masilia\IbexaAiEditor\Ibexa\SuggestionRequestFactory;
use Masilia\IbexaAiEditor\Llm\TextGeneratorFactory;
use Masilia\Contracts\IbexaAi\Api\ApiError;
use Masilia\IbexaAiEditor\Api\SseEncoder;
use Masilia\Contracts\IbexaAi\Provider\ProviderConfigurationException;
use Masilia\IbexaAiEditor\Suggestion\InvalidModelOutputException;
use Masilia\IbexaAiEditor\Suggestion\InvalidSuggestionRequestException;
use Masilia\IbexaAiEditor\Suggestion\SuggestionPayload;
use Masilia\IbexaAiEditor\Suggestion\SuggestionService;
use Masilia\IbexaAiEditor\Suggestion\SupportedField;
use Masilia\IbexaAiEditor\Suggestion\UnsupportedFieldException;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;
use Throwable;

/**
 * Field suggestion endpoints for the admin content edit form.
 *
 * Request and response shapes match the current admin front, so it keeps
 * working while the Ibexa 5 front is being updated.
 */
final class SuggestionController extends Controller implements CsrfProtectedController
{
    /** Shown to the editor for any backend failure; the detail only goes to the log. */
    private const GENERIC_SERVICE_ERROR = 'The AI service is currently unavailable. Please try again later or contact an administrator.';

    private const GENERIC_OUTPUT_ERROR = 'The AI model returned an unusable answer. Please try again.';

    public function __construct(
        private readonly SiteAccessServiceInterface $siteAccess,
        private readonly ContentContextReader $contents,
        private readonly SuggestionRequestFactory $requests,
        private readonly TextGeneratorFactory $generators,
        private readonly LoggerInterface $aiLogger,
        private readonly SseEncoder $sse = new SseEncoder(),
    ) {
    }

    /** What the editing form shows: the buttons of the form are for who may edit contents. */
    public function performAccessCheck(): void
    {
        parent::performAccessCheck();
        $this->denyAccessUnlessGranted(new Attribute('content', 'edit'));
    }


    #[Route('/api/ai/field-types', name: 'masilia.ibexa_ai.field_types', methods: ['GET'], options: ['expose' => true])]
    public function fieldTypes(): JsonResponse
    {
        return new JsonResponse(['fieldTypes' => [
            'ibexa-field-edit--ibexa_string' => SupportedField::String->value,
            'ibexa-field-edit--ibexa_text' => SupportedField::Text->value,
            'ibexa-field-edit--ibexa_richtext' => SupportedField::RichText->value,
            'ibexa-field-edit--ibexa_image' => SupportedField::Image->value,
            'ibexa-field-edit--ibexa_matrix' => SupportedField::Matrix->value,
            'ibexa-field-edit--novaseometas' => SupportedField::SeoMetas->value,
        ]]);
    }

    #[Route('/api/ai/languages/{contentId}', name: 'masilia.ibexa_ai.languages', requirements: ['contentId' => '\d+'], methods: ['GET'], options: ['expose' => true])]
    public function languages(int $contentId): JsonResponse
    {
        $content = $this->contents->load($contentId, 'for language list');
        if ($content === null) {
            return new JsonResponse(['languages' => []]);
        }

        $defaultLocale = Locale::getDefault();
        $languages = array_map(
            static fn (string $code): array => [
                'code' => $code,
                'name' => Locale::getDisplayName($code, $defaultLocale) ?: $code,
            ],
            $this->contents->languageCodes($content),
        );

        return new JsonResponse(['languages' => $languages]);
    }

    #[Route('/api/ai/suggest', name: 'masilia.ibexa_ai.suggest', methods: ['POST'], options: ['expose' => true])]
    public function suggest(Request $request): JsonResponse
    {
        try {
            $payload = SuggestionPayload::fromArray($this->decode($request));
            $suggestionRequest = $this->requests->build($payload);
            $suggestion = (new SuggestionService($this->generators->create($this->siteAccess->getCurrent()->name)))->suggest($suggestionRequest);
        } catch (Throwable $e) {
            [$error, $status] = $this->errorFor($e);

            return new JsonResponse($error->toArray(), $status);
        }

        return new JsonResponse([
            'suggestion' => $suggestion->text,
            'format' => $suggestion->format->value,
        ]);
    }

    #[Route('/api/ai/suggest/stream', name: 'masilia.ibexa_ai.suggest_stream', methods: ['POST'], options: ['expose' => true])]
    public function suggestStream(Request $request): StreamedResponse
    {
        $headers = ['Content-Type' => 'text/event-stream', 'Cache-Control' => 'no-cache', 'X-Accel-Buffering' => 'no'];

        try {
            $payload = SuggestionPayload::fromArray($this->decode($request));
            $suggestionRequest = $this->requests->build($payload);
            $service = new SuggestionService($this->generators->create($this->siteAccess->getCurrent()->name));
            $format = $suggestionRequest->outputFormat()->value;
        } catch (Throwable $e) {
            [$error, $status] = $this->errorFor($e);

            return $this->streamError($error, $status, $headers);
        }

        return new StreamedResponse(function () use ($service, $suggestionRequest, $format): void {
            try {
                foreach ($service->streamWithActivity($suggestionRequest) as $text) {
                    // Null: the model is reasoning. Nothing to show, but the connection must not go idle.
                    echo $text === null ? $this->sse->keepAlive() : $this->sse->delta($text);
                    flush();
                }
                echo $this->sse->completed($format);
            } catch (Throwable $e) {
                $this->aiLogger->error('[AI] Streaming suggestion failed: {message}', [
                    'message' => $e->getMessage(),
                    'exception' => $e,
                ]);
                echo $this->sse->error(ApiError::serviceUnavailable(self::GENERIC_SERVICE_ERROR));
            }
            flush();
        }, Response::HTTP_OK, $headers);
    }

    /**
     * @return array<string, mixed>
     * @throws InvalidSuggestionRequestException
     */
    private function decode(Request $request): array
    {
        try {
            return $request->getPayload()->all();
        } catch (BadRequestException) {
            throw new InvalidSuggestionRequestException('Invalid JSON payload');
        }
    }

    /**
     * Map a failure to the client envelope and HTTP status. Only validation
     * messages reach the client verbatim; everything else is logged and generic.
     *
     * @return array{0: ApiError, 1: int}
     */
    private function errorFor(Throwable $e): array
    {
        if ($e instanceof UnsupportedFieldException) {
            return [ApiError::unsupportedFieldType($e->fieldType), Response::HTTP_BAD_REQUEST];
        }

        if ($e instanceof InvalidSuggestionRequestException) {
            return [ApiError::validation($e->getMessage()), Response::HTTP_BAD_REQUEST];
        }

        if ($e instanceof InvalidModelOutputException) {
            $this->aiLogger->warning('[AI] Unusable model output: {message}', ['message' => $e->getMessage()]);

            return [ApiError::invalidOutput(self::GENERIC_OUTPUT_ERROR), Response::HTTP_BAD_GATEWAY];
        }

        $this->aiLogger->error('[AI] Suggestion failed: {message}', [
            'message' => $e->getMessage(),
            'exception' => $e,
            'configuration' => $e instanceof ProviderConfigurationException,
        ]);

        return [ApiError::serviceUnavailable(self::GENERIC_SERVICE_ERROR), Response::HTTP_SERVICE_UNAVAILABLE];
    }

    /**
     * @param array<string, string> $headers
     */
    private function streamError(ApiError $error, int $status, array $headers): StreamedResponse
    {
        return new StreamedResponse(function () use ($error): void {
            echo $this->sse->error($error);
            flush();
        }, $status, $headers);
    }
}
