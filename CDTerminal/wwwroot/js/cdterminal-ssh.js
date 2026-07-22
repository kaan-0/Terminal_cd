export function enableCommandKeys(element, dotNetReference) {
    if (!element || !dotNetReference) {
        return;
    }

    if (element.__cdTerminalSshKeyHandler) {
        element.removeEventListener(
            'keydown',
            element.__cdTerminalSshKeyHandler
        );
    }

    const handler = function (event) {
        let method = null;
        let argument = null;

        if (event.key === 'Tab') {
            method = 'CompletarComandoDesdeTab';
        } else if (event.key === 'ArrowUp') {
            method = 'NavegarHistorialDesdeTeclado';
            argument = 'up';
        } else if (event.key === 'ArrowDown') {
            method = 'NavegarHistorialDesdeTeclado';
            argument = 'down';
        } else {
            return;
        }

        event.preventDefault();
        event.stopPropagation();

        const invocation = argument === null
            ? dotNetReference.invokeMethodAsync(method)
            : dotNetReference.invokeMethodAsync(method, argument);

        invocation.catch(function (error) {
            console.error('CD Terminal SSH keyboard:', error);
        });
    };

    element.__cdTerminalSshKeyHandler = handler;
    element.addEventListener('keydown', handler);
}

export function disableCommandKeys(element) {
    if (!element || !element.__cdTerminalSshKeyHandler) {
        return;
    }

    element.removeEventListener(
        'keydown',
        element.__cdTerminalSshKeyHandler
    );

    delete element.__cdTerminalSshKeyHandler;
}

// Alias de compatibilidad con la V4.
export function enableCommandTab(element, dotNetReference) {
    enableCommandKeys(element, dotNetReference);
}

export function disableCommandTab(element) {
    disableCommandKeys(element);
}

export function focusCommandEnd(element) {
    if (!element) {
        return;
    }

    element.focus();

    const length = typeof element.value === 'string'
        ? element.value.length
        : 0;

    if (typeof element.setSelectionRange === 'function') {
        element.setSelectionRange(length, length);
    }
}
