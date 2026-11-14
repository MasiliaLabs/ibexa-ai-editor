import React from 'react';

import type { Language } from '../types';

interface SourceLanguageProps {
    value: string;
    currentLanguage: string;
    languages: Language[];
    disabled: boolean;
    onChange: (language: string) => void;
}

/**
 * "Translate from": the languages the saved content exists in, without the one being edited. When the list
 * cannot be loaded, the code can be typed.
 */
export function SourceLanguage({ value, currentLanguage, languages, disabled, onChange }: SourceLanguageProps) {
    const choices = languages.filter((language) => language.code !== currentLanguage);

    return (
        <div className="masilia-suggest__section">
            <label className="masilia-suggest__label" htmlFor="masilia-suggest-source">Translate from</label>
            {choices.length > 0 ? (
                <select
                    id="masilia-suggest-source"
                    className="masilia-suggest__select"
                    value={value}
                    disabled={disabled}
                    onChange={(event) => onChange(event.target.value)}
                >
                    <option value="">Choose a language…</option>
                    {choices.map((language) => (
                        <option key={language.code} value={language.code}>{language.name} ({language.code})</option>
                    ))}
                </select>
            ) : (
                <input
                    id="masilia-suggest-source"
                    className="masilia-suggest__input"
                    type="text"
                    value={value}
                    disabled={disabled}
                    placeholder="Language code, for example fre-FR"
                    onChange={(event) => onChange(event.target.value.trim())}
                />
            )}
            <p className="masilia-suggest__hint">Into {currentLanguage || 'this version'}. The text is read from the saved content.</p>
        </div>
    );
}
