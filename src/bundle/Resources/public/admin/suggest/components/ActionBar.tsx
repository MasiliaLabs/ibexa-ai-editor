import React from 'react';

import { ACTIONS, hasContent, unavailableReason } from '../actions';
import type { ActionId } from '../actions';
import { Button } from '../../shared/ids';
import type { FieldContext } from '../types';

interface ActionBarProps {
    context: FieldContext;
    chosen: ActionId | null;
    disabled: boolean;
    onChoose: (id: ActionId) => void;
}

/**
 * The one-click actions. Each is a toggle: pressed means chosen. An action that cannot work says why, in
 * its tooltip and in a visible line, instead of being silently greyed out.
 */
export function ActionBar({ context, chosen, disabled, onChoose }: ActionBarProps) {
    const reasons = new Map(ACTIONS.map((action) => [action.id, unavailableReason(action, context)] as const));
    const emptyField = !hasContent(context.fieldType, context.currentValue);

    return (
        <div className="masilia-suggest__section">
            <span className="masilia-suggest__label" id="masilia-suggest-actions-label">Quick actions</span>
            <div className="masilia-suggest__actions" role="group" aria-labelledby="masilia-suggest-actions-label">
                {ACTIONS.map((action) => {
                    const reason = reasons.get(action.id) ?? null;
                    const pressed = chosen === action.id;

                    return (
                        <Button
                            key={action.id}
                            type={pressed ? 'primary' : 'secondary'}
                            size="small"
                            icon={action.icon}
                            title={reason ?? action.hint}
                            disabled={disabled || reason !== null}
                            onClick={() => onChoose(action.id)}
                            extraAria={{ 'aria-pressed': pressed }}
                        >
                            {action.label}
                        </Button>
                    );
                })}
            </div>
            {emptyField ? (
                <p className="masilia-suggest__hint">This field is empty, so there is nothing to rewrite. Describe what to write below.</p>
            ) : null}
        </div>
    );
}
