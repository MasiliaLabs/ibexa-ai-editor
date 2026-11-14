import React, { useEffect, useRef, useState } from 'react';
import type { RefObject } from 'react';

import { actionById } from '../actions';
import type { ActionId } from '../actions';

interface PromptFieldProps {
    value: string;
    chosen: ActionId | null;
    recent: string[];
    disabled: boolean;
    inputRef: RefObject<HTMLTextAreaElement | null>;
    onChange: (value: string) => void;
}

/**
 * The free prompt. When an action is chosen it is replaced by what the action does, so the user never
 * wonders whether a typed text and a chosen action are both used.
 */
export function PromptField({ value, chosen, recent, disabled, inputRef, onChange }: PromptFieldProps) {
    const [showRecent, setShowRecent] = useState(false);
    const menuRef = useRef<HTMLDivElement>(null);
    const action = actionById(chosen);

    useEffect(() => {
        if (!showRecent) {
            return undefined;
        }
        const onPointerDown = (event: MouseEvent) => {
            if (menuRef.current !== null && !menuRef.current.contains(event.target as Node)) {
                setShowRecent(false);
            }
        };
        document.addEventListener('mousedown', onPointerDown);

        return () => document.removeEventListener('mousedown', onPointerDown);
    }, [showRecent]);

    return (
        <div className="masilia-suggest__section">
            <div className="masilia-suggest__row">
                <label className="masilia-suggest__label" htmlFor="masilia-suggest-prompt">Or describe what to write</label>
                {recent.length > 0 ? (
                    <div className="masilia-suggest__recent" ref={menuRef}>
                        <button
                            type="button"
                            className="masilia-suggest__link"
                            aria-expanded={showRecent}
                            disabled={disabled}
                            onClick={() => setShowRecent((open) => !open)}
                        >
                            Recent
                        </button>
                        {showRecent ? (
                            <ul className="masilia-suggest__menu" aria-label="Recent prompts">
                                {recent.map((prompt) => (
                                    <li key={prompt}>
                                        <button
                                            type="button"
                                            title={prompt}
                                            onClick={() => {
                                                onChange(prompt);
                                                setShowRecent(false);
                                                inputRef.current?.focus();
                                            }}
                                        >
                                            {prompt.length > 70 ? `${prompt.slice(0, 67)}…` : prompt}
                                        </button>
                                    </li>
                                ))}
                            </ul>
                        ) : null}
                    </div>
                ) : null}
            </div>
            <textarea
                id="masilia-suggest-prompt"
                ref={inputRef}
                className="masilia-suggest__textarea"
                rows={3}
                value={value}
                disabled={disabled}
                placeholder={action !== null ? `${action.label} is chosen. Type here to write your own request instead.` : 'For example: write a two-paragraph introduction about solar energy in Morocco.'}
                onChange={(event) => onChange(event.target.value)}
            />
            {action !== null ? <p className="masilia-suggest__hint"><strong>{action.label}.</strong> {action.hint}</p> : null}
        </div>
    );
}
