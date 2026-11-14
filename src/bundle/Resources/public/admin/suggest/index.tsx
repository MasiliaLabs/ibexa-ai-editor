import React, { StrictMode } from 'react';
import type { ReactNode } from 'react';

import { SuggestModal } from './components/SuggestModal';

const ROOT_ID = 'masilia-ai-suggest-root';

interface ReactDomClient {
    createRoot: (container: Element) => { render: (node: ReactNode) => void };
}

/**
 * Ibexa loads the React DOM client API as the global `ReactDOMClient` (react-dom-load); the `react-dom`
 * default export has no `createRoot` under React 19, so it is not used here.
 */
function reactDomClient(): ReactDomClient | null {
    return (window as unknown as { ReactDOMClient?: ReactDomClient }).ReactDOMClient ?? null;
}

/**
 * Mounts the modal once, in its own element appended to the admin body. It stays empty until the injector
 * dispatches `ai-suggest:open`.
 */
function mount(): void {
    const client = reactDomClient();
    if (client === null || document.getElementById(ROOT_ID) !== null) {
        return;
    }

    const container = document.createElement('div');
    container.id = ROOT_ID;
    document.body.appendChild(container);

    client.createRoot(container).render(
        <StrictMode>
            <SuggestModal />
        </StrictMode>,
    );
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', mount);
} else {
    mount();
}
