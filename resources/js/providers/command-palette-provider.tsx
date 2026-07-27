import type { ReactNode } from 'react';
import {
    createContext,
    useContext,
    useState,
    useCallback,
    useRef,
} from 'react';

type CommandPaletteContextType = {
    isOpen: boolean;
    open: () => void;
    close: () => void;
    restoreFocus: () => void;
};

const CommandPaletteContext = createContext<
    CommandPaletteContextType | undefined
>(undefined);

export function CommandPaletteProvider({ children }: { children: ReactNode }) {
    const [isOpen, setIsOpen] = useState(false);
    const openerRef = useRef<Element | null>(null);

    const open = useCallback(() => {
        openerRef.current = document.activeElement;
        setIsOpen(true);
    }, []);
    const close = useCallback(() => setIsOpen(false), []);

    const restoreFocus = useCallback(() => {
        const opener = openerRef.current;

        if (opener instanceof HTMLElement && opener.isConnected) {
            opener.focus();
        }
    }, []);

    return (
        <CommandPaletteContext.Provider
            value={{ isOpen, open, close, restoreFocus }}
        >
            {children}
        </CommandPaletteContext.Provider>
    );
}

export function useCommandPalette() {
    const context = useContext(CommandPaletteContext);

    if (context === undefined) {
        throw new Error(
            'useCommandPalette must be used within a CommandPaletteProvider',
        );
    }

    return context;
}
