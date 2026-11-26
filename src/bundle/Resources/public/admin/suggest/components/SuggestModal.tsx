import React, { useEffect, useRef } from 'react';

import { AltRadiosListField, AssetsProvider, Button, Icon } from '@masilia-admin-kit/ids';
import { iconPath } from '@masilia-admin-kit/icons';
import { FIELD_LABELS, offersImageGeneration } from '../actions';
import { canGenerate, generateBlocker, isBusy } from '../state';
import { useSuggest } from '../useSuggest';
import { ActionBar } from './ActionBar';
import { ImagePanel } from './ImagePanel';
import { Preview } from './Preview';
import { PromptField } from './PromptField';
import { SourceLanguage } from './SourceLanguage';

const FOCUSABLE = 'button:not([disabled]), [href], input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';

const MODES = [
    { id: 'mode-replace', value: 'replace', label: 'Replace the content' },
    { id: 'mode-append', value: 'append', label: 'Add to the content' },
];

/**
 * The AI suggestion modal, opened by the injector for one field of the edit form. See useSuggest for the
 * behaviour and state.ts for the rules.
 */
export function SuggestModal() {
    const suggest = useSuggest();
    const { state, apply, generate, requestClose, cancelClose } = suggest;
    const dialogRef = useRef<HTMLDivElement>(null);
    const promptRef = useRef<HTMLTextAreaElement>(null);

    const busy = isBusy(state);
    const hasText = state.suggestion !== '';
    const hasImage = state.image.image !== null;
    const canApply = hasImage || (hasText && state.status === 'done');
    const open = state.open && state.context !== null;

    // The prompt gets the focus when the modal opens.
    useEffect(() => {
        if (open) {
            const timer = window.setTimeout(() => promptRef.current?.focus(), 50);

            return () => window.clearTimeout(timer);
        }

        return undefined;
    }, [open, state.context]);

    // Keyboard: Escape closes (asking first when a result would be lost), Ctrl/Cmd+Enter applies or generates,
    // Tab stays inside the dialog.
    useEffect(() => {
        if (!open) {
            return undefined;
        }

        const onKeyDown = (event: KeyboardEvent) => {
            if (event.key === 'Escape') {
                event.preventDefault();
                if (state.confirmingClose) {
                    cancelClose();
                } else {
                    requestClose();
                }

                return;
            }

            if (event.key === 'Enter' && (event.metaKey || event.ctrlKey)) {
                event.preventDefault();
                if (canApply && !busy) {
                    apply();
                } else {
                    generate();
                }

                return;
            }

            if (event.key === 'Tab' && dialogRef.current !== null) {
                const focusable = Array.from(dialogRef.current.querySelectorAll<HTMLElement>(FOCUSABLE));
                if (focusable.length === 0) {
                    return;
                }
                const first = focusable[0];
                const last = focusable[focusable.length - 1];
                const active = document.activeElement;
                if (!dialogRef.current.contains(active)) {
                    event.preventDefault();
                    first.focus();
                } else if (event.shiftKey && active === first) {
                    event.preventDefault();
                    last.focus();
                } else if (!event.shiftKey && active === last) {
                    event.preventDefault();
                    first.focus();
                }
            }
        };

        document.addEventListener('keydown', onKeyDown);

        return () => document.removeEventListener('keydown', onKeyDown);
    }, [open, state.confirmingClose, canApply, busy, apply, generate, requestClose, cancelClose]);

    if (!open || state.context === null) {
        return null;
    }

    const context = state.context;
    const blocker = generateBlocker(state);
    const showImage = offersImageGeneration(context.fieldType);
    const streaming = state.status === 'streaming';
    const showResult = hasText || hasImage;

    return (
        <AssetsProvider value={{ getIconPath: iconPath }}>
            <div className="masilia-suggest-overlay" onClick={suggest.requestClose} />
            <div
                ref={dialogRef}
                className="masilia-suggest"
                role="dialog"
                aria-modal="true"
                aria-labelledby="masilia-suggest-title"
                aria-busy={busy}
            >
                <header className="masilia-suggest__header">
                    <h2 className="masilia-suggest__title" id="masilia-suggest-title">
                        <Icon name="ai" size="small" />
                        AI content assistant
                    </h2>
                    <Button type="tertiary" icon="discard" ariaLabel="Close" title="Close" onClick={suggest.requestClose} />
                </header>

                <div className="masilia-suggest__field">
                    <strong>{context.fieldName || 'Field'}</strong>
                    <span className="masilia-suggest__tag">{FIELD_LABELS[context.fieldType] ?? context.fieldType}</span>
                    {context.language !== '' ? <span className="masilia-suggest__tag">{context.language}</span> : null}
                </div>

                <div className="masilia-suggest__body">
                    <ActionBar context={context} chosen={state.actionId} disabled={busy} onChoose={suggest.chooseAction} />

                    {state.actionId === 'translate' ? (
                        <SourceLanguage
                            value={state.sourceLanguage}
                            currentLanguage={context.language}
                            languages={suggest.languages}
                            disabled={busy}
                            onChange={suggest.setSourceLanguage}
                        />
                    ) : null}

                    <PromptField
                        value={state.prompt}
                        chosen={state.actionId}
                        recent={suggest.recent}
                        disabled={busy}
                        inputRef={promptRef}
                        onChange={suggest.setPrompt}
                    />

                    {showImage ? (
                        <ImagePanel
                            image={state.image}
                            disabled={busy && state.image.status !== 'loading'}
                            onRatio={suggest.setRatio}
                            onGenerate={suggest.generateImage}
                            onCancel={suggest.cancelImage}
                        />
                    ) : null}

                    {context.fieldType !== 'ibexa_image' || hasText ? (
                        <div className="masilia-suggest__section">
                            <AltRadiosListField
                                id="masilia-suggest-mode"
                                name="masilia-suggest-mode"
                                label="When applied"
                                items={MODES}
                                value={state.mode}
                                onChange={(value) => suggest.setMode(value === 'append' ? 'append' : 'replace')}
                            />
                        </div>
                    ) : null}

                    {state.error !== null ? (
                        <div className="masilia-suggest__error" role="alert">{state.error}</div>
                    ) : null}

                    {showResult ? (
                        <section className="masilia-suggest__result" aria-label="Suggestion">
                            <div className="masilia-suggest__resulthead">
                                <span role="status">{streaming ? 'Writing…' : hasImage ? 'Generated image' : 'Suggestion'}</span>
                                <Button type="primary" size="small" disabled={!canApply || busy} onClick={suggest.apply}>
                                    Apply
                                </Button>
                            </div>
                            <div className="masilia-suggest__preview" aria-live="polite" aria-atomic="false">
                                <Preview fieldType={context.fieldType} text={state.suggestion} image={state.image.image} />
                            </div>
                        </section>
                    ) : null}
                </div>

                {state.undo !== null ? (
                    <div className="masilia-suggest__bar" role="status">
                        <span>Applied to the field.</span>
                        <Button type="secondary" size="small" onClick={suggest.undo}>Undo</Button>
                    </div>
                ) : null}

                {state.confirmingClose ? (
                    <div className="masilia-suggest__bar" role="alertdialog" aria-label="Discard the suggestion">
                        <span>This suggestion was not applied. Discard it?</span>
                        <span>
                            <Button type="secondary" size="small" onClick={suggest.cancelClose}>Keep it</Button>{' '}
                            <Button type="primary" size="small" onClick={suggest.confirmClose}>Discard</Button>
                        </span>
                    </div>
                ) : null}

                <footer className="masilia-suggest__footer">
                    <span className="masilia-suggest__blocker">{!busy && !hasText && !hasImage && blocker !== null ? blocker : ''}</span>
                    <Button type="tertiary" onClick={suggest.requestClose}>Cancel</Button>
                    {streaming ? (
                        <Button type="secondary" icon="discard" onClick={suggest.stop}>Stop</Button>
                    ) : (
                        <Button type="primary" icon="ai" disabled={!canGenerate(state)} onClick={suggest.generate}>
                            {hasText ? 'Generate again' : 'Generate'}
                        </Button>
                    )}
                </footer>
            </div>
        </AssetsProvider>
    );
}
