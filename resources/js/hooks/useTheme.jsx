import { useCallback, useEffect, useState } from 'react';

const STORAGE_KEY = 'aiPjjIT-theme';

function getInitialTheme() {
    if (typeof document !== 'undefined') {
        if (document.documentElement.classList.contains('dark')) {
            return 'dark';
        }
    }

    if (typeof window === 'undefined') {
        return 'light';
    }

    try {
        const stored = window.localStorage.getItem(STORAGE_KEY);
        if (stored === 'dark' || stored === 'light') {
            return stored;
        }
    } catch {
    }

    if (typeof window.matchMedia === 'function' && window.matchMedia('(prefers-color-scheme: dark)').matches) {
        return 'dark';
    }

    return 'light';
}

function applyTheme(theme) {
    if (typeof document === 'undefined') {
        return;
    }

    document.documentElement.classList.toggle('dark', theme === 'dark');
    document.documentElement.style.colorScheme = theme;

    const meta = document.querySelector('meta[name="theme-color"]');
    if (meta) {
        meta.setAttribute('content', theme === 'dark' ? '#061226' : '#ffffff');
    }
}

export default function useTheme() {
    const [theme, setThemeState] = useState(getInitialTheme);

    useEffect(() => {
        applyTheme(theme);

        try {
            window.localStorage.setItem(STORAGE_KEY, theme);
        } catch {
        }
    }, [theme]);

    useEffect(() => {
        const onStorage = (event) => {
            if (event.key === STORAGE_KEY && (event.newValue === 'dark' || event.newValue === 'light')) {
                setThemeState(event.newValue);
            }
        };

        window.addEventListener('storage', onStorage);

        return () => window.removeEventListener('storage', onStorage);
    }, []);

    const setTheme = useCallback((next) => {
        setThemeState(next === 'dark' ? 'dark' : 'light');
    }, []);

    const toggle = useCallback(() => {
        setThemeState((current) => (current === 'dark' ? 'light' : 'dark'));
    }, []);

    return { theme, isDark: theme === 'dark', setTheme, toggle };
}
